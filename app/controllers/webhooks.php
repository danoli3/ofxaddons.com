<?php
declare(strict_types=1);

// POST /webhooks/sync - called by danoli3/ofxAddons's Github Action
// right after it publishes a new crawl release, so the site doesn't
// have to wait for its own polling cron. Bearer-token authenticated
// against SYNC_SECRET in .env (also set as a repo secret over there).
function ofx_webhook_sync(): void
{
    header('Content-Type: application/json');

    $secret = ofx_env('SYNC_SECRET');
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $provided = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';

    if (!$secret || !hash_equals($secret, $provided)) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'error' => 'forbidden']);
        return;
    }

    $snapshot = ofx_fetch_latest_crawl_snapshot();
    if (!$snapshot) {
        http_response_code(502);
        echo json_encode(['status' => 502, 'error' => 'could not fetch latest release']);
        return;
    }

    $result = ofx_apply_crawl_snapshot(ofx_db(), $snapshot['addons']);
    echo json_encode(['status' => 200] + $result);
}

// GET /banned.json - public list of full_names the crawler shouldn't
// bother re-fetching (NonAddon/Deleted). No auth: it's not sensitive,
// and it needs to be readable from a Github Actions runner without a
// shared secret.
function ofx_banned_json(): void
{
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    echo json_encode(ofx_banned_full_names(ofx_db()));
}
