<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/job_store.php';

// ── Load stats ────────────────────────────────────────────────
$totalSeen  = countSeenJobIds();

$logFile = __DIR__ . '/data/search.log';
$lastRun = 'Never';
if (file_exists($logFile)) {
  $lines   = array_filter(array_map('trim', file($logFile)));
  $lastLine = end($lines);
  if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $lastLine ?? '', $m)) {
    $lastRun = date('M j, g:i A', strtotime($m[1]));
  }
}

$todayFile  = __DIR__ . '/data/today_jobs.json';
$todayJobs  = file_exists($todayFile) ? json_decode(file_get_contents($todayFile), true) : [];
$todayJobs  = is_array($todayJobs) ? $todayJobs : [];
$todayCount = count($todayJobs);

// ── Source → CSS slug map (covers all 16 sources) ─────────────
function sourceToCssClass(string $source): string
{
  return 'badge-' . preg_replace('/[^a-z0-9]/', '', strtolower($source));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Notifier Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>

  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-logo">🔍</div>
    <a class="nav-btn active" title="Dashboard">📊</a>
    <a class="nav-btn" href="search_jobs.php?run=1&output=browser" title="Run Search">▶️</a>
  </nav>

  <div class="layout">

    <!-- Top Bar -->
    <header class="topbar">
      <div class="topbar-left">
        <h1 class="page-title">Job Notifier</h1>
        <div class="status-pill">
          <div class="status-dot"></div>
          Active
        </div>
      </div>
      <div class="topbar-right">
        <button class="btn btn-outline" onclick="testEmail()">✉️ Test Email</button>
        <button class="btn btn-primary" onclick="triggerSearch()">🔍 Run Now</button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="content">

      <!-- Stats -->
      <div class="stats-grid fade-in">
        <div class="stat-card blue">
          <div class="stat-icon">💼</div>
          <div class="stat-value"><?= $todayCount ?></div>
          <div class="stat-label">Jobs found today</div>
        </div>
        <div class="stat-card green">
          <div class="stat-icon">📬</div>
          <div class="stat-value"><?= $totalSeen ?></div>
          <div class="stat-label">Total jobs tracked</div>
        </div>
        <div class="stat-card amber">
          <div class="stat-icon">🕐</div>
          <div class="stat-value" style="font-size:18px;padding-top:8px;"><?= $lastRun ?></div>
          <div class="stat-label">Last search run</div>
        </div>
        <div class="stat-card violet">
          <div class="stat-icon">⚡</div>
          <div class="stat-value">7 AM</div>
          <div class="stat-label">Daily cron schedule</div>
        </div>
      </div>

      <!-- Main grid -->
      <div class="main-grid">

        <!-- Jobs Panel -->
        <div class="panel fade-in" id="jobs-panel">
          <div class="panel-header">
            <span class="panel-title">Recent Job Matches</span>
            <span style="font-size:13px;color:var(--muted);" id="job-count-label">
              <?= $todayCount ?> from today
            </span>
          </div>

          <div class="filter-strip">
            <button class="filter-chip active" data-filter="all">All</button>
            <button class="filter-chip" data-filter="remoteok">Remote OK</button>
            <button class="filter-chip" data-filter="arbeitnow">Arbeitnow</button>
            <button class="filter-chip" data-filter="workingnomads">Working Nomads</button>
            <button class="filter-chip" data-filter="remotive">Remotive</button>
            <button class="filter-chip" data-filter="himalayas">Himalayas</button>
            <button class="filter-chip" data-filter="jobicy">Jobicy</button>
            <button class="filter-chip" data-filter="weworkremotely">We Work Remotely</button>
            <button class="filter-chip" data-filter="dynamitejobs">Dynamite Jobs</button>
            <button class="filter-chip" data-filter="hackernews">Hacker News</button>
            <button class="filter-chip" data-filter="devto">Dev.to</button>
            <button class="filter-chip" data-filter="gitlab">GitLab</button>
            <button class="filter-chip" data-filter="workatastartup">Work at a Startup</button>
            <button class="filter-chip" data-filter="moniepoint">Moniepoint</button>
            <button class="filter-chip" data-filter="paystack">Paystack</button>
            <button class="filter-chip" data-filter="termii">Termii</button>
            <button class="filter-chip" data-filter="heliumhealth">Helium Health</button>
            <button class="filter-chip" data-filter="adzuna">Adzuna</button>

            <div class="search-box">
              <span>🔍</span>
              <input type="text" id="job-search" placeholder="Search titles...">
            </div>
          </div>

          <div class="job-list" id="job-list">
            <?php if (!empty($todayJobs)): ?>
              <?php foreach ($todayJobs as $job): ?>
                <?php
                $sourceClass = sourceToCssClass($job['source'] ?? '');
                $sourceSlug  = preg_replace('/[^a-z0-9]/', '', strtolower($job['source'] ?? ''));
                $tags        = array_filter(array_slice(explode(',', $job['tags'] ?? ''), 0, 5));
                ?>
                <a href="<?= htmlspecialchars($job['url'] ?? '#') ?>" target="_blank"
                  class="job-item" data-source="<?= $sourceSlug ?>">
                  <div class="job-item-inner">
                    <div>
                      <div class="job-title"><?= htmlspecialchars($job['title'] ?? 'N/A') ?></div>
                      <div class="job-meta">
                        <span>🏢 <?= htmlspecialchars($job['company'] ?? '') ?></span>
                        <span>📍 <?= htmlspecialchars($job['location'] ?? '') ?></span>
                      </div>
                      <?php if (!empty($tags)): ?>
                        <div class="job-tags">
                          <?php foreach ($tags as $t): ?>
                            <span class="tag"><?= htmlspecialchars(trim($t)) ?></span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div>
                      <span class="source-badge <?= $sourceClass ?>"><?= htmlspecialchars($job['source'] ?? '') ?></span>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state">
                <div class="empty-icon">🔭</div>
                <div class="empty-title">No jobs yet</div>
                <div class="empty-sub">Click "Run Now" to search for matching jobs</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right Sidebar -->
        <div>

          <!-- Run Search Widget -->
          <div class="side-widget fade-in">
            <div class="side-widget-header">
              <div class="side-widget-title">Manual Search</div>
            </div>
            <div class="run-area">
              <button class="btn-run" id="run-btn" onclick="triggerSearch()">
                <span>▶</span> Run Search Now
              </button>
              <div class="run-output" id="run-output"></div>
            </div>
          </div>

          <!-- Skills Widget -->
          <div class="side-widget fade-in">
            <div class="side-widget-header">
              <div class="side-widget-title">Tracked Skills</div>
            </div>
            <div class="side-widget-body">
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#3b82f6"></span>PHP / Laravel</span>
              </div>
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#f59e0b"></span>JavaScript</span>
              </div>
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#10b981"></span>Node.js</span>
              </div>
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#8b5cf6"></span>React / Vue</span>
              </div>
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#ef4444"></span>Angular</span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </main>
  </div>

  <!-- Toast -->
  <div class="toast" id="toast"></div>

  <script src="assets/js/dashboard.js"></script>

</body>

</html>