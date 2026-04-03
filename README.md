# 🔍 Job Notifier Bot

A PHP-based job search system that scans multiple job boards daily and emails you matched
roles for **PHP · Laravel · JavaScript · Node.js · React · Vue · Angular**.

---

## 📁 File Structure

```
job-notifier/
├── index.php           ← Web dashboard (open in browser)
├── search_jobs.php     ← CLI script (run via cron)
├── search_jobs_web.php ← AJAX endpoint (called by dashboard)
├── mailer.php          ← PHPMailer email builder
├── job_store.php       ← Deduplication + persistence
├── test_email.php      ← Test your SMTP config
├── config.php          ← ⚠️  EDIT THIS FIRST
├── composer.json
└── data/               ← Auto-created
    ├── seen_jobs.json  ← Tracks sent job IDs
    ├── today_jobs.json ← Latest search results
    └── search.log      ← Run history
```

---

## 🚀 Setup

### 1. Install Dependencies

```bash
cd job-notifier/
composer install
```

### 2. Configure `config.php`

```php
define('RECIPIENT_EMAIL', 'you@example.com');
define('RECIPIENT_NAME',  'Your Name');

define('SMTP_USERNAME', 'yourname@gmail.com');
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx');  // Gmail App Password
```

**Getting a Gmail App Password:**
1. Enable 2-Factor Authentication on your Google account
2. Go to: myaccount.google.com → Security → App Passwords
3. Create a new app password named "Job Notifier"
4. Paste the 16-character code into `SMTP_PASSWORD`

### 3. Set Permissions

```bash
chmod 755 job-notifier/
mkdir -p job-notifier/data
chmod 755 job-notifier/data
```

### 4. Test Email

Open `test_email.php` in your browser or run:
```bash
php test_email.php
```

---

## ⏰ Cron Job (Daily 7 AM)

```bash
crontab -e
```

Add this line:
```
0 7 * * * /usr/bin/php /var/www/html/job-notifier/search_jobs.php >> /var/www/html/job-notifier/data/search.log 2>&1
```

**Find your PHP path:**
```bash
which php
```

---

## 🌐 Job Sources

| Source         | Type   | API Key Needed |
|----------------|--------|----------------|
| Remote OK      | Remote | No             |
| Arbeitnow      | Remote | No             |
| Working Nomads | Remote | No             |
| Adzuna         | Global | Yes (free)     |

**Optional — Adzuna (free tier):**
1. Register at https://developer.adzuna.com
2. Add your App ID + Key to `config.php`
3. Set `ADZUNA_COUNTRY` (gb, us, ng, au, ca, etc.)

---

## 🛠️ Customising Search Keywords

Edit the `$skillKeywords` array inside `search_jobs.php` and `search_jobs_web.php`:

```php
private array $skillKeywords = [
    'php', 'laravel', 'javascript', 'react', 'vue', 'angular',
    'node', 'nodejs', 'typescript', 'next.js', 'nuxt',
    // add more keywords here
];
```

---

## 📧 Email Preview

The daily digest email includes:
- Job title + direct apply link
- Company name + location
- Source badge (colour coded)
- Skill tags
- Short description excerpt

---

## 🔒 Security

- Keep `config.php` out of version control — add it to `.gitignore`
- Never commit your Gmail App Password
- Restrict web access to `search_jobs_web.php` and `test_email.php` using `.htaccess` if on a public server:

```apache
<Files "test_email.php">
    Require ip 127.0.0.1 YOUR.IP.HERE
</Files>
```
