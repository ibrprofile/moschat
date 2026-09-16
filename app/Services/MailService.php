<?php

declare(strict_types=1);

namespace MosChat\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

final class MailService
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $config = config('mail');
        $from = $config['from'] ?? [];

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';
            $mail->XMailer = 'MosChat Mailer';

            if (($config['driver'] ?? '') === 'smtp') {
                $mail->isSMTP();
                $mail->Host = (string) ($config['host'] ?? '127.0.0.1');
                $mail->Port = (int) ($config['port'] ?? 25);
                $mail->SMTPAuth = !empty($config['username']);
                $mail->Timeout = (int) ($config['timeout'] ?? 15);

                if (!empty($config['username'])) {
                    $mail->Username = (string) $config['username'];
                }
                if (!empty($config['password'])) {
                    $mail->Password = (string) $config['password'];
                }
                if (!empty($config['encryption'])) {
                    $enc = strtolower((string) $config['encryption']);
                    if ($enc === 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    } elseif ($enc === 'tls') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    }
                }
                if (empty($config['verify_peer'])) {
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true,
                        ],
                    ];
                }
                $mail->SMTPDebug = SMTP::DEBUG_OFF;
            } else {
                $mail->isMail();
            }

            $fromAddr = (string) ($from['address'] ?? 'noreply@moschat.online');
            $fromName = (string) ($from['name'] ?? 'MosChat');
            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            if ($textBody !== null) {
                $mail->AltBody = $textBody;
            } else {
                $mail->AltBody = strip_tags($htmlBody);
            }

            $mail->send();
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('[MailService] ' . $e->getMessage());
            }
            throw new \RuntimeException('Ошибка отправки письма', 0, $e);
        }
    }

    public static function wrap(string $contentHtml, string $title): string
    {
        $appName = (string) config('app.name', 'MosChat');
        $appUrl = rtrim((string) config('app.url', ''), '/');

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#09090b;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Inter,Roboto,sans-serif;color:#e4e4e7;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#09090b;">
  <tr><td style="padding:32px 16px;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:520px;margin:0 auto;">
      <tr><td style="padding:28px 24px;background:#18181b;border:1px solid rgba(255,255,255,0.08);border-radius:12px;">
        <div style="font-size:18px;font-weight:700;color:#ffffff;margin-bottom:18px;letter-spacing:-0.01em;">{$appName}</div>
        <div style="font-size:15px;line-height:1.55;color:#d4d4d8;">{$contentHtml}</div>
        <div style="margin-top:28px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.08);font-size:12px;color:#71717a;">
          Это автоматическое письмо. Пожалуйста, не отвечайте на него.
        </div>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }
}
