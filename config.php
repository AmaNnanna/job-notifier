<?php

/**
 * Configuration — edit all values before deploying
 */

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ─── Your details ─────────────────────────────────────────────────────────────
define('RECIPIENT_EMAIL', $_ENV['RECIPIENT_EMAIL']);          // Where to receive digests
define('RECIPIENT_NAME', $_ENV['RECIPIENT_NAME']);

// ─── Gmail SMTP (App Password — NOT your account password) ────────────────────
// Enable 2FA on your Google account, then:
// myaccount.google.com → Security → App Passwords → Create "Job Notifier"
define('SMTP_HOST', $_ENV['SMTP_HOST']);
define('SMTPSecure', $_ENV['SMTP_SECURE']);
define('SMTP_PORT', $_ENV['SMTP_PORT']);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME']);
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD']);
define('SMTP_FROM_EMAIL', $_ENV['SMTP_USERNAME']);
define('SMTP_FROM_NAME',  'Job Notifier Bot');

// ─── Adzuna API (optional — free tier at developer.adzuna.com) ────────────────
define('ADZUNA_APP_ID',  '');   // Leave blank to skip Adzuna
define('ADZUNA_APP_KEY', '');
define('ADZUNA_COUNTRY', 'gb'); // gb, us, au, ca, de, fr, nl, nz, sg, za, br, in, ru, at, be, mx, pl, sg

// ─── Storage paths ────────────────────────────────────────────────────────────
define('SEEN_JOBS_FILE', __DIR__ . '/data/seen_jobs.json');
define('LOG_FILE',       __DIR__ . '/data/search.log');

// ─── Search preferences ───────────────────────────────────────────────────────
define('MAX_JOBS_PER_EMAIL', 50);       // Cap jobs in a single digest
define('JOB_HISTORY_DAYS',   30);       // Purge seen-job records older than N days




/*
class JobSearcher
{
    private array $skillKeywords = [
        'php', 'laravel', 'react', // ... etc
    ];

    private array $sources = [
    'remoteOK' => [$this->skillKeywords],
    'arbeitNow' => [$this->categoryKeywords, $this->skillKeywords],
        'workingNomads' => [$this->categoryKeywords],
        'remotive'      => ['software-dev', 'writing'],
        'himalayas'     => ['php', 'javascript', 'react', 'nodejs', 'laravel'],
    ];

    private function fetchWorkingNomads(): array
{
    $jobs = [];
    foreach ($this->sources['workingnomads'] as $category) {
        $data = $this->httpGet("https://www.workingnomads.com/api/exposed_jobs/?category={$category}");
        // ... rest of the logic
    }
    return $jobs;
}

private function fetchRemotive(): array
{
    {
        $jobs = [];
        $tags = implode(',', $this->sources['RemoteOK']);
        $url  = "https://remoteok.com/api?tags={$tags}";
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
}

private function fetchHimalayas(): array
{
    $jobs = [];
    $skills = implode(',', $this->sources['himalayas']);
    $url    = "https://himalayas.app/jobs/api?skills={$skills}&limit=100";
    $data   = $this->httpGet($url);
    // ... rest of the logic
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

    //Well found
    private function fetchWellfound(): array
    {
        $jobs = [];
        if (empty(WELLFOUND_API_KEY)) return [];

        $skills = ['php', 'javascript', 'react', 'node-js', 'vue-js', 'laravel', 'angular'];

        foreach ($skills as $skill) {
            $url  = 'https://api.wellfound.com/v1/jobs?skill_id=' . $skill . '&remote=true&page=1';
            $data = $this->httpGet($url, [
                'Authorization: Bearer ' . WELLFOUND_API_KEY,
                'Accept: application/json',
            ]);
            if (!$data) continue;

            $parsed = json_decode($data, true);
            if (!is_array($parsed['jobs'] ?? null)) continue;

            foreach ($parsed['jobs'] as $item) {
                $jobs[] = [
                    'id'          => 'wellfound_' . ($item['id'] ?? md5($item['apply_url'] ?? '')),
                    'title'       => $item['title'] ?? 'N/A',
                    'company'     => $item['startup']['name'] ?? 'N/A',
                    'location'    => !empty($item['remote']) ? 'Remote' : ($item['locations'][0]['display_name'] ?? 'N/A'),
                    'url'         => $item['apply_url'] ?? ('https://wellfound.com/jobs/' . ($item['id'] ?? '')),
                    'description' => strip_tags($item['description'] ?? ''),
                    'tags'        => $skill . ', ' . implode(', ', array_column($item['tags'] ?? [], 'name')),
                    'source'      => 'Wellfound',
                    'posted_at'   => date('Y-m-d', strtotime($item['created_at'] ?? 'now')),
                ];
            }
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

        $parsed = json_decode($data, true);
        if (!is_array($parsed)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'js',
            'node',
            'nodejs',
            'react',
            'vue',
            'angular',
            'typescript',
            'next.js',
            'fullstack',
            'full stack',
            'frontend',
            'backend',
            'web developer'
        ];

        foreach ($parsed as $item) {
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

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'node',
            'nodejs',
            'react',
            'vue',
            'angular',
            'typescript',
            'next.js',
            'fullstack',
            'full stack',
            'frontend',
            'backend',
            'web developer'
        ];

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

    //Flutterwave (Greenhouse)
    private function fetchFlutterwave(): array
    {
        $jobs = [];
        $url  = 'https://boards-api.greenhouse.io/v1/boards/flutterwave/jobs?content=true';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!is_array($parsed['jobs'] ?? null)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer'
        ];

        foreach ($parsed['jobs'] as $item) {
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
                'id'          => 'flutterwave_' . ($item['id'] ?? md5($item['absolute_url'] ?? '')),
                'title'       => $item['title'] ?? 'N/A',
                'company'     => 'Flutterwave',
                'location'    => $item['location']['name'] ?? 'Nigeria / Remote',
                'url'         => $item['absolute_url'] ?? 'https://flutterwave.com/us/careers',
                'description' => substr(strip_tags($item['content'] ?? ''), 0, 300),
                'tags'        => 'fintech, nigeria',
                'source'      => 'Flutterwave',
                'posted_at'   => date('Y-m-d', strtotime($item['updated_at'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    //kuda (Greenhouse)
    private function fetchKuda(): array
    {
        $jobs = [];
        $url  = 'https://boards-api.greenhouse.io/v1/boards/kuda/jobs?content=true';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!is_array($parsed['jobs'] ?? null)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer'
        ];

        foreach ($parsed['jobs'] as $item) {
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
                'id'          => 'kuda_' . ($item['id'] ?? md5($item['absolute_url'] ?? '')),
                'title'       => $item['title'] ?? 'N/A',
                'company'     => 'Kuda Bank',
                'location'    => $item['location']['name'] ?? 'Nigeria / Remote',
                'url'         => $item['absolute_url'] ?? 'https://kuda.com/careers',
                'description' => substr(strip_tags($item['content'] ?? ''), 0, 300),
                'tags'        => 'fintech, nigeria, banking',
                'source'      => 'Kuda Bank',
                'posted_at'   => date('Y-m-d', strtotime($item['updated_at'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    //Moniepoint (Greenhouse)
    private function fetchMoniepoint(): array
    {
        $jobs = [];
        $url  = 'https://api.lever.co/v0/postings/moniepoint?mode=json';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!is_array($parsed)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer'
        ];

        foreach ($parsed as $item) {
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

    //Currywise (Lever)
    private function fetchCowrywise(): array
    {
        $jobs = [];
        $url  = 'https://api.lever.co/v0/postings/cowrywise?mode=json';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!is_array($parsed)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer'
        ];

        foreach ($parsed as $item) {
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
                'id'          => 'cowrywise_' . ($item['id'] ?? md5($item['hostedUrl'] ?? '')),
                'title'       => $item['text'] ?? 'N/A',
                'company'     => 'Cowrywise',
                'location'    => $item['categories']['location'] ?? 'Nigeria / Remote',
                'url'         => $item['hostedUrl'] ?? 'https://cowrywise.com/careers',
                'description' => substr(strip_tags($item['descriptionPlain'] ?? ''), 0, 300),
                'tags'        => $item['categories']['team'] ?? 'fintech, nigeria',
                'source'      => 'Cowrywise',
                'posted_at'   => date('Y-m-d', ($item['createdAt'] ?? time()) / 1000),
            ];
        }
        return $jobs;
    }

    //Paystack (Greenhouse)
    private function fetchPaystack(): array
    {
        $jobs = [];
        $url  = 'https://boards-api.greenhouse.io/v1/boards/paystack/jobs?content=true';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!is_array($parsed['jobs'] ?? null)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer',
            'engineer'
        ];

        foreach ($parsed['jobs'] as $item) {
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

    //Prospa(Greenhouse)
    private function fetchPaystack(): array
    {
        $jobs = [];
        $url  = 'https://boards-api.greenhouse.io/v1/boards/paystack/jobs?content=true';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!is_array($parsed['jobs'] ?? null)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer',
            'engineer'
        ];

        foreach ($parsed['jobs'] as $item) {
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

    //Piggyvest (Breezy HR)
    private function fetchPaystack(): array
    {
        $jobs = [];
        $url  = 'https://boards-api.greenhouse.io/v1/boards/paystack/jobs?content=true';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!is_array($parsed['jobs'] ?? null)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer',
            'engineer'
        ];

        foreach ($parsed['jobs'] as $item) {
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

    //Helium Health (Lever)
    private function fetchHeliumHealth(): array
    {
        $jobs = [];
        $url  = 'https://api.lever.co/v0/postings/heliumhealth?mode=json';
        $data = $this->httpGet($url);
        if (!$data) return [];

        $parsed = json_decode($data, true);
        if (!is_array($parsed)) return [];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer'
        ];

        foreach ($parsed as $item) {
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
                'id'          => 'heliumhealth_' . ($item['id'] ?? md5($item['hostedUrl'] ?? '')),
                'title'       => $item['text'] ?? 'N/A',
                'company'     => 'Helium Health',
                'location'    => $item['categories']['location'] ?? 'Nigeria / Remote',
                'url'         => $item['hostedUrl'] ?? 'https://heliumhealth.com/careers',
                'description' => substr(strip_tags($item['descriptionPlain'] ?? ''), 0, 300),
                'tags'        => $item['categories']['team'] ?? 'healthtech, nigeria',
                'source'      => 'Helium Health',
                'posted_at'   => date('Y-m-d', ($item['createdAt'] ?? time()) / 1000),
            ];
        }
        return $jobs;
    }

    //Termii
    private function fetchTermii(): array
    {
        $jobs = [];
        $url  = 'https://termii.com/careers';
        $html = $this->httpGet($url);
        if (!$html) return [];

        // Load HTML into DOMDocument for parsing
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Target job listing elements — adjust selector if their page structure changes
        $nodes = $xpath->query('//a[contains(@href, "career") or contains(@href, "job")]');

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer'
        ];

        $seen = [];
        foreach ($nodes as $node) {
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



    //IdentityPass
    private function fetchIdentityPass(): array
    {
        $jobs = [];
        $url  = 'https://identitypass.com/careers';
        $html = $this->httpGet($url);
        if (!$html) return [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer',
            'engineer'
        ];

        // Target all anchor tags pointing to job or career links
        $nodes = $xpath->query('//a[contains(@href, "job") or contains(@href, "career") or contains(@href, "position") or contains(@href, "role")]');

        $seen = [];
        foreach ($nodes as $node) {
            $title = trim($node->textContent);
            $href  = trim($node->getAttribute('href'));
            if (strlen($title) < 5) continue;

            $haystack = strtolower($title);
            $matched  = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) continue;

            if (!str_starts_with($href, 'http')) {
                $href = 'https://identitypass.com' . $href;
            }

            $id = md5($href . $title);
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            $jobs[] = [
                'id'          => 'identitypass_' . $id,
                'title'       => $title,
                'company'     => 'IdentityPass',
                'location'    => 'Lagos, Nigeria / Remote',
                'url'         => $href ?: 'https://identitypass.com/careers',
                'description' => 'Role at IdentityPass — identity verification infrastructure.',
                'tags'        => 'nigeria, identity, verification, api',
                'source'      => 'IdentityPass',
                'posted_at'   => date('Y-m-d'),
            ];
        }
        return $jobs;
    }

    //LifeBank
    private function fetchLifeBank(): array
    {
        $jobs = [];
        $url  = 'https://lifebank.ng/jobs/';
        $html = $this->httpGet($url);
        if (!$html) return [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer',
            'engineer',
            'tech'
        ];

        $nodes = $xpath->query('//a[contains(@href, "job") or contains(@href, "career") or contains(@href, "position")]');

        $seen = [];
        foreach ($nodes as $node) {
            $title = trim($node->textContent);
            $href  = trim($node->getAttribute('href'));
            if (strlen($title) < 5) continue;

            $haystack = strtolower($title);
            $matched  = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) continue;

            if (!str_starts_with($href, 'http')) {
                $href = 'https://lifebank.ng' . $href;
            }

            $id = md5($href . $title);
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            $jobs[] = [
                'id'          => 'lifebank_' . $id,
                'title'       => $title,
                'company'     => 'LifeBank',
                'location'    => 'Lagos, Nigeria',
                'url'         => $href ?: 'https://lifebank.ng/jobs/',
                'description' => 'Role at LifeBank — healthcare technology and logistics.',
                'tags'        => 'nigeria, healthtech, logistics',
                'source'      => 'LifeBank',
                'posted_at'   => date('Y-m-d'),
            ];
        }
        return $jobs;
    }

    //Tuteria
    private function fetchLifeBank(): array
    {
        $jobs = [];
        $url  = 'https://lifebank.ng/jobs/';
        $html = $this->httpGet($url);
        if (!$html) return [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer',
            'engineer',
            'tech'
        ];

        $nodes = $xpath->query('//a[contains(@href, "job") or contains(@href, "career") or contains(@href, "position")]');

        $seen = [];
        foreach ($nodes as $node) {
            $title = trim($node->textContent);
            $href  = trim($node->getAttribute('href'));
            if (strlen($title) < 5) continue;

            $haystack = strtolower($title);
            $matched  = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) continue;

            if (!str_starts_with($href, 'http')) {
                $href = 'https://lifebank.ng' . $href;
            }

            $id = md5($href . $title);
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            $jobs[] = [
                'id'          => 'lifebank_' . $id,
                'title'       => $title,
                'company'     => 'LifeBank',
                'location'    => 'Lagos, Nigeria',
                'url'         => $href ?: 'https://lifebank.ng/jobs/',
                'description' => 'Role at LifeBank — healthcare technology and logistics.',
                'tags'        => 'nigeria, healthtech, logistics',
                'source'      => 'LifeBank',
                'posted_at'   => date('Y-m-d'),
            ];
        }
        return $jobs;
    }

    //Afrilearn + Gradely (Direct Scrape — shared pattern)
    private function fetchAfrilearnAndGradely(): array
    {
        $jobs = [];

        $companies = [
            'Afrilearn' => [
                'url'  => 'https://afrilearn.com/careers',
                'base' => 'https://afrilearn.com',
                'tags' => 'nigeria, edtech, elearning',
                'desc' => 'Role at Afrilearn — African e-learning platform.',
            ],
            'Gradely' => [
                'url'  => 'https://gradely.co/careers',
                'base' => 'https://gradely.co',
                'tags' => 'nigeria, edtech, adaptive learning',
                'desc' => 'Role at Gradely — personalized learning platform.',
            ],
        ];

        $keywords = [
            'php',
            'laravel',
            'javascript',
            'react',
            'vue',
            'angular',
            'node',
            'typescript',
            'frontend',
            'backend',
            'fullstack',
            'full stack',
            'software engineer',
            'developer',
            'engineer'
        ];

        foreach ($companies as $name => $config) {
            $html = $this->httpGet($config['url']);
            if (!$html) continue;

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML($html);
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);

            $nodes = $xpath->query('//a[contains(@href, "job") or contains(@href, "career") or contains(@href, "position") or contains(@href, "apply") or contains(@href, "role")]');

            $seen = [];
            foreach ($nodes as $node) {
                $title = trim($node->textContent);
                $href  = trim($node->getAttribute('href'));
                if (strlen($title) < 5) continue;

                $haystack = strtolower($title);
                $matched  = false;
                foreach ($keywords as $kw) {
                    if (str_contains($haystack, $kw)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) continue;

                if (!str_starts_with($href, 'http')) {
                    $href = $config['base'] . $href;
                }

                $id = md5($href . $title);
                if (isset($seen[$id])) continue;
                $seen[$id] = true;

                $slug = strtolower(str_replace(' ', '', $name));
                $jobs[] = [
                    'id'          => $slug . '_' . $id,
                    'title'       => $title,
                    'company'     => $name,
                    'location'    => 'Lagos, Nigeria / Remote',
                    'url'         => $href ?: $config['url'],
                    'description' => $config['desc'],
                    'tags'        => $config['tags'],
                    'source'      => $name,
                    'posted_at'   => date('Y-m-d'),
                ];
            }
        }
        return $jobs;
    }

    //Okra
    private function fetchOkra(): array
    {
        $jobs   = [];
        $url    = 'https://boards-api.greenhouse.io/v1/boards/{okra-slug}/jobs?content=true';
        $data   = $this->httpGet($url);
        if (!$data) return [];
        $parsed = json_decode($data, true);
        foreach ($parsed['jobs'] ?? [] as $item) {
            $jobs[] = [
                'id'          => 'okra_' . $item['id'],
                'title'       => $item['title']                        ?? 'N/A',
                'company'     => 'Okra',
                'location'    => $item['location']['name']             ?? 'Remote',
                'url'         => $item['absolute_url']                 ?? '#',
                'description' => strip_tags($item['content']          ?? ''),
                'tags'        => $item['departments'][0]['name']       ?? '',
                'source'      => 'Okra via Greenhouse',
                'posted_at'   => date('Y-m-d', strtotime($item['updated_at'] ?? 'now')),
            ];
        }
        return $jobs;
    }

    private function fetchMono(): array
    {
        $jobs   = [];
        $url    = 'https://boards-api.greenhouse.io/v1/boards/{mono-slug}/jobs?content=true';
        $data   = $this->httpGet($url);
        if (!$data) return [];
        $parsed = json_decode($data, true);
        foreach ($parsed['jobs'] ?? [] as $item) {
            $jobs[] = [
                'id'          => 'mono_' . $item['id'],
                'title'       => $item['title']                        ?? 'N/A',
                'company'     => 'Mono',
                'location'    => $item['location']['name']             ?? 'Remote',
                'url'         => $item['absolute_url']                 ?? '#',
                'description' => strip_tags($item['content']          ?? ''),
                'tags'        => $item['departments'][0]['name']       ?? '',
                'source'      => 'Mono via Greenhouse',
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
*/