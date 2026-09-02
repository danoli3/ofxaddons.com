<?php
declare(strict_types=1);

// Records what an admin did, for the /admin/log page. Never blocks or
// fails the action it's logging - a logging problem shouldn't stop a
// real categorize/ban/import from going through.
function ofx_log_admin_action(PDO $pdo, ?int $userId, string $action, ?int $repoId, ?string $details = null): void
{
    try {
        $pdo->prepare(
            'INSERT INTO admin_logs (user_id, action, repo_id, details, created_at) VALUES (?, ?, ?, ?, NOW())'
        )->execute([$userId, $action, $repoId, $details]);
    } catch (Throwable $e) {
        // don't let a logging failure surface as a failed admin action
    }
}
