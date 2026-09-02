<?php
declare(strict_types=1);

// Reads the same .env file format used by the previous Rails deploy attempt
// (KEY=VALUE, one per line). Lives one directory above /app, at the repo/
// docroot root, next to index.php.
function ofx_load_env(): array
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }

    $env = [];
    $path = dirname(__DIR__) . '/.env';
    if (is_readable($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }

    // real environment variables (e.g. set via a hosting panel) win over the file
    foreach (array_keys($env) as $key) {
        $override = getenv($key);
        if ($override !== false) {
            $env[$key] = $override;
        }
    }

    return $env;
}

function ofx_env(string $key, ?string $default = null): ?string
{
    return ofx_load_env()[$key] ?? $default;
}
