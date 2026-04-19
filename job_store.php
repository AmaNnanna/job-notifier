<?php

/**
 * Job Store — persists seen job IDs to avoid duplicate emails
 */

require_once __DIR__ . '/config.php';

function loadSeenJobIds(): array
{
    $file = SEEN_JOBS_FILE;
    if (!file_exists($file)) return [];

    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return [];

    // Purge entries older than JOB_HISTORY_DAYS
    $cutoff = strtotime('-' . JOB_HISTORY_DAYS . ' days');
    $fresh  = array_filter($data, fn($ts) => $ts >= $cutoff);

    return array_keys($fresh);
}

function saveJobsToStore(array $jobs): void
{
    $file = SEEN_JOBS_FILE;
    $dir  = dirname($file);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Load existing
    $existing = [];
    if (file_exists($file)) {
        $raw = json_decode(file_get_contents($file), true);
        if (is_array($raw)) $existing = $raw;
    }

    // Purge old
    $cutoff = strtotime('-' . JOB_HISTORY_DAYS . ' days');
    $existing = array_filter($existing, fn($ts) => $ts >= $cutoff);

    // Add new
    $now = time();
    foreach ($jobs as $job) {
        if (!empty($job['id'])) {
            $existing[$job['id']] = $now;
        }
    }

    file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT));
}

function countSeenJobIds(): int
{
    $file = SEEN_JOBS_FILE;
    if (!file_exists($file)) return 0;

    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return 0;

    // Respect the same purge window used everywhere else
    $cutoff = strtotime('-' . JOB_HISTORY_DAYS . ' days');
    return count(array_filter($data, fn($ts) => $ts >= $cutoff));
}
