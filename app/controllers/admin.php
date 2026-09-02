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
