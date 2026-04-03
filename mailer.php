<?php
/**
 * Job Mailer — sends the daily digest via PHPMailer + Gmail SMTP
 * Install PHPMailer: composer require phpmailer/phpmailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

class JobMailer {

    public function sendDailyDigest(array $jobs): bool {
        $jobs = array_slice($jobs, 0, MAX_JOBS_PER_EMAIL);

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress(RECIPIENT_EMAIL, RECIPIENT_NAME);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'There are ' . count($jobs) . ' New Job Matches — ' . date('D, M j Y');
            $mail->Body    = $this->buildHtmlEmail($jobs);
            $mail->AltBody = $this->buildPlainText($jobs);

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("[JobMailer] Failed: {$mail->ErrorInfo}");
            return false;
        }
    }

    private function buildHtmlEmail(array $jobs): string {
        $jobCards = '';
        $sourceColors = [
            'Remote OK'      => '#10b981',
            'Arbeitnow'      => '#6366f1',
            'Working Nomads' => '#f59e0b',
            'Adzuna'         => '#ef4444',
        ];

        foreach ($jobs as $job) {
            $color   = $sourceColors[$job['source']] ?? '#64748b';
            $title   = htmlspecialchars($job['title']);
            $company = htmlspecialchars($job['company']);
            $location = htmlspecialchars($job['location']);
            $source  = htmlspecialchars($job['source']);
            $tags    = htmlspecialchars($job['tags'] ?? '');
            $url     = htmlspecialchars($job['url']);
            $desc    = htmlspecialchars(substr($job['description'] ?? '', 0, 200));
            $desc    = $desc ? $desc . '...' : '';
            $date    = htmlspecialchars($job['posted_at'] ?? '');

            $tagPills = '';
            if ($tags) {
                foreach (explode(',', $tags) as $tag) {
                    $t = trim($tag);
                    if ($t) {
                        $tagPills .= "<span style='display:inline-block;background:#f1f5f9;color:#475569;font-size:11px;padding:2px 8px;border-radius:20px;margin:2px 2px 0 0;font-family:monospace;'>{$t}</span>";
                    }
                }
            }

            $jobCards .= "
            <tr>
              <td style='padding:0 0 16px 0;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
                  <tr>
                    <td style='padding:20px 24px;border-left:4px solid {$color};'>
                      <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                          <td>
                            <a href='{$url}' style='font-size:17px;font-weight:700;color:#0f172a;text-decoration:none;line-height:1.3;font-family:Georgia,serif;'>{$title}</a>
                          </td>
                          <td align='right' valign='top'>
                            <span style='background:{$color};color:#fff;font-size:10px;font-weight:600;padding:3px 9px;border-radius:20px;white-space:nowrap;font-family:sans-serif;letter-spacing:0.5px;text-transform:uppercase;'>{$source}</span>
                          </td>
                        </tr>
                        <tr>
                          <td colspan='2' style='padding-top:6px;'>
                            <span style='color:#475569;font-size:14px;font-family:sans-serif;'>🏢 {$company}</span>
                            &nbsp;&nbsp;
                            <span style='color:#94a3b8;font-size:13px;font-family:sans-serif;'>📍 {$location}</span>
                            " . ($date ? "&nbsp;&nbsp;<span style='color:#94a3b8;font-size:12px;font-family:sans-serif;'>🗓 {$date}</span>" : "") . "
                          </td>
                        </tr>
                        " . ($desc ? "<tr><td colspan='2' style='padding-top:10px;color:#64748b;font-size:13px;line-height:1.6;font-family:sans-serif;'>{$desc}</td></tr>" : "") . "
                        " . ($tagPills ? "<tr><td colspan='2' style='padding-top:10px;'>{$tagPills}</td></tr>" : "") . "
                        <tr>
                          <td colspan='2' style='padding-top:14px;'>
                            <a href='{$url}' style='display:inline-block;background:#0f172a;color:#fff;text-decoration:none;font-size:13px;font-weight:600;padding:8px 18px;border-radius:8px;font-family:sans-serif;letter-spacing:0.3px;'>View Job →</a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>";
        }

        $count = count($jobs);
        $date  = date('l, F j, Y');

        return "<!DOCTYPE html>
<html>
<head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f8fafc;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f8fafc;'>
    <tr>
      <td align='center' style='padding:40px 20px;'>
        <table width='600' cellpadding='0' cellspacing='0' style='max-width:600px;width:100%;'>

          <!-- Header -->
          <tr>
            <td style='background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:16px;padding:40px 40px 36px;text-align:center;'>
              <p style='margin:0 0 8px;font-size:12px;color:#94a3b8;letter-spacing:3px;text-transform:uppercase;font-family:sans-serif;font-weight:600;'>Daily Digest</p>
              <h1 style='margin:0 0 6px;font-size:32px;color:#fff;font-family:Georgia,serif;font-weight:400;'>Your Job Matches</h1>
              <p style='margin:0;font-size:15px;color:#93c5fd;font-family:sans-serif;'>{$date}</p>
              <div style='margin:24px auto 0;display:inline-block;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:50px;padding:10px 28px;'>
                <span style='font-size:28px;font-weight:700;color:#fff;font-family:Georgia,serif;'>{$count}</span>
                <span style='font-size:14px;color:#bfdbfe;font-family:sans-serif;margin-left:6px;'>new openings</span>
              </div>
              <p style='margin:16px 0 0;font-size:13px;color:#7dd3fc;font-family:sans-serif;'>PHP · Laravel · JavaScript · Node.js · React · Vue · Angular</p>
            </td>
          </tr>

          <tr><td style='height:24px;'></td></tr>

          <!-- Job Cards -->
          <tr>
            <td>
              <table width='100%' cellpadding='0' cellspacing='0'>
                {$jobCards}
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style='padding:24px;text-align:center;'>
              <p style='margin:0;font-size:12px;color:#94a3b8;font-family:sans-serif;line-height:1.6;'>
                Sent by your Job Notifier Bot · Runs daily at 7:00 AM<br>
                Sources: Remote OK · Arbeitnow · Working Nomads · Adzuna
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>";
    }

    private function buildPlainText(array $jobs): string {
        $lines = ["YOUR DAILY JOB DIGEST — " . date('D M j Y'), str_repeat('=', 50), ''];
        foreach ($jobs as $i => $job) {
            $lines[] = ($i + 1) . ". {$job['title']}";
            $lines[] = "   Company : {$job['company']}";
            $lines[] = "   Location: {$job['location']}";
            $lines[] = "   Source  : {$job['source']}";
            $lines[] = "   URL     : {$job['url']}";
            $lines[] = '';
        }
        return implode("\n", $lines);
    }
}
