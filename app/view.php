<?php
declare(strict_types=1);

const OFX_PAGE_SIZE = 24;

function ofx_render(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/views/' . $template . '.php';
    $content = ob_get_clean();
    include __DIR__ . '/views/layout.php';
}

function ofx_not_found(): void
{
    http_response_code(404);
    ofx_render('errors/404', ['title' => 'Not Found']);
}

function ofx_redirect(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function ofx_h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function ofx_flash_get(): ?string
{
    ofx_session_start();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function ofx_avatar_url(?string $url): string
{
    return $url ?: '/app/assets/img/default-gravatar.png';
}

// Addons can ship a 270x70 ofxaddons_thumbnail.png in their repo root
// (see /pages/howto) - has_thumbnail is set by the crawler when it
// finds one. /raw/HEAD/ resolves to whatever the default branch is
// (main, master, ...) without us needing to store it.
function ofx_thumbnail_url(string $fullName): string
{
    return 'https://github.com/' . $fullName . '/raw/HEAD/ofxaddons_thumbnail.png';
}

// Appends ?v=<mtime> so a deploy automatically busts the 30-day cache
// Apache puts on static assets, instead of visitors running whatever
// JS/CSS their browser cached from before the last update.
function ofx_asset_url(string $path): string
{
    $file = dirname(__DIR__) . $path;
    $v = is_readable($file) ? filemtime($file) : time();
    return $path . '?v=' . $v;
}

function ofx_addon_partial(array $addon): void
{
    include __DIR__ . '/views/partials/addon-card.php';
}

function ofx_addon_grid(array $addons, bool $hasMore, string $nextUrl): void
{
    include __DIR__ . '/views/partials/addon-grid.php';
}

function ofx_is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

// Adds ?page=N (replacing any existing page param) to the current
// request's path+query, for the infinite-scroll "load more" link.
function ofx_next_page_url(int $nextPage): string
{
    $params = $_GET;
    $params['page'] = $nextPage;
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return $path . '?' . http_build_query($params);
}

// Splits $rows into [rowsToShow, hasMore] - fetch $limit+1 upstream to
// avoid a second COUNT query.
function ofx_paginate_slice(array $rows, int $limit): array
{
    $hasMore = count($rows) > $limit;
    return [array_slice($rows, 0, $limit), $hasMore];
}

function ofx_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    $mins = intdiv($diff, 60);
    if ($mins < 60) {
        return "{$mins}m ago";
    }
    $hours = intdiv($mins, 60);
    if ($hours < 24) {
        return "{$hours}h ago";
    }
    $days = intdiv($hours, 24);
    if ($days < 30) {
        return "{$days}d ago";
    }
    $months = intdiv($days, 30);
    if ($months < 12) {
        return "{$months}mo ago";
    }
    $years = intdiv($months, 12);
    return "{$years}y ago";
}
