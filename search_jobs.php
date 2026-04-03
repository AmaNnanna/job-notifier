<?php
/**
 * Job Search Engine
 * Scrapes Google Jobs / job boards for PHP, Laravel, JS, Node.js, React/Vue/Angular roles
 * Run via cron: 0 7 * * * /usr/bin/php /path/to/search_jobs.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/job_store.php';

class JobSearcher {

    private array $skills = [
        'PHP Laravel developer',
        'JavaScript Node.js developer',
        'React Vue Angular frontend developer',
        'Full stack PHP JavaScript developer',
        'Laravel Node.js engineer',
    ];

    private array $jobBoards = [
        'linkedin'   => 'https://www.linkedin.com/jobs/search/?keywords=%s&f_TPR=r86400',
        'indeed'     => 'https://www.indeed.com/jobs?q=%s&fromage=1',
        'remoteok'   => 'https://remoteok.com/api?tags=%s',
        'workingnomads' => 'https://www.workingnomads.com/api/exposed_jobs/?category=development',
        'arbeitnow'  => 'https://www.arbeitnow.com/api/job-board-api',
        'adzuna'     => 'https://api.adzuna.com/v1/api/jobs/%s/search/1?app_id=%s&app_key=%s&what=%s&max_days_old=1',
    ];

    public function run(): void {
        echo "[" . date('Y-m-d H:i:s') . "] Starting job search...\n";

        $allJobs = [];

        // Fetch from free APIs
        $allJobs = array_merge($allJobs, $this->fetchRemoteOK());
        $allJobs = array_merge($allJobs, $this->fetchArbeitnow());
        $allJobs = array_merge($allJobs, $this->fetchWorkingNomads());

        if (!empty(ADZUNA_APP_ID) && !empty(ADZUNA_APP_KEY)) {
            $allJobs = array_merge($allJobs, $this->fetchAdzuna());
        }

        // Deduplicate by job ID/URL
        $allJobs = $this->deduplicateJobs($allJobs);

        // Filter by skill relevance
        $matched = $this->filterBySkills($allJobs);

        // Remove already-seen jobs
        $newJobs = $this->filterNewJobs($matched);

        echo "[" . date('Y-m-d H:i:s') . "] Found " . count($newJobs) . " new matching jobs.\n";

        if (!empty($newJobs)) {
            // Save to store
            saveJobsToStore($newJobs);

            // Send email
            $mailer = new JobMailer();
            $mailer->sendDailyDigest($newJobs);
            echo "[" . date('Y-m-d H:i:s') . "] Email sent successfully.\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] No new jobs found. No email sent.\n";
        }
    }

    // ─── Remote OK (free public JSON API) ───────────────────────────────────
    private function fetchRemoteOK(): array {
        $jobs = [];
        $tags = urlencode('php,javascript,react,nodejs,laravel,vue');
        $url  = "https://remoteok.com/api?tags={$tags}";

        $data = $this->httpGet($url, ['Referer: https://remoteok.com']);
        if (!$data) return [];

        $listings = json_decode($data, true);
        if (!is_array($listings)) return [];

        foreach ($listings as $item) {
            if (!isset($item['id'])) continue;
            $jobs[] = [
                'id'          => 'remoteok_' . $item['id'],
                'title'       => $item['position'] ?? 'N/A',
                'company'     => $item['company'] ?? 'N/A',
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

    // ─── Arbeitnow (free API, remote + visa-sponsored) ──────────────────────
    private function fetchArbeitnow(): array {
        $jobs = [];
        $url  = 'https://www.arbeitnow.com/api/job-board-api?page=1';

        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!isset($parsed['data'])) return [];

        foreach ($parsed['data'] as $item) {
            $jobs[] = [
                'id'          => 'arbeitnow_' . ($item['slug'] ?? md5($item['url'] ?? '')),
                'title'       => $item['title'] ?? 'N/A',
                'company'     => $item['company_name'] ?? 'N/A',
                'location'    => $item['location'] ?? 'Remote',
                'url'         => $item['url'] ?? '#',
                'description' => strip_tags($item['description'] ?? ''),
                'tags'        => implode(', ', $item['tags'] ?? []),
                'source'      => 'Arbeitnow',
                'posted_at'   => date('Y-m-d', strtotime($item['created_at'] ?? 'now')),
            ];
        }

        return $jobs;
    }

    // ─── Working Nomads (free API) ───────────────────────────────────────────
    private function fetchWorkingNomads(): array {
        $jobs = [];
        $url  = 'https://www.workingnomads.com/api/exposed_jobs/?category=development';

        $data = $this->httpGet($url);
        if (!$data) return [];

        $listings = json_decode($data, true);
        if (!is_array($listings)) return [];

        foreach ($listings as $item) {
            $jobs[] = [
                'id'          => 'nomads_' . ($item['id'] ?? md5($item['url'] ?? '')),
                'title'       => $item['title'] ?? 'N/A',
                'company'     => $item['company_name'] ?? 'N/A',
                'location'    => 'Remote',
                'url'         => $item['url'] ?? '#',
                'description' => strip_tags($item['description'] ?? ''),
                'tags'        => $item['tags'] ?? '',
                'source'      => 'Working Nomads',
                'posted_at'   => date('Y-m-d', strtotime($item['pub_date'] ?? 'now')),
            ];
        }

        return $jobs;
    }

    // ─── Adzuna (optional — requires free API key) ───────────────────────────
    private function fetchAdzuna(): array {
        $jobs     = [];
        $keywords = ['PHP Laravel', 'JavaScript React', 'Node.js', 'Vue Angular'];
        $country  = ADZUNA_COUNTRY ?? 'gb';

        foreach ($keywords as $kw) {
            $url = sprintf(
                'https://api.adzuna.com/v1/api/jobs/%s/search/1?app_id=%s&app_key=%s&what=%s&max_days_old=1&results_per_page=20',
                $country,
                urlencode(ADZUNA_APP_ID),
                urlencode(ADZUNA_APP_KEY),
                urlencode($kw)
            );

            $data = $this->httpGet($url);
            if (!$data) continue;

            $parsed = json_decode($data, true);
            if (!isset($parsed['results'])) continue;

            foreach ($parsed['results'] as $item) {
                $jobs[] = [
                    'id'          => 'adzuna_' . ($item['id'] ?? md5($item['redirect_url'] ?? '')),
                    'title'       => $item['title'] ?? 'N/A',
                    'company'     => $item['company']['display_name'] ?? 'N/A',
                    'location'    => $item['location']['display_name'] ?? 'N/A',
                    'url'         => $item['redirect_url'] ?? '#',
                    'description' => strip_tags($item['description'] ?? ''),
                    'tags'        => $kw,
                    'source'      => 'Adzuna',
                    'posted_at'   => date('Y-m-d', strtotime($item['created'] ?? 'now')),
                ];
            }
        }

        return $jobs;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────
    private function filterBySkills(array $jobs): array {
        $keywords = [
            'php', 'laravel', 'javascript', 'js', 'node', 'nodejs', 'node.js',
            'react', 'vue', 'angular', 'next.js', 'nuxt', 'typescript',
            'full stack', 'fullstack', 'backend', 'frontend', 'web developer'
        ];

        return array_filter($jobs, function($job) use ($keywords) {
            $haystack = strtolower(
                ($job['title'] ?? '') . ' ' .
                ($job['description'] ?? '') . ' ' .
                ($job['tags'] ?? '')
            );
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) return true;
            }
            return false;
        });
    }

    private function deduplicateJobs(array $jobs): array {
        $seen = [];
        $unique = [];
        foreach ($jobs as $job) {
            $key = $job['id'] ?? md5($job['url'] ?? $job['title'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $job;
            }
        }
        return $unique;
    }

    private function filterNewJobs(array $jobs): array {
        $seen = loadSeenJobIds();
        $newJobs = [];
        foreach ($jobs as $job) {
            if (!in_array($job['id'], $seen)) {
                $newJobs[] = $job;
            }
        }
        return $newJobs;
    }

    private function httpGet(string $url, array $extraHeaders = []): ?string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; JobBot/1.0)',
            CURLOPT_HTTPHEADER     => array_merge([
                'Accept: application/json',
            ], $extraHeaders),
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            echo "[WARN] cURL error for {$url}: {$err}\n";
            return null;
        }
        return $body ?: null;
    }
}

// ─── Entry point ─────────────────────────────────────────────────────────────
$searcher = new JobSearcher();
$searcher->run();
