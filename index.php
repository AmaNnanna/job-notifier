<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Notifier Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink: #0b0f1a;
      --ink-2: #1e293b;
      --ink-3: #334155;
      --muted: #64748b;
      --subtle: #94a3b8;
      --rule: #e2e8f0;
      --surface: #f8fafc;
      --white: #ffffff;
      --blue: #2563eb;
      --blue-soft: #eff6ff;
      --green: #059669;
      --green-soft: #ecfdf5;
      --amber: #d97706;
      --amber-soft: #fffbeb;
      --red: #dc2626;
      --red-soft: #fef2f2;
      --violet: #7c3aed;
      --violet-soft: #f5f3ff;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--surface);
      color: var(--ink);
      min-height: 100vh;
      line-height: 1.6;
    }

    /* ── SIDEBAR ──────────────────────────────────────────────── */
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0;
      width: 72px;
      background: var(--ink);
      display: flex; flex-direction: column; align-items: center;
      padding: 28px 0;
      z-index: 100;
      gap: 8px;
    }

    .sidebar-logo {
      width: 40px; height: 40px;
      background: var(--blue);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px;
      margin-bottom: 24px;
    }

    .nav-btn {
      width: 44px; height: 44px;
      border-radius: 12px;
      border: none;
      background: transparent;
      color: var(--subtle);
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
      transition: background 0.15s, color 0.15s;
      text-decoration: none;
    }

    .nav-btn:hover, .nav-btn.active {
      background: rgba(255,255,255,0.1);
      color: #fff;
    }

    /* ── MAIN LAYOUT ──────────────────────────────────────────── */
    .layout {
      margin-left: 72px;
      min-height: 100vh;
    }

    /* ── TOP BAR ──────────────────────────────────────────────── */
    .topbar {
      position: sticky; top: 0; z-index: 50;
      background: rgba(248,250,252,0.95);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--rule);
      padding: 0 40px;
      height: 64px;
      display: flex; align-items: center; justify-content: space-between;
    }

    .topbar-left {
      display: flex; align-items: center; gap: 16px;
    }

    .page-title {
      font-family: 'Playfair Display', serif;
      font-size: 20px;
      font-weight: 600;
      color: var(--ink);
    }

    .status-pill {
      display: flex; align-items: center; gap: 6px;
      background: var(--green-soft);
      color: var(--green);
      font-size: 12px; font-weight: 500;
      padding: 4px 12px; border-radius: 20px;
      letter-spacing: 0.3px;
    }

    .status-dot {
      width: 6px; height: 6px;
      background: var(--green);
      border-radius: 50%;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.3; }
    }

    .topbar-right { display: flex; align-items: center; gap: 12px; }

    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 8px 18px;
      border-radius: 10px;
      font-size: 14px; font-weight: 500;
      cursor: pointer; border: none;
      transition: all 0.15s;
      font-family: 'DM Sans', sans-serif;
      text-decoration: none;
    }

    .btn-primary {
      background: var(--ink);
      color: #fff;
    }
    .btn-primary:hover { background: var(--ink-2); transform: translateY(-1px); }

    .btn-outline {
      background: transparent;
      border: 1px solid var(--rule);
      color: var(--ink-3);
    }
    .btn-outline:hover { border-color: var(--ink-3); background: var(--white); }

    /* ── CONTENT ──────────────────────────────────────────────── */
    .content { padding: 40px; }

    /* ── STATS ROW ────────────────────────────────────────────── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 36px;
    }

    .stat-card {
      background: var(--white);
      border: 1px solid var(--rule);
      border-radius: 16px;
      padding: 24px;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0;
      height: 3px;
    }

    .stat-card.blue::before { background: var(--blue); }
    .stat-card.green::before { background: var(--green); }
    .stat-card.amber::before { background: var(--amber); }
    .stat-card.violet::before { background: var(--violet); }

    .stat-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
      margin-bottom: 16px;
    }

    .stat-card.blue .stat-icon { background: var(--blue-soft); }
    .stat-card.green .stat-icon { background: var(--green-soft); }
    .stat-card.amber .stat-icon { background: var(--amber-soft); }
    .stat-card.violet .stat-icon { background: var(--violet-soft); }

    .stat-value {
      font-family: 'Playfair Display', serif;
      font-size: 36px;
      font-weight: 700;
      line-height: 1;
      color: var(--ink);
    }

    .stat-label {
      font-size: 13px;
      color: var(--muted);
      margin-top: 4px;
    }

    /* ── GRID: JOBS + SIDEBAR PANEL ──────────────────────────── */
    .main-grid {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 24px;
      align-items: start;
    }

    /* ── JOBS PANEL ───────────────────────────────────────────── */
    .panel {
      background: var(--white);
      border: 1px solid var(--rule);
      border-radius: 16px;
      overflow: hidden;
    }

    .panel-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--rule);
      display: flex; align-items: center; justify-content: space-between;
    }

    .panel-title {
      font-family: 'Playfair Display', serif;
      font-size: 17px;
      font-weight: 600;
    }

    /* ── FILTER STRIP ─────────────────────────────────────────── */
    .filter-strip {
      padding: 14px 24px;
      border-bottom: 1px solid var(--rule);
      display: flex; align-items: center; gap: 10px;
      flex-wrap: wrap;
    }

    .filter-chip {
      padding: 5px 14px;
      border-radius: 20px;
      font-size: 12px; font-weight: 500;
      cursor: pointer;
      border: 1px solid var(--rule);
      background: transparent;
      color: var(--muted);
      transition: all 0.15s;
      font-family: 'DM Sans', sans-serif;
    }

    .filter-chip.active {
      background: var(--ink);
      color: #fff;
      border-color: var(--ink);
    }

    .filter-chip:hover:not(.active) {
      border-color: var(--ink-3);
      color: var(--ink-3);
    }

    .search-box {
      margin-left: auto;
      display: flex; align-items: center; gap: 8px;
      background: var(--surface);
      border: 1px solid var(--rule);
      border-radius: 10px;
      padding: 6px 14px;
    }

    .search-box input {
      border: none; background: transparent;
      font-family: 'DM Sans', sans-serif;
      font-size: 13px; color: var(--ink);
      outline: none; width: 180px;
    }

    .search-box input::placeholder { color: var(--subtle); }

    /* ── JOB LIST ─────────────────────────────────────────────── */
    .job-list { padding: 8px 0; }

    .job-item {
      padding: 18px 24px;
      border-bottom: 1px solid var(--rule);
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 12px;
      cursor: pointer;
      transition: background 0.12s;
      text-decoration: none;
      color: inherit;
      display: block;
    }

    .job-item:last-child { border-bottom: none; }
    .job-item:hover { background: var(--surface); }

    .job-item-inner {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 12px;
      align-items: start;
    }

    .job-title {
      font-size: 15px; font-weight: 600;
      color: var(--ink);
      line-height: 1.3;
      margin-bottom: 4px;
    }

    .job-meta {
      font-size: 13px; color: var(--muted);
      display: flex; align-items: center; gap: 12px;
      flex-wrap: wrap;
    }

    .job-meta span { display: flex; align-items: center; gap: 4px; }

    .job-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; }

    .tag {
      font-family: 'JetBrains Mono', monospace;
      font-size: 10px;
      background: var(--surface);
      border: 1px solid var(--rule);
      color: var(--ink-3);
      padding: 2px 8px; border-radius: 4px;
    }

    .source-badge {
      font-size: 10px; font-weight: 600;
      padding: 3px 10px; border-radius: 20px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .badge-remoteok   { background: #d1fae5; color: #065f46; }
    .badge-arbeitnow  { background: #ede9fe; color: #4c1d95; }
    .badge-nomads     { background: #fef3c7; color: #78350f; }
    .badge-adzuna     { background: #fee2e2; color: #7f1d1d; }

    /* ── SIDE PANEL ───────────────────────────────────────────── */
    .side-widget {
      background: var(--white);
      border: 1px solid var(--rule);
      border-radius: 16px;
      overflow: hidden;
      margin-bottom: 20px;
    }

    .side-widget-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--rule);
    }

    .side-widget-title {
      font-family: 'Playfair Display', serif;
      font-size: 15px; font-weight: 600;
    }

    .side-widget-body { padding: 16px 20px; }

    .skill-row {
      display: flex; align-items: center;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid var(--rule);
      font-size: 13px;
    }
    .skill-row:last-child { border-bottom: none; }

    .skill-name { display: flex; align-items: center; gap: 8px; font-weight: 500; }
    .skill-dot { width: 8px; height: 8px; border-radius: 50%; }

    .skill-count {
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      color: var(--muted);
    }

    .cron-line {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid var(--rule);
      font-size: 13px;
    }
    .cron-line:last-child { border-bottom: none; }
    .cron-step {
      width: 20px; height: 20px;
      background: var(--ink);
      color: #fff;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 700;
      flex-shrink: 0; margin-top: 1px;
    }
    .cron-text { color: var(--ink-3); line-height: 1.4; }
    .cron-code {
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      background: var(--surface);
      border: 1px solid var(--rule);
      padding: 6px 10px;
      border-radius: 6px;
      margin-top: 6px;
      color: var(--blue);
      word-break: break-all;
    }

    /* ── RUN BUTTON AREA ──────────────────────────────────────── */
    .run-area {
      padding: 16px 20px;
    }

    .btn-run {
      width: 100%;
      background: var(--ink);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 12px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px; font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }

    .btn-run:hover { background: #1e3a5f; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
    .btn-run:active { transform: translateY(0); }
    .btn-run:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    .run-output {
      margin-top: 12px;
      background: var(--ink);
      color: #4ade80;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      border-radius: 8px;
      padding: 10px 14px;
      max-height: 120px;
      overflow-y: auto;
      display: none;
      line-height: 1.8;
    }

    /* ── EMPTY STATE ──────────────────────────────────────────── */
    .empty-state {
      padding: 60px 24px;
      text-align: center;
      color: var(--muted);
    }

    .empty-icon { font-size: 40px; margin-bottom: 12px; }
    .empty-title { font-size: 16px; font-weight: 600; color: var(--ink-3); margin-bottom: 6px; }
    .empty-sub { font-size: 13px; }

    /* ── TOAST ────────────────────────────────────────────────── */
    .toast {
      position: fixed; bottom: 24px; right: 24px; z-index: 999;
      background: var(--ink);
      color: #fff;
      padding: 12px 20px;
      border-radius: 10px;
      font-size: 14px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
      transform: translateY(80px);
      opacity: 0;
      transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
    }

    .toast.show {
      transform: translateY(0);
      opacity: 1;
    }

    /* ── RESPONSIVE ───────────────────────────────────────────── */
    @media (max-width: 1100px) {
      .main-grid { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 640px) {
      .sidebar { width: 56px; }
      .layout { margin-left: 56px; }
      .content { padding: 20px; }
      .topbar { padding: 0 20px; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
    }

    /* ── SPINNER ──────────────────────────────────────────────── */
    .spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── ENTRY ANIM ───────────────────────────────────────────── */
    .fade-in {
      animation: fadeUp 0.4s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-logo">🔍</div>
    <a class="nav-btn active" title="Dashboard">📊</a>
    <!-- <a class="nav-btn" href="config.php" title="Settings">⚙️</a> -->
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
        <button class="btn btn-outline" onclick="testEmai()">✉️ Test Email</button>
        <button class="btn btn-primary" onclick="triggerSearch()">🔍 Run Now</button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="content">

      <!-- Stats -->
      <div class="stats-grid fade-in">
        <?php
        // Load stats from store
        $seenFile = __DIR__ . '/data/seen_jobs.json';
        $seen = file_exists($seenFile) ? json_decode(file_get_contents($seenFile), true) : [];
        $totalSeen = is_array($seen) ? count($seen) : 0;

        $logFile = __DIR__ . '/data/search.log';
        $lastRun = 'Never';
        if (file_exists($logFile)) {
            $lines = array_filter(array_map('trim', file($logFile)));
            $lastLine = end($lines);
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $lastLine ?? '', $m)) {
                $lastRun = date('M j, g:i A', strtotime($m[1]));
            }
        }

        $todayFile = __DIR__ . '/data/today_jobs.json';
        $todayJobs = file_exists($todayFile) ? json_decode(file_get_contents($todayFile), true) : [];
        $todayCount = is_array($todayJobs) ? count($todayJobs) : 0;
        ?>

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
            <button class="filter-chip" data-filter="nomads">Working Nomads</button>
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
                  $sourceClass = match(strtolower($job['source'] ?? '')) {
                      'remote ok' => 'badge-remoteok',
                      'arbeitnow' => 'badge-arbeitnow',
                      'working nomads' => 'badge-nomads',
                      'adzuna' => 'badge-adzuna',
                      default => 'badge-nomads'
                  };
                  $sourceSlug = str_replace([' ', '.'], '', strtolower($job['source'] ?? ''));
                  $tags = array_filter(array_slice(explode(',', $job['tags'] ?? ''), 0, 5));
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
                <span class="skill-count">keyword #1</span>
              </div>
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#f59e0b"></span>JavaScript</span>
                <span class="skill-count">keyword #2</span>
              </div>
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#10b981"></span>Node.js</span>
                <span class="skill-count">keyword #3</span>
              </div>
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#8b5cf6"></span>React / Vue</span>
                <span class="skill-count">keyword #4</span>
              </div>
              <div class="skill-row">
                <span class="skill-name"><span class="skill-dot" style="background:#ef4444"></span>Angular</span>
                <span class="skill-count">keyword #5</span>
              </div>
            </div>
          </div>

          <!-- Cron Setup Widget -->
          <!-- <div class="side-widget fade-in">
            <div class="side-widget-header">
              <div class="side-widget-title">⚙️ Cron Setup</div>
            </div>
            <div class="side-widget-body">
              <div class="cron-line">
                <div class="cron-step">1</div>
                <div class="cron-text">SSH into your server and open crontab</div>
              </div>
              <div class="cron-line">
                <div class="cron-step">2</div>
                <div class="cron-text">
                  Run <strong>crontab -e</strong> and add:
                  <div class="cron-code">0 7 * * * /usr/bin/php /var/www/job-notifier/search_jobs.php >> /var/www/job-notifier/data/search.log 2>&1</div>
                </div>
              </div>
              <div class="cron-line">
                <div class="cron-step">3</div>
                <div class="cron-text">Runs every day at <strong>7:00 AM</strong> server time</div>
              </div>
            </div>
          </div> -->

        </div>
      </div>
    </main>
  </div>

  <!-- Toast -->
  <div class="toast" id="toast"></div>

  <script>
    // ── Filter chips ──────────────────────────────────────────
    document.querySelectorAll('.filter-chip').forEach(chip => {
      chip.addEventListener('click', function() {
        document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        filterJobs();
      });
    });

    document.getElementById('job-search').addEventListener('input', filterJobs);

    function filterJobs() {
      const activeFilter = document.querySelector('.filter-chip.active')?.dataset.filter || 'all';
      const query = document.getElementById('job-search').value.toLowerCase();
      const items = document.querySelectorAll('.job-item');
      let visible = 0;

      items.forEach(item => {
        const matchFilter = activeFilter === 'all' || item.dataset.source === activeFilter;
        const matchQuery  = !query || item.innerText.toLowerCase().includes(query);
        const show = matchFilter && matchQuery;
        item.style.display = show ? 'block' : 'none';
        if (show) visible++;
      });

      document.getElementById('job-count-label').textContent = visible + ' shown';
    }

    // ── Run search ────────────────────────────────────────────
    function triggerSearch() {
      const btn = document.getElementById('run-btn');
      const out = document.getElementById('run-output');

      btn.disabled = true;
      btn.innerHTML = '<div class="spinner"></div> Searching...';
      out.style.display = 'block';
      out.innerHTML = '$ Starting job search...\n';

      fetch('search_jobs.php?run=1&output=json')
        .then(r => r.json())
        .then(data => {
          out.innerHTML += `$ Found ${data.found ?? 0} jobs\n`;
          out.innerHTML += `$ Matched: ${data.matched ?? 0}\n`;
          out.innerHTML += `$ New: ${data.new ?? 0}\n`;
          if ((data.new ?? 0) > 0) {
            out.innerHTML += `$ ✓ Email sent!\n`;
          } else {
            out.innerHTML += `$ ✓ No new jobs — no email sent\n`;
          }
          showToast(`✅ Done! ${data.new ?? 0} new jobs found`);
          if ((data.new ?? 0) > 0) setTimeout(() => location.reload(), 1500);
        })
        .catch(err => {
          out.innerHTML += `$ Error: ${err.message}\n`;
          showToast('❌ Search failed — check server logs');
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = '<span>▶</span> Run Search Now';
        });
    }

    // ── Test email ────────────────────────────────────────────
    function testEmail() {
      showToast('📧 Sending test email...');
      fetch('test_email.php')
        .then(r => r.json())
        .then(d => showToast(d.success ? '✅ Test email sent!' : '❌ Email failed: ' + d.error))
        .catch(() => showToast('❌ Could not reach test endpoint'));
    }

    // ── Toast ─────────────────────────────────────────────────
    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 3500);
    }
  </script>

</body>
</html>
