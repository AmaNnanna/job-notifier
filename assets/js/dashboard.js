// ── Filter chips ──────────────────────────────────────────────
document.querySelectorAll('.filter-chip').forEach(chip => {
    chip.addEventListener('click', function () {
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
        const matchQuery = !query || item.innerText.toLowerCase().includes(query);
        const show = matchFilter && matchQuery;
        item.style.display = show ? 'block' : 'none';
        if (show) visible++;
    });

    document.getElementById('job-count-label').textContent = visible + ' shown';
}

// ── Run search ────────────────────────────────────────────────
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
            out.innerHTML += `$ Emailed: ${data.emailed ? 'yes' : 'no'}\n`;
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

// ── Test email ────────────────────────────────────────────────
function testEmail() {                          // fixed: was testEmai()
    showToast('📧 Sending test email...');
    fetch('test_email.php')
        .then(r => r.json())
        .then(d => showToast(d.success ? '✅ Test email sent!' : '❌ Email failed: ' + d.error))
        .catch(() => showToast('❌ Could not reach test endpoint'));
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}