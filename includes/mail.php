<?php
// Sending email. config.php → MAIL_METHOD:
//   'log'  writes the email to storage/logs/mail-<date>.log instead of sending (for testing)
//   'mail' uses PHP's mail() function
//   'smtp' talks to an SMTP server (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS), with STARTTLS on port 587 or SSL on 465
//
//   send_mail('ada@example.com', 'Your booking', '<p>Hello</p>');

function send_mail($to, $subject, $html)
{
    $from = MAIL_FROM;
    $from_name = SITE_NAME;
    // Plain-text version: keep link addresses ("Label: https://…") before removing the tags
    $text = preg_replace('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2: $1', $html);
    $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</tr>', '</h1>', '</h2>'], "\n", $text)), ENT_QUOTES, 'UTF-8'));
    $boundary = 'b' . bin2hex(random_bytes(8));

    $headers = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from>\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if (MAIL_METHOD == 'log') {
        write_log('mail', "To: $to | Subject: $subject", ['text' => $text]);
        return true;
    }
    if (MAIL_METHOD == 'mail') {
        $ok = mail($to, $encoded_subject, $body, $headers);
        if (!$ok) write_log('mail', "mail() failed for $to");
        return $ok;
    }
    $ok = smtp_send($to, $encoded_subject, $headers, $body);
    if (!$ok) write_log('mail', "SMTP failed for $to");
    return $ok;
}

// A very small SMTP client: enough to send one message through a normal mail server
function smtp_send($to, $subject, $headers, $body)
{
    $host = SMTP_PORT == 465 ? 'ssl://' . SMTP_HOST : SMTP_HOST;
    $smtp = @fsockopen($host, SMTP_PORT, $errno, $errstr, 15);
    if (!$smtp) {
        write_log('mail', "Can't connect to SMTP server: $errstr");
        return false;
    }
    stream_set_timeout($smtp, 15);

    // Read the server's reply and check it starts with the expected code
    $expect = function ($code) use ($smtp) {
        $reply = '';
        while (($line = fgets($smtp, 515)) !== false) {
            $reply .= $line;
            if (isset($line[3]) && $line[3] == ' ') break;   // last line of the reply
        }
        if (substr($reply, 0, 3) != $code) {
            write_log('mail', "SMTP expected $code, got: " . trim($reply));
            return false;
        }
        return true;
    };
    $say = function ($line) use ($smtp) { fwrite($smtp, $line . "\r\n"); };

    $domain = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
    $ok = $expect('220');
    $say("EHLO $domain");
    $ok = $ok && $expect('250');
    if ($ok && SMTP_PORT != 465) {
        $say('STARTTLS');
        $ok = $expect('220') && stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $say("EHLO $domain");
        $ok = $ok && $expect('250');
    }
    if ($ok && SMTP_USER != '') {
        $say('AUTH LOGIN');
        $ok = $expect('334');
        $say(base64_encode(SMTP_USER));
        $ok = $ok && $expect('334');
        $say(base64_encode(SMTP_PASS));
        $ok = $ok && $expect('235');
    }
    if ($ok) { $say('MAIL FROM:<' . MAIL_FROM . '>'); $ok = $expect('250'); }
    if ($ok) { $say("RCPT TO:<$to>"); $ok = $expect('250'); }
    if ($ok) { $say('DATA'); $ok = $expect('354'); }
    if ($ok) {
        // Lines starting with a dot must be doubled (SMTP rule)
        $message = "To: <$to>\r\nSubject: $subject\r\nDate: " . date('r') . "\r\n" . $headers . "\r\n" . $body;
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($smtp, $message . "\r\n.\r\n");
        $ok = $expect('250');
    }
    $say('QUIT');
    fclose($smtp);
    return $ok;
}
