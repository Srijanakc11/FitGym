<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

$fitgymComposerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($fitgymComposerAutoload)) {
    require_once $fitgymComposerAutoload;
}

if (!function_exists('fitgym_mail_setting')) {
    function fitgym_mail_setting(string $key, string $default = ''): string
    {
        return function_exists('fitgym_setting') ? trim(fitgym_setting($key, $default)) : trim($default);
    }
}

if (!function_exists('fitgym_mail_is_available')) {
    function fitgym_mail_is_available(): bool
    {
        return class_exists(PHPMailer::class);
    }
}

if (!function_exists('fitgym_mail_config')) {
    function fitgym_mail_config(): array
    {
        $siteName = function_exists('fitgym_setting')
            ? fitgym_setting('site_name', 'FitGym')
            : 'FitGym';

        return [
            'host' => fitgym_mail_setting('mail_smtp_host', 'smtp.gmail.com'),
            'port' => (int)fitgym_mail_setting('mail_smtp_port', '587'),
            'secure' => strtolower(fitgym_mail_setting('mail_smtp_secure', 'tls')),
            'username' => fitgym_mail_setting('mail_smtp_username', 'srijanaseru@gmail.com'),
            'password' => fitgym_mail_setting('mail_smtp_password', ''),
            'from_email' => fitgym_mail_setting('mail_from_email', 'srijanaseru@gmail.com'),
            'from_name' => fitgym_mail_setting('mail_from_name', $siteName),
            'reply_to' => fitgym_mail_setting('mail_reply_to_email', 'srijanaseru@gmail.com'),
            'admin_copy' => fitgym_mail_setting('mail_admin_notification_email', 'srijanaseru@gmail.com'),
            'test_recipient' => fitgym_mail_setting('mail_test_recipient', ''),
        ];
    }
}

if (!function_exists('fitgym_mail_is_configured')) {
    function fitgym_mail_is_configured(): bool
    {
        if (!fitgym_mail_is_available()) {
            return false;
        }

        $config = fitgym_mail_config();
        return $config['host'] !== ''
            && $config['from_email'] !== ''
            && $config['username'] !== ''
            && $config['password'] !== '';
    }
}

if (!function_exists('fitgym_mail_bootstrap_booking_columns')) {
    function fitgym_mail_bootstrap_booking_columns(): void
    {
        static $bootstrapped = false;
        global $conn;

        if ($bootstrapped || !isset($conn) || !($conn instanceof mysqli) || !function_exists('fitgym_table_has_column')) {
            return;
        }

        $columnDefinitions = [
            'payment_success_mail_sent_at' => "ALTER TABLE bookings ADD COLUMN payment_success_mail_sent_at DATETIME NULL AFTER payment_verified_at",
            'expiry_reminder_mail_sent_at' => "ALTER TABLE bookings ADD COLUMN expiry_reminder_mail_sent_at DATETIME NULL AFTER payment_success_mail_sent_at",
        ];

        foreach ($columnDefinitions as $column => $sql) {
            if (!fitgym_table_has_column('bookings', $column)) {
                $conn->query($sql);
            }
        }

        if (function_exists('fitgym_reset_table_column_cache')) {
            fitgym_reset_table_column_cache();
        }

        $bootstrapped = true;
    }
}

if (!function_exists('fitgym_mail_format_date')) {
    function fitgym_mail_format_date(?string $value, string $fallback = '-'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $fallback;
        }

        return date('F j, Y', $timestamp);
    }
}

if (!function_exists('fitgym_booking_expiry_date_value')) {
    function fitgym_booking_expiry_date_value(?string $preferredDate): ?string
    {
        if ($preferredDate === null || trim($preferredDate) === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', trim($preferredDate));
        if (!$date) {
            $timestamp = strtotime($preferredDate);
            if ($timestamp === false) {
                return null;
            }
            $date = (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        $expiryDays = function_exists('fitgym_booking_expiry_days') ? max(1, fitgym_booking_expiry_days()) : 30;
        return $date->modify('+' . $expiryDays . ' days')->format('Y-m-d');
    }
}

if (!function_exists('fitgym_booking_notification_recipients')) {
    function fitgym_booking_notification_recipients(array $booking): array
    {
        $config = fitgym_mail_config();
        $bookingEmail = trim((string)($booking['email'] ?? ''));
        $recipientMap = [];

        $overrideRecipient = trim((string)$config['test_recipient']);
        if ($overrideRecipient !== '' && filter_var($overrideRecipient, FILTER_VALIDATE_EMAIL)) {
            $recipientMap[strtolower($overrideRecipient)] = $overrideRecipient;
        } elseif ($bookingEmail !== '' && filter_var($bookingEmail, FILTER_VALIDATE_EMAIL)) {
            $recipientMap[strtolower($bookingEmail)] = $bookingEmail;
        }

        $bccMap = [];
        $adminCopy = trim((string)$config['admin_copy']);
        if (
            $adminCopy !== ''
            && filter_var($adminCopy, FILTER_VALIDATE_EMAIL)
            && !isset($recipientMap[strtolower($adminCopy)])
        ) {
            $bccMap[strtolower($adminCopy)] = $adminCopy;
        }

        return [
            'to' => array_values($recipientMap),
            'bcc' => array_values($bccMap),
            'override_active' => $overrideRecipient !== '' && isset($recipientMap[strtolower($overrideRecipient)]),
            'override_recipient' => $overrideRecipient,
        ];
    }
}

if (!function_exists('fitgym_send_mail')) {
    function fitgym_send_mail(array $payload): array
    {
        if (!fitgym_mail_is_available()) {
            return ['ok' => false, 'error' => 'PHPMailer is not available.'];
        }

        if (!fitgym_mail_is_configured()) {
            return ['ok' => false, 'error' => 'SMTP mail settings are incomplete. Add the Gmail app password to finish setup.'];
        }

        $config = fitgym_mail_config();
        $to = array_values(array_filter((array)($payload['to'] ?? []), static fn($value): bool => is_string($value) && trim($value) !== ''));
        if ($to === []) {
            return ['ok' => false, 'error' => 'No valid email recipient was supplied.'];
        }

        $bcc = array_values(array_filter((array)($payload['bcc'] ?? []), static fn($value): bool => is_string($value) && trim($value) !== ''));
        $subject = trim((string)($payload['subject'] ?? 'FitGym Notification'));
        $html = (string)($payload['html'] ?? '');
        $text = (string)($payload['text'] ?? strip_tags($html));

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->Port = max(1, (int)$config['port']);
            $mail->CharSet = 'UTF-8';

            if ($config['secure'] === 'ssl' || $config['secure'] === 'smtps') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom($config['from_email'], $config['from_name']);
            if ($config['reply_to'] !== '' && filter_var($config['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($config['reply_to'], $config['from_name']);
            }

            foreach ($to as $address) {
                if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($address);
                }
            }

            foreach ($bcc as $address) {
                if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($address);
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html !== '' ? $html : nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
            $mail->AltBody = $text !== '' ? $text : trim(strip_tags($mail->Body));
            $mail->send();

            return ['ok' => true, 'error' => ''];
        } catch (PHPMailerException $exception) {
            error_log('FitGym mailer error: ' . $exception->getMessage());
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }
}

if (!function_exists('fitgym_mail_wrap_html')) {
    function fitgym_mail_wrap_html(string $title, string $intro, string $contentHtml): string
    {
        $siteName = function_exists('fitgym_setting') ? fitgym_setting('site_name', 'FitGym') : 'FitGym';
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#fff7f0;font-family:Arial,sans-serif;color:#1c1c1c;">'
            . '<div style="max-width:620px;margin:0 auto;padding:28px 20px;">'
            . '<div style="background:#111;color:#fff;padding:18px 22px;border-radius:16px 16px 0 0;">'
            . '<h1 style="margin:0;font-size:22px;">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '</div>'
            . '<div style="background:#ffffff;padding:26px 22px;border-radius:0 0 16px 16px;border:1px solid #f0d7c4;border-top:none;">'
            . '<h2 style="margin:0 0 10px;font-size:24px;color:#ff6c1a;">' . $escapedTitle . '</h2>'
            . '<p style="margin:0 0 18px;line-height:1.6;color:#56453c;">' . $escapedIntro . '</p>'
            . $contentHtml
            . '</div></div></body></html>';
    }
}

if (!function_exists('fitgym_send_booking_payment_success_email')) {
    function fitgym_send_booking_payment_success_email(array $booking): bool
    {
        global $conn;

        fitgym_mail_bootstrap_booking_columns();

        if (!fitgym_mail_is_configured()) {
            return false;
        }

        if (!empty($booking['payment_success_mail_sent_at'])) {
            return true;
        }

        $recipients = fitgym_booking_notification_recipients($booking);
        if ($recipients['to'] === []) {
            return false;
        }

        $className = trim((string)($booking['class_name'] ?? 'Your class'));
        $preferredDate = fitgym_mail_format_date((string)($booking['preferred_date'] ?? ''));
        $expiryDate = fitgym_mail_format_date(fitgym_booking_expiry_date_value((string)($booking['preferred_date'] ?? '')));
        $timeSlot = trim((string)($booking['time_slot'] ?? ''));
        $trainerName = trim((string)($booking['trainer_name'] ?? 'TBA'));
        $amount = function_exists('fitgym_price_label_from_rupees')
            ? fitgym_price_label_from_rupees((int)round(((int)($booking['payment_amount_paisa'] ?? 0)) / 100))
            : 'NPR ' . number_format((int)round(((int)($booking['payment_amount_paisa'] ?? 0)) / 100));
        $profileUrl = function_exists('fitgym_absolute_url')
            ? fitgym_absolute_url('/php/client/dashboard.php')
            : '/php/client/dashboard.php';

        $html = fitgym_mail_wrap_html(
            'Payment received',
            'Your Khalti payment was verified successfully and your booking is confirmed.',
            '<p style="margin:0 0 16px;line-height:1.7;">'
                . '<strong>Class:</strong> ' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Date:</strong> ' . htmlspecialchars($preferredDate, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Time:</strong> ' . htmlspecialchars($timeSlot, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Trainer:</strong> ' . htmlspecialchars($trainerName, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Amount:</strong> ' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Booking expiry date:</strong> ' . htmlspecialchars($expiryDate, ENT_QUOTES, 'UTF-8')
            . '</p>'
            . '<p style="margin:0 0 18px;"><a href="' . htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#ff6c1a;color:#fff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:700;">Open My Profile</a></p>'
            . '<p style="margin:0;color:#6d5c52;line-height:1.6;">Keep this email for your records. Your reservation will remain visible in your profile until the expiry date shown above.</p>'
        );

        $text = "Your Khalti payment was verified successfully.\n\n"
            . "Class: {$className}\n"
            . "Date: {$preferredDate}\n"
            . "Time: {$timeSlot}\n"
            . "Trainer: {$trainerName}\n"
            . "Amount: {$amount}\n"
            . "Booking expiry date: {$expiryDate}\n\n"
            . "Open your profile: {$profileUrl}";

        $sendResult = fitgym_send_mail([
            'to' => $recipients['to'],
            'bcc' => $recipients['bcc'],
            'subject' => 'FitGym payment confirmed for ' . $className,
            'html' => $html,
            'text' => $text,
        ]);

        if (!$sendResult['ok']) {
            return false;
        }

        if ($conn instanceof mysqli && isset($booking['id'])) {
            $sentAt = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE bookings SET payment_success_mail_sent_at = ? WHERE id = ?");
            if ($stmt) {
                $bookingId = (int)$booking['id'];
                $stmt->bind_param('si', $sentAt, $bookingId);
                $stmt->execute();
                $stmt->close();
            }
        }

        return true;
    }
}

if (!function_exists('fitgym_send_booking_expiry_reminder_email')) {
    function fitgym_send_booking_expiry_reminder_email(array $booking): bool
    {
        global $conn;

        fitgym_mail_bootstrap_booking_columns();

        if (!fitgym_mail_is_configured()) {
            return false;
        }

        if (!empty($booking['expiry_reminder_mail_sent_at'])) {
            return true;
        }

        $recipients = fitgym_booking_notification_recipients($booking);
        if ($recipients['to'] === []) {
            return false;
        }

        $className = trim((string)($booking['class_name'] ?? 'your class'));
        $expiryDateValue = fitgym_booking_expiry_date_value((string)($booking['preferred_date'] ?? ''));
        $expiryDate = fitgym_mail_format_date($expiryDateValue);
        $preferredDate = fitgym_mail_format_date((string)($booking['preferred_date'] ?? ''));
        $renewUrl = function_exists('fitgym_absolute_url')
            ? fitgym_absolute_url('/php/book_class.php?class=' . rawurlencode((string)($booking['class_slug'] ?? '')))
            : '/php/book_class.php';
        $classesUrl = function_exists('fitgym_absolute_url')
            ? fitgym_absolute_url('/php/classes.php')
            : '/php/classes.php';

        $html = fitgym_mail_wrap_html(
            'Your booking is close to expiry',
            'This is a reminder sent 2 days before your FitGym booking archive date.',
            '<p style="margin:0 0 16px;line-height:1.7;">'
                . 'Your booking for <strong>' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '</strong> from '
                . htmlspecialchars($preferredDate, ENT_QUOTES, 'UTF-8')
                . ' will expire on <strong>' . htmlspecialchars($expiryDate, ENT_QUOTES, 'UTF-8') . '</strong>.'
            . '</p>'
            . '<p style="margin:0 0 16px;line-height:1.7;color:#56453c;">If you want to continue this routine, renew now by booking your next session before the archive date.</p>'
            . '<p style="margin:0 0 10px;"><a href="' . htmlspecialchars($renewUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#ff6c1a;color:#fff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:700;">Renew This Class</a></p>'
            . '<p style="margin:0;"><a href="' . htmlspecialchars($classesUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#ff6c1a;font-weight:700;text-decoration:none;">Browse all classes</a></p>'
        );

        $text = "Reminder: your FitGym booking is close to expiry.\n\n"
            . "Class: {$className}\n"
            . "Booked class date: {$preferredDate}\n"
            . "Expiry date: {$expiryDate}\n\n"
            . "Renew this class: {$renewUrl}\n"
            . "Browse all classes: {$classesUrl}";

        $sendResult = fitgym_send_mail([
            'to' => $recipients['to'],
            'bcc' => $recipients['bcc'],
            'subject' => 'FitGym reminder: renew before ' . $expiryDate,
            'html' => $html,
            'text' => $text,
        ]);

        if (!$sendResult['ok']) {
            return false;
        }

        if ($conn instanceof mysqli && isset($booking['id'])) {
            $sentAt = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE bookings SET expiry_reminder_mail_sent_at = ? WHERE id = ?");
            if ($stmt) {
                $bookingId = (int)$booking['id'];
                $stmt->bind_param('si', $sentAt, $bookingId);
                $stmt->execute();
                $stmt->close();
            }
        }

        return true;
    }
}

if (!function_exists('fitgym_process_booking_email_notifications')) {
    function fitgym_process_booking_email_notifications(): void
    {
        static $processed = false;
        global $conn;

        if ($processed || !isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        $processed = true;
        fitgym_mail_bootstrap_booking_columns();

        if (!fitgym_mail_is_configured()) {
            return;
        }

        $expiryDays = function_exists('fitgym_booking_expiry_days') ? max(1, fitgym_booking_expiry_days()) : 30;
        $reminderLeadDays = 2;
        $reminderWindowStart = max(0, $expiryDays - $reminderLeadDays);

        $sql = "SELECT *
                FROM bookings
                WHERE COALESCE(status, 'Pending') NOT IN ('Cancelled', 'Expired')
                  AND email IS NOT NULL
                  AND email <> ''
                  AND COALESCE(expiry_reminder_mail_sent_at, '') = ''
                  AND DATE_ADD(preferred_date, INTERVAL {$reminderWindowStart} DAY) <= CURDATE()
                  AND DATE_ADD(preferred_date, INTERVAL {$expiryDays} DAY) >= CURDATE()
                ORDER BY preferred_date ASC
                LIMIT 20";
        $query = $conn->query($sql);
        if (!$query) {
            return;
        }

        while ($booking = $query->fetch_assoc()) {
            fitgym_send_booking_expiry_reminder_email($booking);
        }
        $query->free();
    }
}
