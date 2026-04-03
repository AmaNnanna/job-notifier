<?php
/**
 * Web-triggered search runner
 * Called by the dashboard's "Run Now" button via fetch()
 */

require_once __DIR__ . '/config.php';

// Only allow direct browser or AJAX calls from same origin
if (!isset($_GET['run'])) {
    http_response_code(403);
    exit('Access denied');
}

$outputMode = $_GET['output'] ?? 'json';

ob_start();

// Run the searcher
require_once __DIR__ . '/job_store.php';
require_once __DIR__ . '/mailer.php';

// Include the searcher class without running it at include time
class JobSearcherWeb {

    private array $skillKeywords = [
        'php', 'laravel', 'javascript', 'js', 'node', 'nodejs', 'node.js',
        'react', 'vue', 'full stack', 'fullstack', 'backend', 'frontend', 'web developer', 'software engineer', 'remote', 'work from home', 'technical writer', 'api documentation', 'documentation', 'docs', 'technical content', 'programmer writer', 'software writer', 'technology writer', 'tech writer', 'remote writer', 'remote technical writer'
    ];

    public array $stats = ['found' => 0, 'matched' => 0, 'new' => 0, 'emailed' => false];

    public function run(): void {
        $all = [];
        $all = array_merge($all, $this->fetchRemoteOK());
        $all = array_merge($all, $this->fetchArbeitnow());
        $all = array_merge($all, $this->fetchWorkingNomads());

        if (!empty(ADZUNA_APP_ID)) {
            $all = array_merge($all, $this->fetchAdzuna());
        }

        $all = $this->deduplicate($all);
        $this->stats['found'] = count($all);

        $matched = $this->filterBySkills($all);
        $this->stats['matched'] = count($matched);

        $new = $this->filterNew($matched);
        $this->stats['new'] = count($new);

        if (!empty($new)) {
            saveJobsToStore($new);

            // Save today's jobs for dashboard display
            $dir = __DIR__ . '/data';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dir . '/today_jobs.json', json_encode(array_values($new), JSON_PRETTY_PRINT));

            $mailer = new JobMailer();
            $this->stats['emailed'] = $mailer->sendDailyDigest($new);
        }

        // Append to log
        $logDir = __DIR__ . '/data';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $logLine = '[' . date('Y-m-d H:i:s') . '] Web run: found=' . $this->stats['found']
                 . ' matched=' . $this->stats['matched']
                 . ' new=' . $this->stats['new'] . "\n";
        file_put_contents(LOG_FILE, $logLine, FILE_APPEND);
    }

    private function fetchRemoteOK(): array {
        $jobs = [];
        $url  = 'https://remoteok.com/api?tags=php,javascript,react,nodejs,laravel,vue';
        $data = $this->get($url, ['Referer: https://remoteok.com']);
        if (!$data) return [];
        $listings = json_decode($data, true);
        if (!is_array($listings)) return [];
        foreach ($listings as $item) {
            if (!isset($item['id'])) continue;
            $jobs[] = [
                'id' => 'remoteok_' . $item['id'],
                'title' => $item['position'] ?? 'N/A',
                'company' => $item['company'] ?? 'N/A',
                'location' => 'Remote',
                'url' => $item['url'] ?? "https://remoteok.com/l/{$item['id']}",
                'description' => strip_tags($item['description'] ?? ''),
                'tags' => implode(', ', $item['tags'] ?? []),
                'source' => 'Remote OK',
                'posted_at' => $item['date'] ?? date('Y-m-d'),
            ];
        }
        return $jobs;
    }

    private function fetchArbeitnow(): array {
        $jobs = [];
        $data = $this->get('https://www.arbeitnow.com/api/job-board-api?page=1');
        if (!$data) return [];
        $parsed = json_decode($data, true);
        if (!isset($parsed['data'])) return [];
        foreach ($parsed['data'] as $item) {
            $jobs[] = [
                'id' => 'arbeitnow_' . ($item['slug'] ?? md5($item['url'] ?? '')),
                'title' => $item['title'] ?? 'N/A',
                'company' => $item['company_name'] ?? 'N/A',
                'location' => $item['location'] ?? 'Remote',
                'url' => $item['url'] ?? '#',
                'description' => strip_tags($item['description'] ?? ''),
                'tags' => implode(', ', $item['tags'] ?? []),
                'source' => 'Arbeitnow',
                'posted_at' => date('Y-m-d', strtotime($item['created_at'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    private function fetchWorkingNomads(): array {
        $jobs = [];
        $data = $this->get('https://www.workingnomads.com/api/exposed_jobs/?category=development');
        if (!$data) return [];
        $listings = json_decode($data, true);
        if (!is_array($listings)) return [];
        foreach ($listings as $item) {
            $jobs[] = [
                'id' => 'nomads_' . ($item['id'] ?? md5($item['url'] ?? '')),
                'title' => $item['title'] ?? 'N/A',
                'company' => $item['company_name'] ?? 'N/A',
                'location' => 'Remote',
                'url' => $item['url'] ?? '#',
                'description' => strip_tags($item['description'] ?? ''),
                'tags' => $item['tags'] ?? '',
                'source' => 'Working Nomads',
                'posted_at' => date('Y-m-d', strtotime($item['pub_date'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    private function fetchAdzuna(): array {
        $jobs = [];
        $country = ADZUNA_COUNTRY ?? 'gb';
        foreach (['PHP Laravel', 'JavaScript React', 'Node.js'] as $kw) {
            $url = sprintf(
                'https://api.adzuna.com/v1/api/jobs/%s/search/1?app_id=%s&app_key=%s&what=%s&max_days_old=1&results_per_page=20',
                $country, urlencode(ADZUNA_APP_ID), urlencode(ADZUNA_APP_KEY), urlencode($kw)
            );
            $data = $this->get($url);
            if (!$data) continue;
            $parsed = json_decode($data, true);
            foreach ($parsed['results'] ?? [] as $item) {
                $jobs[] = [
                    'id' => 'adzuna_' . ($item['id'] ?? md5($item['redirect_url'] ?? '')),
                    'title' => $item['title'] ?? 'N/A',
                    'company' => $item['company']['display_name'] ?? 'N/A',
                    'location' => $item['location']['display_name'] ?? 'N/A',
                    'url' => $item['redirect_url'] ?? '#',
                    'description' => strip_tags($item['description'] ?? ''),
                    'tags' => $kw,
                    'source' => 'Adzuna',
                    'posted_at' => date('Y-m-d', strtotime($item['created'] ?? 'now')),
                ];
            }
        }
        return $jobs;
    }

    private function filterBySkills(array $jobs): array {
        return array_filter($jobs, function($job) {
            $hay = strtolower(($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['tags'] ?? ''));
            foreach ($this->skillKeywords as $kw) {
                if (str_contains($hay, $kw)) return true;
            }
            return false;
        });
    }

    private function deduplicate(array $jobs): array {
        $seen = []; $out = [];
        foreach ($jobs as $j) {
            $k = $j['id'] ?? md5($j['url'] ?? $j['title'] ?? '');
            if (!isset($seen[$k])) { $seen[$k] = true; $out[] = $j; }
        }
        return $out;
    }

    private function filterNew(array $jobs): array {
        $seen = loadSeenJobIds();
        return array_filter($jobs, fn($j) => !in_array($j['id'], $seen));
    }

    private function get(string $url, array $headers = []): ?string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; JobBot/1.0)',
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body ?: null;
    }
}

$searcher = new JobSearcherWeb();
$searcher->run();

ob_end_clean();

header('Content-Type: application/json');
echo json_encode($searcher->stats);
