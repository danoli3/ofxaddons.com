<?php
declare(strict_types=1);

function ofx_categories_index(): void
{
    $pdo = ofx_db();

    $stmt = $pdo->query('
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url, cz.category_id
        FROM repos r
        JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type = "Addon"
        ORDER BY LOWER(r.name) ASC
    ');

    $addonsByCategory = [];
    while ($row = $stmt->fetch()) {
        $addonsByCategory[$row['category_id']][] = $row;
    }

    $categories = $pdo->query('SELECT * FROM categories ORDER BY LOWER(name) ASC')->fetchAll();
    // only categories that actually have addons, matching Category.having_addons
    $categories = array_values(array_filter($categories, fn($c) => !empty($addonsByCategory[$c['id']])));

    ofx_render('categories/index', [
        'categories' => $categories,
        'addonsByCategory' => $addonsByCategory,
        'title' => 'Categories',
    ]);
}

function ofx_categories_show(string $id): void
{
    $pdo = ofx_db();

    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category) {
        ofx_not_found();
        return;
    }

    $stmt = $pdo->prepare('
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url
        FROM repos r
        JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN users u ON u.id = r.user_id
        WHERE cz.category_id = ? AND r.type = "Addon"
        ORDER BY LOWER(r.name) ASC
    ');
    $stmt->execute([$id]);
    $addons = $stmt->fetchAll();

    ofx_render('categories/show', [
        'category' => $category,
        'addons' => $addons,
        'title' => $category['name'],
    ]);
}
