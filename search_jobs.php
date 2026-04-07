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
        'php',
        'laravel',
        'javascript',
        'js',
        'node',
        'nodejs',
        'node.js',
        'react',
        'vue',
        'angular',
        'next.js',
        'nuxt',
        'typescript',
        'full stack',
        'fullstack',
        'backend',
        'frontend',
        'web developer',
        'software engineer',
        'remote',
        'work from home',
        'technical writer',
        'api documentation',
        'documentation',
        'docs',
        'technical content',
        'programmer writer',
        'software writer',
        'technology writer',
        'tech writer',
        'remote writer',
        'remote technical writer',
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
            $this->fetchRemotive(),
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
    //[No signup]
    //RemoteOk
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

    //Arbeitnow
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

    //Working Nomads
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

    //Remotive
    private function fetchRemotive(): array
    {
        $jobs = [];
        $url = 'https://remotive.com/api/remote-jobs?category=software-dev&limit=100';
        $data = $this->httpGet($url);
        if (!$data) return [];
        $parsed = json_decode($data, true);
        foreach ($parsed['jobs'] ?? [] as $item) {
            $jobs[] = [
                'id' => 'remotive_' . $item['id'],
                'title' => $item['title'] ?? 'N/A',
                'company' => $item['company_name'] ?? 'N/A',
                'location' => $item['candidate_required_location'] ?? 'N/A',
                'url' => $item['url'] ?? '#',
                'description' => strip_tags($item['description'] ?? ''),
                'tags' => implode(', ', $item['tags'] ?? []),
                'source' => 'Remotive',
                'posted_at' => date('Y-m-d', strtotime($item['publication_date'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    //Himalayas
    private function fetchHimalayas(): array
    {
        $jobs = [];
        $url  = 'https://himalayas.app/jobs/api?skills=php,javascript,react,nodejs&limit=100';
        $data = $this->httpGet($url);
        if (!$data) return [];
        $parsed = json_decode($data, true);
        foreach ($parsed['jobs'] ?? [] as $item) {
            $jobs[] = [
                'id'          => 'himalayas_' . ($item['slug'] ?? md5($item['applicationLink'] ?? '')),
                'title'       => $item['title'] ?? 'N/A',
                'company'     => $item['companyName'] ?? 'N/A',
                'location'    => $item['locationRestrictions'] ?? 'Remote',
                'url'         => $item['applicationLink'] ?? '#',
                'description' => strip_tags($item['description'] ?? ''),
                'tags'        => implode(', ', $item['skills'] ?? []),
                'source'      => 'Himalayas',
                'posted_at'   => date('Y-m-d', strtotime($item['publishedAt'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    //We work remotely
    private function fetchWeWorkRemotely(): array
    {
        $jobs = [];
        $feeds = [
            'https://weworkremotely.com/categories/remote-programming-jobs.rss',
            'https://weworkremotely.com/categories/remote-full-stack-programming-jobs.rss',
        ];
        foreach ($feeds as $url) {
            $xml = $this->httpGet($url);
            if (!$xml) continue;
            // Suppress XML errors for malformed feeds
            libxml_use_internal_errors(true);
            $feed = simplexml_load_string($xml);
            if (!$feed) continue;
            foreach ($feed->channel->item ?? [] as $item) {
                $id = md5((string)$item->link);
                $jobs[] = [
                    'id'          => 'wwr_' . $id,
                    'title'       => (string)$item->title,
                    'company'     => (string)($item->children('https://weworkremotely.com')->company ?? 'N/A'),
                    'location'    => 'Remote',
                    'url'         => (string)$item->link,
                    'description' => strip_tags((string)$item->description),
                    'tags'        => '',
                    'source'      => 'We Work Remotely',
                    'posted_at'   => date('Y-m-d', strtotime((string)$item->pubDate ?? 'now')),
                ];
            }
        }
        return $jobs;
    }

    //Jobicy
    private function fetchJobicy(): array
    {
        $jobs = [];
        $url  = 'https://jobicy.com/api/v2/remote-jobs?count=50&tag=php,javascript,react';
        $data = $this->httpGet($url);
        if (!$data) return [];
        $parsed = json_decode($data, true);
        foreach ($parsed['jobs'] ?? [] as $item) {
            $jobs[] = [
                'id'          => 'jobicy_' . ($item['id'] ?? md5($item['url'] ?? '')),
                'title'       => $item['jobTitle'] ?? 'N/A',
                'company'     => $item['companyName'] ?? 'N/A',
                'location'    => $item['jobGeo'] ?? 'Remote',
                'url'         => $item['url'] ?? '#',
                'description' => strip_tags($item['jobDescription'] ?? ''),
                'tags'        => implode(', ', $item['jobIndustry'] ?? []),
                'source'      => 'Jobicy',
                'posted_at'   => date('Y-m-d', strtotime($item['pubDate'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    //The muse
    private function fetchTheMuse(): array
    {
        $jobs = [];
        if (empty(THEMUSE_API_KEY)) return [];
        $categories = ['Engineering', 'Software Engineer'];
        foreach ($categories as $cat) {
            $url  = 'https://www.themuse.com/api/public/jobs?category=' . urlencode($cat) . '&level=Senior+Level&level=Mid+Level&api_key=' . THEMUSE_API_KEY . '&page=1';
            $data = $this->httpGet($url);
            if (!$data) continue;
            $parsed = json_decode($data, true);
            foreach ($parsed['results'] ?? [] as $item) {
                $jobs[] = [
                    'id'          => 'muse_' . ($item['id'] ?? md5($item['refs']['landing_page'] ?? '')),
                    'title'       => $item['name'] ?? 'N/A',
                    'company'     => $item['company']['name'] ?? 'N/A',
                    'location'    => implode(', ', array_column($item['locations'] ?? [], 'name')),
                    'url'         => $item['refs']['landing_page'] ?? '#',
                    'description' => strip_tags($item['contents'] ?? ''),
                    'tags'        => implode(', ', array_column($item['tags'] ?? [], 'name')),
                    'source'      => 'The Muse',
                    'posted_at'   => date('Y-m-d', strtotime($item['publication_date'] ?? 'now')),
                ];
            }
        }
        return $jobs;
    }

    //Hacker news
    private function fetchHackerNews(): array
    {
        $jobs = [];
        // Find the latest "Who is Hiring" thread ID
        $search = $this->httpGet('https://hn.algolia.com/api/v1/search?query=Ask+HN+Who+is+hiring&tags=story,ask_hn&hitsPerPage=1');
        if (!$search) return [];
        $searchData = json_decode($search, true);
        $threadId   = $searchData['hits'][0]['objectID'] ?? null;
        if (!$threadId) return [];

        // Fetch top-level comments from that thread
        $thread = $this->httpGet("https://hn.algolia.com/api/v1/items/{$threadId}");
        if (!$thread) return [];
        $threadData = json_decode($thread, true);

        $keywords = ['php', 'laravel', 'react', 'vue', 'angular', 'node', 'javascript'];

        foreach (array_slice($threadData['children'] ?? [], 0, 200) as $comment) {
            $text = strip_tags($comment['text'] ?? '');
            if (empty($text)) continue;
            $lower = strtolower($text);
            $match = false;
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) continue;

            // Extract first line as title
            $lines = array_filter(explode("\n", $text));
            $title = trim(substr(reset($lines), 0, 80)) ?: 'HN Job Post';

            $jobs[] = [
                'id'          => 'hn_' . ($comment['id'] ?? md5($text)),
                'title'       => $title,
                'company'     => 'See post',
                'location'    => 'See post',
                'url'         => 'https://news.ycombinator.com/item?id=' . ($comment['id'] ?? ''),
                'description' => substr($text, 0, 300),
                'tags'        => 'hacker news',
                'source'      => 'Hacker News',
                'posted_at'   => date('Y-m-d', $comment['created_at_i'] ?? time()),
            ];
        }
        return $jobs;
    }

    //Dev.to
    private function fetchDevTo(): array
    {
        $jobs = [];
        $tags = ['php', 'javascript', 'react', 'node', 'vue', 'laravel', 'angular'];
        foreach ($tags as $tag) {
            $url  = 'https://dev.to/api/listings?category=cfp&tag=' . $tag . '&per_page=50';
            $data = $this->httpGet($url, ['api-key: ']);
            if (!$data) continue;
            $listings = json_decode($data, true);
            if (!is_array($listings)) continue;
            foreach ($listings as $item) {
                // Only include job/hiring listings
                $title = $item['title'] ?? '';
                $body  = strtolower($item['body_markdown'] ?? '');
                if (
                    !str_contains(strtolower($title), 'hir') &&
                    !str_contains($body, 'hiring') &&
                    !str_contains($body, 'job') &&
                    !str_contains($body, 'remote')
                ) {
                    continue;
                }
                $jobs[] = [
                    'id'          => 'devto_' . ($item['id'] ?? md5($title)),
                    'title'       => $title,
                    'company'     => $item['user']['name'] ?? 'N/A',
                    'location'    => 'Remote / See post',
                    'url'         => 'https://dev.to' . ($item['slug'] ?? ''),
                    'description' => substr(strip_tags($item['body_markdown'] ?? ''), 0, 300),
                    'tags'        => implode(', ', $item['tag_list'] ?? []),
                    'source'      => 'Dev.to',
                    'posted_at'   => date('Y-m-d', strtotime($item['published_at'] ?? 'now')),
                ];
            }
        }
        return $jobs;
    }

    //Gitlab
    private function fetchGitLab(): array
    {
        $jobs = [];
        // GitLab publishes their own open roles as a public JSON feed
        $url  = 'https://about.gitlab.com/jobs/all-jobs.json';
        $data = $this->httpGet($url);
        if (!$data) return [];
        $parsed = json_decode($data, true);
        if (!is_array($parsed)) return [];

        $keywords = [
            'php',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'laravel',
            'frontend',
            'backend',
            'full stack',
            'fullstack',
            'engineer',
            'developer'
        ];

        foreach ($parsed as $item) {
            $haystack = strtolower(
                ($item['title'] ?? '') . ' ' .
                    ($item['department'] ?? '') . ' ' .
                    ($item['description'] ?? '')
            );
            $match = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) continue;

            $jobs[] = [
                'id'          => 'gitlab_' . ($item['id'] ?? md5($item['apply_url'] ?? $item['title'] ?? '')),
                'title'       => $item['title'] ?? 'N/A',
                'company'     => 'GitLab',
                'location'    => $item['location'] ?? 'Remote — Worldwide',
                'url'         => $item['apply_url'] ?? 'https://about.gitlab.com/jobs/',
                'description' => strip_tags($item['description'] ?? ''),
                'tags'        => $item['department'] ?? '',
                'source'      => 'GitLab',
                'posted_at'   => date('Y-m-d', strtotime($item['updated_at'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    //[Requires signup]
    //Adzuma
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
