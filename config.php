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

//Helium Health (Lever)
private function fetchHeliumHealth(): array
{
    $jobs = [];
    $url  = 'https://heliumhealth.com/careers';
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

*/