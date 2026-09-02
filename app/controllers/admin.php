<?php
declare(strict_types=1);

const OFX_REPO_TYPES = ['Addon', 'Deleted', 'Empty', 'Incomplete', 'NonAddon', 'Unsorted'];

function ofx_admin_index(): void
{
    $admin = ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query('
        SELECT r.*, u.login AS user_login
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type IN ("Unsorted", "Incomplete")
        ORDER BY r.pushed_at DESC
    ');
    $repos = $stmt->fetchAll();

    $repoCategoryIds = [];
    $catStmt = $pdo->query('SELECT repo_id, category_id FROM categorizations');
    while ($row = $catStmt->fetch()) {
        $repoCategoryIds[$row['repo_id']][] = (int)$row['category_id'];
    }

    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY LOWER(name) ASC')->fetchAll();

    ofx_render('admin/index', [
        'repos' => $repos,
        'repoCategoryIds' => $repoCategoryIds,
        'categories' => $categories,
        'admin' => $admin,
        'title' => 'Admin',
    ]);
}

function ofx_admin_update(string $id): void
{
    ofx_require_admin();
    header('Content-Type: application/json');

    $type = $_POST['type'] ?? null;
    if (!in_array($type, OFX_REPO_TYPES, true)) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ['invalid type']]);
        return;
    }

    $categoryIds = array_values(array_filter(array_map('intval', $_POST['category_ids'] ?? [])));

    if ($type === 'Addon' && empty($categoryIds)) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ["Categories can't be empty for an addon"]]);
        return;
    }

    $pdo = ofx_db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE repos SET type = ?, updated_at = NOW() WHERE id = ?')->execute([$type, $id]);
        $pdo->prepare('DELETE FROM categorizations WHERE repo_id = ?')->execute([$id]);

        if (!empty($categoryIds)) {
            $insert = $pdo->prepare(
                'INSERT INTO categorizations (category_id, repo_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
            );
            foreach ($categoryIds as $categoryId) {
                $insert->execute([$categoryId, $id]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 500, 'error' => [$e->getMessage()]]);
        return;
    }

    echo json_encode(['status' => 200, 'repo' => ['id' => (int)$id, 'type' => $type]]);
}

// Repos matching the "ofx" name prefix by coincidence but with nothing
// to do with openFrameworks (e.g. a security tool called "ofxpwn") -
// banning is just a type change to NonAddon, reusing the same update
// endpoint the categorize form already posts to.
function ofx_admin_banned(): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query('
        SELECT r.*, u.login AS user_login
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type = "NonAddon"
        ORDER BY r.updated_at DESC
    ');

    ofx_render('admin/banned', [
        'repos' => $stmt->fetchAll(),
        'title' => 'Banned',
    ]);
}

// GET /admin/export.json or /admin/export.xml - every Addon-type repo
// and the category names it's assigned to, for backup or for seeding
// another install of this same app.
function ofx_admin_export(string $format): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query('
        SELECT r.full_name, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR "||") AS categories
        FROM repos r
        JOIN categorizations cz ON cz.repo_id = r.id
        JOIN categories c ON c.id = cz.category_id
        WHERE r.type = "Addon"
        GROUP BY r.id
        ORDER BY LOWER(r.full_name) ASC
    ');
    $entries = array_map(
        fn($row) => ['full_name' => $row['full_name'], 'categories' => explode('||', $row['categories'])],
        $stmt->fetchAll()
    );

    if ($format === 'xml') {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('addons');
        foreach ($entries as $entry) {
            $xml->startElement('addon');
            $xml->writeAttribute('full_name', $entry['full_name']);
            foreach ($entry['categories'] as $cat) {
                $xml->writeElement('category', $cat);
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();

        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="ofxaddons-export.xml"');
        echo $xml->outputMemory();
        return;
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ofxaddons-export.json"');
    echo json_encode($entries, JSON_PRETTY_PRINT);
}

// POST /admin/import - upload a .json or .xml file in the same shape
// ofx_admin_export produces (full_name + categories per addon) and
// bulk-apply it: matching repos get marked Addon and their
// categorizations replaced. Unknown category names are created.
// Repos not found by full_name are reported back, not silently
// dropped.
function ofx_admin_import(): void
{
    ofx_require_admin();

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash'] = 'Import failed: no file uploaded.';
        ofx_redirect('/admin/repos');
        return;
    }

    $name = $_FILES['file']['name'];
    $contents = file_get_contents($_FILES['file']['tmp_name']);
    $isXml = str_ends_with(strtolower($name), '.xml');

    try {
        $entries = $isXml ? ofx_parse_import_xml($contents) : ofx_parse_import_json($contents);
    } catch (Throwable $e) {
        $_SESSION['flash'] = 'Import failed: ' . $e->getMessage();
        ofx_redirect('/admin/repos');
        return;
    }

    $result = ofx_apply_addon_import(ofx_db(), $entries);

    $_SESSION['flash'] = "Import done: {$result['updated']} addon(s) categorized, "
        . "{$result['notFound']} not found in this database.";
    ofx_redirect('/admin/repos');
}

function ofx_parse_import_json(string $contents): array
{
    $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('expected a JSON array of {full_name, categories}');
    }
    return $data;
}

function ofx_parse_import_xml(string $contents): array
{
    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contents);
    libxml_use_internal_errors($prev);
    if ($xml === false) {
        throw new RuntimeException('invalid XML');
    }

    $entries = [];
    foreach ($xml->addon as $addon) {
        $categories = [];
        foreach ($addon->category as $cat) {
            $categories[] = (string)$cat;
        }
        $entries[] = ['full_name' => (string)$addon['full_name'], 'categories' => $categories];
    }
    return $entries;
}

// Shared by the upload-based import above and by one-off seeding
// scripts (e.g. bulk-loading categorization scraped from elsewhere).
function ofx_apply_addon_import(PDO $pdo, array $entries): array
{
    $updated = 0;
    $notFound = 0;

    foreach ($entries as $entry) {
        $fullName = $entry['full_name'] ?? null;
        $categoryNames = array_filter(array_map('trim', $entry['categories'] ?? []));
        if (!$fullName || empty($categoryNames)) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT id FROM repos WHERE LOWER(full_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$fullName]);
        $repoId = $stmt->fetchColumn();
        if (!$repoId) {
            $notFound++;
            continue;
        }

        $categoryIds = [];
        foreach ($categoryNames as $name) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
            $stmt->execute([$name]);
            $categoryId = $stmt->fetchColumn();
            if (!$categoryId) {
                $pdo->prepare('INSERT INTO categories (name, created_at, updated_at) VALUES (?, NOW(), NOW())')
                    ->execute([$name]);
                $categoryId = $pdo->lastInsertId();
            }
            $categoryIds[] = $categoryId;
        }

        $pdo->prepare('UPDATE repos SET type = "Addon", updated_at = NOW() WHERE id = ?')->execute([$repoId]);
        $pdo->prepare('DELETE FROM categorizations WHERE repo_id = ?')->execute([$repoId]);
        $insert = $pdo->prepare(
            'INSERT INTO categorizations (category_id, repo_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
        );
        foreach ($categoryIds as $categoryId) {
            $insert->execute([$categoryId, $repoId]);
        }

        $updated++;
    }

    return ['updated' => $updated, 'notFound' => $notFound];
}
