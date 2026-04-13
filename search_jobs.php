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
        'python',
        'c',
        'c#',
        'c++',
        'java',
        'full stack',
        'fullstack',
        'backend',
        'frontend',
        'web developer',
        'software engineer',
        'cloud',
        'cloud engineer',
        'devops',
        'infrastructure',
        'system administrator',
        'docker',
        'aws',
        'container',
        'remote',
        'work from home',
        'technical writer',
        'api documentation',
        'documentation',
        'technical content',
        'programmer writer',
        'software writer',
        'technology writer',
        'tech writer',
        'remote writer',
        'remote technical writer',
        'cloud engineer',
        'startup',
    ];

    private array $categoryKeywords = [
        'development',
        'software-dev',
        'programming',
        'software programming',
        'software development',
        'software engineer',
        'engineering',
        'writing',
        'content',
        'technical writing',
        'documentation',
        'devops',
        'infrastructure',
        'startup',
        'cloud',
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

    private array $sources;
    public function __construct()
    {
        $this->sources = [
            'remoteOK' => ['$this->skillKeywords'],
            'arbeitNow' => [$this->categoryKeywords, $this->skillKeywords],
            'workingNomads' => [$this->categoryKeywords],
            'remotive' => [$this->categoryKeywords],
            'himalayas' => [$this->skillKeywords],
            'jobicy' => [$this->skillKeywords],
            'hackerNews' => [$this->skillKeywords],
            'devTo' => [$this->skillKeywords],
            'gitLab' => [$this->skillKeywords],
            'workAtAStartup' => [$this->skillKeywords],
            'dynamiteJobs' => [$this->skillKeywords],
            'moniepoint' => [$this->skillKeywords],
            'termii' => [$this->skillKeywords],
            'heliumHealth' => [$this->skillKeywords],
            'paystack' => [$this->skillKeywords],
        ];
    }

    public function run(string $mode = 'cli'): void
    {
        $this->log($mode, 'Starting job search…');

        $fetchers = [
            'fetchRemoteOK',
            'fetchArbeitnow',
            'fetchWorkingNomads',
            'fetchRemotive',
            'fetchHimalayas',
            'fetchWeWorkRemotely',
            'fetchJobicy',
            'fetchHackerNews',
            'fetchDevTo',
            'fetchGitLab',
            'fetchWorkAtAStartup',
            'fetchDynamiteJobs',
            'fetchMoniepoint',
            'fetchTermii',
            'fetchHeliumHealth',
            'fetchPaystack',
        ];

        // 1. Fetch from all sources
        $all = array_merge(
            ...array_map(fn($method) => $this->$method(), $fetchers)
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

    private function mergeJson(string $json): array
    {
        $parsed = json_decode($json, true);
        if (!is_array($parsed)) {
            return [];
        }

        $data = $parsed['data'] ?? $parsed;

        if (array_keys($data) === range(0, count($data) - 1)) {
            return $data;
        }

        return [$data];
    }

    // ─── Source fetchers ──────────────────────────────────────────────────────
    //[No signup]
    //RemoteOk
    private function fetchRemoteOK(): array
    {
        $jobs = [];
        $tags = implode(',', $this->sources['remoteOK']);
        $url  = "https://remoteok.com/api?tags={$tags}";
        $data = $this->httpGet($url, ['Referer: https://remoteok.com']);

        if (!$data) {
            return [];
        }

        $listings = $this->mergeJson($data);

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
        echo "Fetched " . count($listings) . " jobs from source\n";
        die();
    }

    //Arbeitnow
    private function fetchArbeitnow(): array
    {
        $jobs = [];
        $tags = implode(',', $this->skillKeywords);
        $categorys = implode(',', $this->categoryKeywords);
        $url = "https://www.arbeitnow.com/api/job-board-api?tags={$tags}&category={$categorys}";
        $data = $this->httpGet($url);

        if (!$data) {
            return [];
        }

        $listings = $this->mergeJson($data);

        foreach ($listings as $item) {
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
        $category = implode(',', $this->sources['workingNomads']);
        $data = $this->httpGet("https://www.workingnomads.com/api/exposed_jobs/?category={$category}");

        if (!$data) {
            return [];
        }

        $listings = $this->mergeJson($data);

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
        $category = implode(',', $this->sources['remotive']);
        $url = "https://remotive.com/api/remote-jobs?category={$category}";
        $data = $this->httpGet($url);
        if (!$data) return [];
        $listings = $this->mergeJson($data);
        foreach ($listings as $item) {
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
        $skills = implode(',', $this->sources['himalayas']);
        $url  = "https://himalayas.app/jobs/api?skills={$skills}&limit=100";
        $data = $this->httpGet($url);
        if (!$data) return [];
        $listings = $this->mergeJson($data);
        foreach ($listings as $item) {
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
        $skills = implode(',', $this->sources['jobicy']);
        $url  = "https://jobicy.com/api/v2/remote-jobs?count=50&tag={$skills}";
        $data = $this->httpGet($url);
        if (!$data) return [];
        $listings = $this->mergeJson($data);
        foreach ($listings as $item) {
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

        $keywords = array_map('strtolower', $this->sources['hackerNews']);

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
        $tags = array_map('strtolower', $this->sources['devTo']);
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
        $listings = $this->mergeJson($data);

        $keywords = array_map('strtolower', $this->sources['gitlab']);

        foreach ($listings as $item) {
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

    //Work at a startup (by Y Combinator)
    private function fetchWorkAtAStartup(): array
    {
        $jobs = [];

        // YC's Work at a Startup exposes a public jobs feed
        $url  = 'https://www.workatastartup.com/jobs.json';
        $data = $this->httpGet($url, [
            'Referer: https://www.workatastartup.com',
            'Accept: application/json',
        ]);
        if (!$data) return [];

        $listings = $this->mergeJson($data);

        $keywords = array_map('strtolower', $this->sources['workAtAStartup']);

        foreach ($listings as $item) {
            $haystack = strtolower(
                ($item['title'] ?? '') . ' ' .
                    ($item['description'] ?? '') . ' ' .
                    implode(' ', $item['skills'] ?? [])
            );

            $matched = false;
            $matchedTag = '';
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matched    = true;
                    $matchedTag = $kw;
                    break;
                }
            }
            if (!$matched) continue;

            $jobs[] = [
                'id'          => 'workatastartup_' . ($item['id'] ?? md5($item['url'] ?? '')),
                'title'       => $item['title'] ?? 'N/A',
                'company'     => $item['company']['name'] ?? 'N/A',
                'location'    => !empty($item['remote']) ? 'Remote' : ($item['location'] ?? 'N/A'),
                'url'         => !empty($item['url'])
                    ? $item['url']
                    : 'https://www.workatastartup.com/jobs/' . ($item['id'] ?? ''),
                'description' => strip_tags($item['description'] ?? ''),
                'tags'        => implode(', ', $item['skills'] ?? [$matchedTag]),
                'source'      => 'Work at a Startup',
                'posted_at'   => date('Y-m-d', strtotime($item['created_at'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    //Dynamite jobs
    private function fetchDynamiteJobs(): array
    {
        $jobs = [];

        $feeds = [
            'https://dynamitejobs.com/remote-jobs.rss',
            'https://dynamitejobs.com/category/software-development/remote-jobs.rss',
        ];

        $keywords = array_map('strtolower', $this->sources['dynamiteJobs']);

        foreach ($feeds as $url) {
            $xml = $this->httpGet($url);
            if (!$xml) continue;

            libxml_use_internal_errors(true);
            $feed = simplexml_load_string($xml);
            if (!$feed) continue;

            foreach ($feed->channel->item ?? [] as $item) {
                $title       = (string)($item->title ?? '');
                $description = strip_tags((string)($item->description ?? ''));
                $haystack    = strtolower($title . ' ' . $description);

                $matched    = false;
                $matchedTag = '';
                foreach ($keywords as $kw) {
                    if (str_contains($haystack, $kw)) {
                        $matched    = true;
                        $matchedTag = $kw;
                        break;
                    }
                }
                if (!$matched) continue;

                $link = (string)($item->link ?? '');
                $id   = md5($link ?: $title);

                // Try to extract company from title — Dynamite usually formats as "Title at Company"
                $company = 'N/A';
                if (preg_match('/\bat\s+(.+)$/i', $title, $m)) {
                    $company = trim($m[1]);
                }

                $jobs[] = [
                    'id'          => 'dynamite_' . $id,
                    'title'       => $title,
                    'company'     => $company,
                    'location'    => 'Remote',
                    'url'         => $link ?: '#',
                    'description' => substr($description, 0, 300),
                    'tags'        => $matchedTag,
                    'source'      => 'Dynamite Jobs',
                    'posted_at'   => date('Y-m-d', strtotime((string)($item->pubDate ?? 'now'))),
                ];
            }
        }
        return $jobs;
    }

    //Moniepoint (Greenhouse)
    private function fetchMoniepoint(): array
    {
        $jobs = [];
        $url  = 'https://job-boards.eu.greenhouse.io/moniepoint';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $listings = $this->mergeJson($data);

        $keywords = array_map('strtolower', $this->sources['moniepoint']);

        foreach ($listings as $item) {
            $haystack = strtolower(
                ($item['text'] ?? '') . ' ' .
                    ($item['categories']['team'] ?? '') . ' ' .
                    strip_tags($item['descriptionPlain'] ?? '')
            );
            $matched = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) continue;

            $jobs[] = [
                'id'          => 'moniepoint_' . ($item['id'] ?? md5($item['hostedUrl'] ?? '')),
                'title'       => $item['text'] ?? 'N/A',
                'company'     => 'Moniepoint',
                'location'    => $item['categories']['location'] ?? 'Nigeria / Remote',
                'url'         => $item['hostedUrl'] ?? 'https://moniepoint.com/careers',
                'description' => substr(strip_tags($item['descriptionPlain'] ?? ''), 0, 300),
                'tags'        => $item['categories']['team'] ?? 'fintech, nigeria',
                'source'      => 'Moniepoint',
                'posted_at'   => date('Y-m-d', ($item['createdAt'] ?? time()) / 1000),
            ];
        }
        return $jobs;
    }

    //Termii
    private function fetchTermii(): array
    {
        $jobs = [];
        $url  = 'https://termii.com/company/careers';
        $html = $this->httpGet($url);
        if (!$html) return [];

        // Load HTML into DOMDocument for parsing
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Target job listing elements
        $nodes = $xpath->query('//a[contains(@href, "career") or contains(@href, "job")]');

        $keywords = array_map('strtolower', $this->sources['termii']);

        $seen = [];
        foreach ($nodes as $node) {

            if (!$node instanceof DOMElement) continue;

            $title = trim($node->textContent);
            $href  = $node->getAttribute('href');
            if (empty($title) || strlen($title) < 5) continue;

            $haystack = strtolower($title);
            $matched  = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) continue;

            // Build full URL if relative
            if (!str_starts_with($href, 'http')) {
                $href = 'https://termii.com' . $href;
            }

            $id = md5($href . $title);
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            $jobs[] = [
                'id'          => 'termii_' . $id,
                'title'       => $title,
                'company'     => 'Termii',
                'location'    => 'Lagos, Nigeria / Remote',
                'url'         => $href ?: 'https://termii.com/company/careers',
                'description' => 'Role at Termii — messaging infrastructure company.',
                'tags'        => 'nigeria, communications, api',
                'source'      => 'Termii',
                'posted_at'   => date('Y-m-d'),
            ];
        }
        return $jobs;
    }

    //Helium Health
    private function fetchHeliumHealth(): array
    {
        $jobs = [];
        $url  = 'https://heliumhealth.com/company/careers';
        $html = $this->httpGet($url);
        if (!$html) return [];

        // Load HTML into DOMDocument for parsing
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Target job listing elements
        $nodes = $xpath->query('//a[contains(@href, "career") or contains(@href, "job")]');

        $keywords = array_map('strtolower', $this->sources['termii']);

        $seen = [];
        foreach ($nodes as $node) {

            if (!$node instanceof DOMElement) continue;

            $title = trim($node->textContent);
            $href  = $node->getAttribute('href');
            if (empty($title) || strlen($title) < 5) continue;

            $haystack = strtolower($title);
            $matched  = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) continue;

            // Build full URL if relative
            if (!str_starts_with($href, 'http')) {
                $href = 'https://heliumhealth.com' . $href;
            }

            $id = md5($href . $title);
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            $jobs[] = [
                'id'          => 'termii_' . $id,
                'title'       => $title,
                'company'     => 'Helium Health',
                'location'    => 'Lagos, Nigeria / Remote',
                'url'         => $href ?: 'https://heliumhealth.com/company/careers',
                'description' => 'Role at Helium Health — healthcare technology company.',
                'tags'        => 'nigeria, healthcare, technology',
                'source'      => 'Helium Health',
                'posted_at'   => date('Y-m-d'),
            ];
        }
        return $jobs;
    }

    //Paystack (Greenhouse)
    private function fetchPaystack(): array
    {
        $jobs = [];
        $url  = 'https://job-boards.greenhouse.io/paystack?error=true';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $listings = $this->mergeJson($data);

        $keywords = array_map('strtolower', $this->sources['paystack']);

        foreach ($listings as $item) {
            $haystack = strtolower(($item['title'] ?? '') . ' ' . strip_tags($item['content'] ?? ''));
            $matched  = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) continue;

            $jobs[] = [
                'id'          => 'paystack_' . ($item['id'] ?? md5($item['absolute_url'] ?? '')),
                'title'       => $item['title'] ?? 'N/A',
                'company'     => 'Paystack',
                'location'    => $item['location']['name'] ?? 'Lagos, Nigeria',
                'url'         => $item['absolute_url'] ?? 'https://paystack.com/careers',
                'description' => substr(strip_tags($item['content'] ?? ''), 0, 300),
                'tags'        => 'fintech, nigeria, payments',
                'source'      => 'Paystack',
                'posted_at'   => date('Y-m-d', strtotime($item['updated_at'] ?? 'now')),
            ];
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
