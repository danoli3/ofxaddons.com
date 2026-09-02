<?php
declare(strict_types=1);

// Crawls Github for openFrameworks addons and upserts them into the repos/
// users tables. This is a straight port of the previous Rails app's
// lib/importer.rb + lib/github_api.rb + lib/github_data.rb, carrying over
// the fix that whole investigation was about: authenticating with a
// personal access token via an Authorization header (Github retired
// client_id/client_secret query-param auth in 2020), which is the
// difference between a 10 req/min search limit and a 30 req/min one, and
// a 60 req/hr core limit vs a 5000 req/hr one.
final class Importer
{
    private const REPO_TYPES = ['Addon', 'Deleted', 'Empty', 'Incomplete', 'NonAddon', 'Unsorted'];

    private PDO $pdo;
    private string $token;
    /** @var array<int, array> */
    private array $items = [];

    public function __construct(PDO $pdo, string $token)
    {
        $this->pdo = $pdo;
        $this->token = $token;
    }

    public function run(): bool
    {
        $lockPath = sys_get_temp_dir() . '/ofxaddons_importer.lock';
        $lockFile = fopen($lockPath, 'c');
        if (!$lockFile || !flock($lockFile, LOCK_EX | LOCK_NB)) {
            fwrite(STDERR, "Another import is already in progress, skipping.\n");
            return false;
        }

        try {
            $this->search();
            fwrite(STDOUT, 'Fetched ' . count($this->items) . " repos from Github\n");

            $this->prune();
            fwrite(STDOUT, count($this->items) . " repos after pruning non-addons\n");

            foreach ($this->items as $item) {
                $this->upsert($item);
            }

            return true;
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    private function search(): void
    {
        foreach (str_split('0123456789abcdefghijklmnopqrstuvwxyz') as $letter) {
            $this->searchTerm('ofx' . $letter);
        }
    }

    private function searchTerm(string $term): void
    {
        $url = 'https://api.github.com/search/repositories?' . http_build_query([
            'q' => $term . ' in:name',
            'per_page' => 100,
        ]);

        while ($url !== null) {
            [$body, $headers, $status] = $this->request($url);

            if ($status === 200) {
                $data = json_decode($body, true);
                foreach ($data['items'] ?? [] as $item) {
                    $this->items[] = $item;
                }
                $url = $this->nextPageUrl($headers);
                continue;
            }

            if ($this->isRateLimited($headers, $status)) {
                $this->sleepUntilRateLimitReset($headers);
                continue; // retry the same url
            }

            fwrite(STDERR, "Search failed ({$status}) for \"{$term}\": " . substr($body, 0, 200) . "\n");
            $url = null;
        }
    }

    private function prune(): void
    {
        $stmt = $this->pdo->query("SELECT full_name FROM repos WHERE type IN ('Deleted', 'NonAddon')");
        $excluded = array_flip(array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN)));

        $this->items = array_values(array_filter($this->items, function (array $item) use ($excluded): bool {
            if (isset($excluded[strtolower($item['full_name'] ?? '')])) {
                return false; // previously marked Deleted/NonAddon - don't resurrect
            }
            if (!preg_match('/^ofx/i', $item['name'] ?? '')) {
                return false; // doesn't start with "ofx"
            }
            if (empty($item['pushed_at'])) {
                return false; // empty repo
            }
            return true;
        }));
    }

    private function upsert(array $item): void
    {
        $fullName = $item['full_name'] ?? null;
        if (!$fullName) {
            return;
        }

        $contents = $this->fetchContents($fullName);

        $hasMakefile = false;
        $exampleCount = 0;
        $hasCorrectFolder = false;
        $hasThumbnail = false;

        foreach ($contents ?? [] as $entry) {
            $name = $entry['name'] ?? '';
            if ($name === 'addon_config.mk' || $name === 'addon.make') {
                $hasMakefile = true;
            } elseif (preg_match('/example/i', $name)) {
                $exampleCount++;
            } elseif (preg_match('/src/i', $name)) {
                $hasCorrectFolder = true;
            } elseif (preg_match('/ofxaddons_thumbnail\.png/i', $name)) {
                $hasThumbnail = true;
            }
        }

        $userId = $this->upsertUser($item['owner'] ?? []);

        $stmt = $this->pdo->prepare('SELECT id, type FROM repos WHERE full_name = ? LIMIT 1');
        $stmt->execute([$fullName]);
        $existing = $stmt->fetch();

        // Mirrors the original: only Empty/Incomplete get set explicitly.
        // Otherwise an existing repo keeps whatever type it already had
        // (e.g. a human already marked it Addon) and a new one gets the
        // schema default (Unsorted).
        $type = $existing['type'] ?? 'Unsorted';
        if (empty($item['pushed_at'])) {
            $type = 'Empty';
        } elseif (!$hasCorrectFolder) {
            $type = 'Incomplete';
        }

        $params = [
            $this->toDatetime($item['created_at'] ?? null),
            $item['description'] ?? null,
            (int)($item['forks_count'] ?? 0),
            !empty($item['fork']) ? 1 : 0,
            $item['name'] ?? null,
            $item['parent']['full_name'] ?? null,
            $this->toDatetime($item['pushed_at'] ?? null),
            $item['source']['full_name'] ?? null,
            (int)($item['stargazers_count'] ?? 0),
            $exampleCount,
            $hasMakefile ? 1 : 0,
            $hasCorrectFolder ? 1 : 0,
            $hasThumbnail ? 1 : 0,
            $userId,
            $type,
        ];

        if ($existing) {
            $sql = 'UPDATE repos SET created_at=?, description=?, forks_count=?, fork=?, name=?, parent=?,
                    pushed_at=?, source=?, stargazers_count=?, example_count=?, has_makefile=?,
                    has_correct_folder_structure=?, has_thumbnail=?, user_id=?, type=?, updated_at=NOW()
                    WHERE id=?';
            $this->pdo->prepare($sql)->execute([...$params, $existing['id']]);
            fwrite(STDOUT, "Updated {$fullName} ({$type})\n");
        } else {
            $sql = 'INSERT INTO repos (created_at, description, forks_count, fork, name, parent, pushed_at,
                    source, stargazers_count, example_count, has_makefile, has_correct_folder_structure,
                    has_thumbnail, user_id, type, full_name, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())';
            $this->pdo->prepare($sql)->execute([...$params, $fullName]);
            fwrite(STDOUT, "Added {$fullName} ({$type})\n");
        }
    }

    private function upsertUser(array $owner): ?int
    {
        if (empty($owner['id'])) {
            return null;
        }

        $uid = (string)$owner['id'];
        $login = $owner['login'] ?? null;
        $avatarUrl = $owner['avatar_url'] ?? null;

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE provider = ? AND uid = ? LIMIT 1');
        $stmt->execute(['github', $uid]);
        $existing = $stmt->fetch();

        if (!$existing && $login) {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE provider = ? AND login = ? LIMIT 1');
            $stmt->execute(['github', $login]);
            $existing = $stmt->fetch();
        }

        if ($existing) {
            $this->pdo
                ->prepare('UPDATE users SET uid = ?, login = ?, avatar_url = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$uid, $login, $avatarUrl, $existing['id']]);
            return (int)$existing['id'];
        }

        $this->pdo
            ->prepare('INSERT INTO users (provider, uid, login, avatar_url, admin, created_at, updated_at)
                       VALUES (?, ?, ?, ?, 0, NOW(), NOW())')
            ->execute(['github', $uid, $login, $avatarUrl]);
        return (int)$this->pdo->lastInsertId();
    }

    private function fetchContents(string $fullName): ?array
    {
        [$owner, $repo] = array_pad(explode('/', $fullName, 2), 2, '');
        $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/contents';

        while (true) {
            [$body, $headers, $status] = $this->request($url);

            if ($status === 200) {
                $data = json_decode($body, true);
                return is_array($data) ? $data : null;
            }

            if ($this->isRateLimited($headers, $status)) {
                $this->sleepUntilRateLimitReset($headers);
                continue;
            }

            // 404 (empty/deleted repo), 403 (not rate-limit related), etc - just skip
            return null;
        }
    }

    /** @return array{0: string, 1: array<string, string>, 2: int} */
    private function request(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: token {$this->token}",
                'Accept: application/vnd.github.v3+json',
                'User-Agent: ofxaddons-crawler',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("HTTP request to {$url} failed: {$err}");
        }

        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }

        return [$body, $headers, $status];
    }

    private function nextPageUrl(array $headers): ?string
    {
        $link = $headers['link'] ?? null;
        if (!$link) {
            return null;
        }
        foreach (explode(',', $link) as $part) {
            if (str_contains($part, 'rel="next"') && preg_match('/<([^>]+)>/', $part, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    private function isRateLimited(array $headers, int $status): bool
    {
        return in_array($status, [403, 429], true) && ($headers['x-ratelimit-remaining'] ?? '1') === '0';
    }

    private function sleepUntilRateLimitReset(array $headers): void
    {
        $reset = (int)($headers['x-ratelimit-reset'] ?? (time() + 60));
        $seconds = max(1, $reset - time() + 1);
        fwrite(STDOUT, "Hit Github API rate limit, sleeping {$seconds}s\n");
        sleep($seconds);
    }

    private function toDatetime(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }
        try {
            return (new DateTime($iso))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
}
