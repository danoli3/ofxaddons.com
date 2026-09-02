<?php
declare(strict_types=1);

// Entry point for cron: php cron/run_importer.php
// Deliberately not named importer.php/Importer.php - this repo is
// developed on a case-insensitive filesystem (macOS), where those two
// names collide into a single file.

require_once __DIR__ . '/../app/env.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/Importer.php';

$token = ofx_env('GITHUB_TOKEN');
if (!$token) {
    fwrite(STDERR, "GITHUB_TOKEN is not set in .env - refusing to run unauthenticated (crippling rate limits).\n");
    exit(1);
}

$importer = new Importer(ofx_db(), $token);
$importer->run();
