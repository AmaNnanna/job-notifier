<?php
/**
 * Test email sender — used by the dashboard "Test Email" button
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

$sample = [
    [
        'id'          => 'test_001',
        'title'       => 'Senior Laravel Developer',
        'company'     => 'Acme Corp',
        'location'    => 'Remote',
        'url'         => 'https://example.com/job/1',
        'description' => 'We are looking for a skilled Laravel developer with 3+ years experience building scalable web applications.',
        'tags'        => 'php, laravel, mysql, redis',
        'source'      => 'Remote OK',
        'posted_at'   => date('Y-m-d'),
    ],
    [
        'id'          => 'test_002',
        'title'       => 'Full Stack JavaScript Engineer',
        'company'     => 'TechStartup',
        'location'    => 'Remote / Europe',
        'url'         => 'https://example.com/job/2',
        'description' => 'Join our team as a full-stack JS engineer. You will work with React on the frontend and Node.js on the backend.',
        'tags'        => 'react, node.js, typescript, postgresql',
        'source'      => 'Arbeitnow',
        'posted_at'   => date('Y-m-d'),
    ],
    [
        'id'          => 'test_003',
        'title'       => 'Vue.js Frontend Developer',
        'company'     => 'DigitalAgency',
        'location'    => 'Worldwide',
        'url'         => 'https://example.com/job/3',
        'description' => 'We need a passionate Vue.js developer to help build our next-gen SaaS platform.',
        'tags'        => 'vue, nuxt, javascript, css',
        'source'      => 'Working Nomads',
        'posted_at'   => date('Y-m-d'),
    ],
];

$mailer = new JobMailer();
$result = $mailer->sendDailyDigest($sample);

header('Content-Type: application/json');
echo json_encode([
    'success' => $result,
    'error'   => $result ? null : 'Check SMTP config in config.php',
    'sent_to' => RECIPIENT_EMAIL,
]);
