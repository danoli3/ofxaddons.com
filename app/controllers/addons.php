<?php
declare(strict_types=1);

function ofx_addons_index(): void
{
    ofx_render_addons_sorted($_GET['sort'] ?? null);
}

function ofx_addons_freshest(): void
{
    ofx_render_addons_sorted('freshest');
}

function ofx_addons_popular(): void
{
    ofx_render_addons_sorted('popular');
}

function ofx_render_addons_sorted(?string $sort): void
{
    // whitelisted, not user-concatenated - safe to interpolate
    $order = 'LOWER(r.name) ASC';
    if ($sort === 'freshest') {
        $order = 'r.pushed_at DESC';
    } elseif ($sort === 'popular') {
        $order = 'r.stargazers_count DESC';
    }

    $pdo = ofx_db();
    $stmt = $pdo->query("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type = 'Addon'
        ORDER BY {$order}
    ");
    $addons = $stmt->fetchAll();

    ofx_render('addons/index', [
        'addons' => $addons,
        'sort' => $sort,
        'title' => 'All Addons',
    ]);
}
