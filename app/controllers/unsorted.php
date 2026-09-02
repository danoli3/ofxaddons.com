<?php
declare(strict_types=1);

function ofx_unsorted_index(): void
{
    $pdo = ofx_db();
    $stmt = $pdo->query('
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type IN ("Unsorted", "Incomplete")
        ORDER BY r.stargazers_count DESC, r.example_count DESC, r.pushed_at DESC, LOWER(r.name) ASC
    ');
    $repos = $stmt->fetchAll();

    ofx_render('unsorted/index', [
        'repos' => $repos,
        'title' => 'Unsorted',
    ]);
}
