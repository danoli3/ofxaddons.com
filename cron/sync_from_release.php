<?php
declare(strict_types=1);

// Safety-net fallback for the webhook (app/controllers/webhooks.php):
// pulls the latest danoli3/ofxAddons release directly, in case a
// webhook call ever fails to land (site was down, network hiccup,
// etc). Idempotent - safe to run repeatedly. Public repo/release
// asset, no Github auth needed.

require_once __DIR__ . '/../app/env.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/sync.php';

$snapshot = ofx_fetch_latest_crawl_snapshot();
if (!$snapshot) {
    fwrite(STDERR, "Could not fetch latest release from danoli3/ofxAddons\n");
    exit(1);
}

$result = ofx_apply_crawl_snapshot(ofx_db(), $snapshot['addons']);
fwrite(STDOUT, sprintf(
    "Synced from release generated_at=%s: %d added, %d updated, %d skipped (banned)\n",
    $snapshot['generated_at'] ?? 'unknown',
    $result['added'],
    $result['updated'],
    $result['skipped_banned']
));
