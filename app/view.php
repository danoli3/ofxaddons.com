<?php
declare(strict_types=1);

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

function ofx_addon_partial(array $addon): void
{
    include __DIR__ . '/views/partials/addon-card.php';
}
