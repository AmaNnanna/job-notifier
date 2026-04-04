<?php
/**
 * Job Search Engine — unified entry point
 *
 * Runs in two modes detected automatically at the bottom of this file:
 *
 *   CLI / cron  →  php search_jobs.php
 *                  Logs to stdout. Cron: 0 7 * * * /usr/bin/php /path/to/search_jobs.php
 *
 *   Web / AJAX  →  GET ?run=1   (called by the dashboard "Run Now" button)
 *                  Responds with JSON: {"found":N,"matched":N,"new":N,"emailed":true|false}
 *                  Add ?output=json (default) or ?output=text as needed.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/job_store.php';

// ─── Shared searcher ──────────────────────────────────────────────────────────

class JobSearcher
{
    /** Keywords used to filter fetched jobs by relevance */
    private array $skillKeywords = [
        'php', 'laravel', 'javascript', 'js', 'node', 'nodejs', 'node.js',
        'react', 'vue', 'angular', 'next.js', 'nuxt', 'typescript',
        'full stack', 'fullstack', 'backend', 'frontend', 'web developer',
        'software engineer', 'remote', 'work from home',
        'technical writer', 'api documentation', 'documentation', 'docs',
        'technical content', 'programmer writer', 'software writer',
        'technology writer', 'tech writer', 'remote writer', 'remote technical writer',
    ];

    /** Accumulated run statistics — populated by run() */
    public array $stats = [
        'found'   => 0,
        'matched' => 0,
        'new'     => 0,
        'emailed' => false,
    ];

    // ─── Public entry point ───────────────────────────────────────────────────

    /**
     * Fetch, filter, deduplicate, persist, and optionally email new jobs.
     *
     * @param string $mode  'cli' → log to stdout | 'web' → populate $this->stats only
     */
    public function run(string $mode = 'cli'): void
    {
        $this->log($mode, 'Starting job search…');

        // 1. Fetch from all sources
        $all = array_merge(
            $this->fetchRemoteOK(),
            $this->fetchArbeitnow(),
            $this->fetchWorkingNomads(),
            !empty(ADZUNA_APP_ID) && !empty(ADZUNA_APP_KEY) ? $this->fetchAdzuna() : [],
        );

        // 2. Deduplicate, filter by skill, filter already-seen
        $all     = $this->deduplicate($all);
        $matched = $this->filterBySkills($all);
        $new     = $this->filterNew($matched);

        // 3. Update stats
        $this->stats['found']   = count($all);
        $this->stats['matched'] = count($matched);
        $this->stats['new']     = count($new);

        $this->log($mode, "Found {$this->stats['found']} total, {$this->stats['matched']} matched, {$this->stats['new']} new.");

        if (!empty($new)) {
            // 4a. Persist new job IDs so they won't appear again
            saveJobsToStore($new);

            // 4b. Web mode: save today's jobs for the dashboard to display
            if ($mode === 'web') {
                $dir = __DIR__ . '/data';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents(
                    $dir . '/today_jobs.json',
                    json_encode(array_values($new), JSON_PRETTY_PRINT)
                );
            }

            // 4c. Send digest email (both modes)
            $mailer = new JobMailer();
            $this->stats['emailed'] = (bool) $mailer->sendDailyDigest($new);
            $this->log($mode, $this->stats['emailed'] ? 'Email sent.' : 'Email send failed.');
        } else {
            $this->log($mode, 'No new jobs. No email sent.');
        }

        // 5. Append a run summary to the log file (both modes)
        $this->writeLog();
    }

    // ─── Source fetchers ──────────────────────────────────────────────────────

    private function fetchRemoteOK(): array
    {
        $jobs = [];
        $url  = 'https://remoteok.com/api?tags=php,javascript,react,nodejs,laravel,vue';
        $data = $this->httpGet($url, ['Referer: https://remoteok.com']);

        if (!$data) {
            return [];
        }

        $listings = json_decode($data, true);

        if (!is_array($listings)) {
            return [];
        }

        foreach ($listings as $item) {
            if (!isset($item['id'])) {
                continue;
            }
            $jobs[] = [
                'id'          => 'remoteok_' . $item['id'],
                'title'       => $item['position'] ?? 'N/A',
                'company'     => $item['company']  ?? 'N/A',
                'location'    => 'Remote',
                'url'         => $item['url'] ?? "https://remoteok.com/l/{$item['id']}",
                'description' => strip_tags($item['description'] ?? ''),
                'tags'        => implode(', ', $item['tags'] ?? []),
                'source'      => 'Remote OK',
                'posted_at'   => $item['date'] ?? date('Y-m-d'),
            ];
        }

        return $jobs;
    }

    private function fetchArbeitnow(): array
    {
        $jobs = [];
        $data = $this->httpGet('https://www.arbeitnow.com/api/job-board-api?page=1');

        if (!$data) {
            return [];
        }

        $parsed = json_decode($data, true);

        if (!isset($parsed['data'])) {
            return [];
        }

        foreach ($parsed['data'] as $item) {
            $jobs[] = [
                'id'          => 'arbeitnow_' . ($item['slug'] ?? md5($item['url'] ?? '')),
                'title'       => $item['title']        ?? 'N/A',
                'company'     => $item['company_name'] ?? 'N/A',
                'location'    => $item['location']     ?? 'Remote',
                'url'         => $item['url']          ?? '#',
                'description' => strip_tags($item['description'] ?? ''),
                'tags'        => implode(', ', $item['tags'] ?? []),
                'source'      => 'Arbeitnow',
                'posted_at'   => date('Y-m-d', strtotime($item['created_at'] ?? 'now')),
            ];
        }

        return $jobs;
    }

    private function fetchWorkingNomads(): array
    {
        $jobs = [];
        $data = $this->httpGet('https://www.workingnomads.com/api/exposed_jobs/?category=development');

        if (!$data) {
            return [];
        }

        $listings = json_decode($data, true);

        if (!is_array($listings)) {
            return [];
        }

        foreach ($listings as $item) {
            $jobs[] = [
                'id'          => 'nomads_' . ($item['id'] ?? md5($item['url'] ?? '')),
                'title'       => $item['title']        ?? 'N/A',
                'company'     => $item['company_name'] ?? 'N/A',
                'location'    => 'Remote',
                'url'         => $item['url']          ?? '#',
                'description' => strip_tags($item['description'] ?? ''),
                'tags'        => $item['tags']         ?? '',
                'source'      => 'Working Nomads',
                'posted_at'   => date('Y-m-d', strtotime($item['pub_date'] ?? 'now')),
            ];
        }

        return $jobs;
    }

    private function fetchAdzuna(): array
    {
        $jobs    = [];
        $country = ADZUNA_COUNTRY ?? 'gb';

        foreach (['PHP Laravel', 'JavaScript React', 'Node.js', 'Vue Angular'] as $kw) {
            $url = sprintf(
                'https://api.adzuna.com/v1/api/jobs/%s/search/1?app_id=%s&app_key=%s&what=%s&max_days_old=1&results_per_page=20',
                $country,
                urlencode(ADZUNA_APP_ID),
                urlencode(ADZUNA_APP_KEY),
                urlencode($kw)
            );

            $data = $this->httpGet($url);

            if (!$data) {
                continue;
            }

            $parsed = json_decode($data, true);

            foreach ($parsed['results'] ?? [] as $item) {
                $jobs[] = [
                    'id'          => 'adzuna_' . ($item['id'] ?? md5($item['redirect_url'] ?? '')),
                    'title'       => $item['title']                       ?? 'N/A',
                    'company'     => $item['company']['display_name']     ?? 'N/A',
                    'location'    => $item['location']['display_name']    ?? 'N/A',
                    'url'         => $item['redirect_url']                ?? '#',
                    'description' => strip_tags($item['description']      ?? ''),
                    'tags'        => $kw,
                    'source'      => 'Adzuna',
                    'posted_at'   => date('Y-m-d', strtotime($item['created'] ?? 'now')),
                ];
            }
        }

        return $jobs;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function filterBySkills(array $jobs): array
    {
        return array_values(array_filter($jobs, function (array $job): bool {
            $haystack = strtolower(
                ($job['title']       ?? '') . ' ' .
                ($job['description'] ?? '') . ' ' .
                ($job['tags']        ?? '')
            );
            foreach ($this->skillKeywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    return true;
                }
            }
            return false;
        }));
    }

    private function deduplicate(array $jobs): array
    {
        $seen = [];
        $out  = [];

        foreach ($jobs as $job) {
            $key = $job['id'] ?? md5($job['url'] ?? $job['title'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[]      = $job;
            }
        }

        return $out;
    }

    private function filterNew(array $jobs): array
    {
        $seen = loadSeenJobIds();
        return array_values(array_filter($jobs, fn(array $j) => !in_array($j['id'], $seen, true)));
    }

    private function httpGet(string $url, array $extraHeaders = []): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; JobBot/1.0)',
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $extraHeaders),
        ]);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("[JobSearcher] cURL error for {$url}: {$err}");
            return null;
        }

        return $body ?: null;
    }

    /** Write a one-liner run summary to LOG_FILE (defined in config.php). */
    private function writeLog(): void
    {
        if (!defined('LOG_FILE')) {
            return;
        }

        $dir = dirname(LOG_FILE);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $line = sprintf(
            "[%s] Run: found=%d matched=%d new=%d emailed=%s\n",
            date('Y-m-d H:i:s'),
            $this->stats['found'],
            $this->stats['matched'],
            $this->stats['new'],
            $this->stats['emailed'] ? 'yes' : 'no'
        );

        file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }

    /** Print to stdout in CLI mode only. */
    private function log(string $mode, string $message): void
    {
        if ($mode === 'cli') {
            echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        }
    }
}

// ─── Mode detection & entry point ─────────────────────────────────────────────

$isCli = php_sapi_name() === 'cli';
$isWeb = !$isCli && isset($_GET['run']);

if ($isCli) {
    // ── CLI / cron mode ────────────────────────────────────────────────────
    $searcher = new JobSearcher();
    $searcher->run('cli');

} elseif ($isWeb) {
    // ── Web / AJAX mode ────────────────────────────────────────────────────
    // Suppress any accidental output so the JSON response is clean.
    ob_start();
    $searcher = new JobSearcher();
    $searcher->run('web');
    ob_end_clean();

    header('Content-Type: application/json');
    echo json_encode($searcher->stats);

} else {
    // ── Direct browser hit without ?run=1 ──────────────────────────────────
    http_response_code(403);
    exit('Access denied');
}