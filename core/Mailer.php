<?php

declare(strict_types=1);

namespace Elementary;

class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        require_once ELEMENTARY_ROOT . '/vendor/phpmailer/PHPMailer.php';
        require_once ELEMENTARY_ROOT . '/vendor/phpmailer/SMTP.php';
        require_once ELEMENTARY_ROOT . '/vendor/phpmailer/Exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $host = Config::get('smtp_host', '');
            if ($host) {
                $mail->isSMTP();
                $mail->Host       = $host;
                $mail->Port       = (int) Config::get('smtp_port', 587);
                $mail->SMTPAuth   = true;
                $mail->Username   = Config::get('smtp_user', '');
                $mail->Password   = Config::get('smtp_pass', '');
                $mail->SMTPSecure = Config::get('smtp_port', 587) == 465 ? 'ssl' : 'tls';
            }

            $fromEmail = Config::get('smtp_from_email', '') ?: 'noreply@' . parse_url(Config::get('site_url', ''), PHP_URL_HOST);
            $fromName  = Config::get('smtp_from_name', '') ?: Config::get('app_name', 'Elementary');

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            return $mail->send();
        } catch (\Throwable $e) {
            if (Config::get('debug_mode', false)) {
                throw $e;
            }
            return false;
        }
    }
}
