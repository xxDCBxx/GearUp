<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gearup');

define('SITE_NAME', 'GEARUP!');
define('BACKGROUND_INTERVAL', 5);

// SMTP email settings for password reset email delivery.
// Replace these values with a working SMTP provider and credentials.
define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_USER', 'gearupwebsystem@gmail.com');
define('EMAIL_SMTP_PASS', 'ucdf lbtd kbbl wmuj');
define('EMAIL_SMTP_SECURE', 'tls'); // use 'tls' or 'ssl'
define('EMAIL_SMTP_FROM', 'noreply@gearup.com');
define('EMAIL_SMTP_FROM_NAME', 'GearUp');

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

function start_safe_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

function send_smtp_email($to, $subject, $message, $fromEmail = EMAIL_SMTP_FROM, $fromName = EMAIL_SMTP_FROM_NAME) {
    $host = EMAIL_SMTP_HOST;
    $port = EMAIL_SMTP_PORT;
    $username = EMAIL_SMTP_USER;
    $password = EMAIL_SMTP_PASS;
    $secure = strtolower(EMAIL_SMTP_SECURE);

    if (empty($host) || empty($port) || empty($username) || empty($password) || empty($fromEmail)) {
        return ['success' => false, 'error' => 'SMTP email is not configured. Please set EMAIL_SMTP_HOST, EMAIL_SMTP_PORT, EMAIL_SMTP_USER, EMAIL_SMTP_PASS, and EMAIL_SMTP_FROM in connections.php.'];
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $fp = fsockopen($remote, $port, $errno, $errstr, 30);
    if (!$fp) {
        return ['success' => false, 'error' => "SMTP connection failed: $errno $errstr"];
    }

    $getResponse = function() use ($fp) {
        $data = '';
        while ($str = fgets($fp, 512)) {
            $data .= $str;
            if (isset($str[3]) && $str[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $sendCommand = function($command) use ($fp, $getResponse) {
        if ($command !== null) {
            fputs($fp, $command . "\r\n");
        }
        return $getResponse();
    };

    $response = $getResponse();
    if (strpos($response, '220') !== 0) {
        fclose($fp);
        return ['success' => false, 'error' => "SMTP handshake failed: $response"];
    }

    $hostname = gethostname() ?: 'localhost';
    $response = $sendCommand("EHLO $hostname");
    if (strpos($response, '250') !== 0) {
        $response = $sendCommand("HELO $hostname");
        if (strpos($response, '250') !== 0) {
            fclose($fp);
            return ['success' => false, 'error' => "SMTP HELO/EHLO failed: $response"];
        }
    }

    if ($secure === 'tls') {
        $response = $sendCommand('STARTTLS');
        if (strpos($response, '220') !== 0) {
            fclose($fp);
            return ['success' => false, 'error' => "SMTP STARTTLS failed: $response"];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['success' => false, 'error' => 'Failed to enable TLS encryption on SMTP connection.'];
        }
        $response = $sendCommand("EHLO $hostname");
        if (strpos($response, '250') !== 0) {
            fclose($fp);
            return ['success' => false, 'error' => "SMTP EHLO after STARTTLS failed: $response"];
        }
    }

    $response = $sendCommand('AUTH LOGIN');
    if (strpos($response, '334') !== 0) {
        fclose($fp);
        return ['success' => false, 'error' => "SMTP AUTH LOGIN failed: $response"];
    }

    $response = $sendCommand(base64_encode($username));
    if (strpos($response, '334') !== 0) {
        fclose($fp);
        return ['success' => false, 'error' => "SMTP username rejected: $response"];
    }

    $response = $sendCommand(base64_encode($password));
    if (strpos($response, '235') !== 0) {
        fclose($fp);
        return ['success' => false, 'error' => "SMTP password rejected: $response"];
    }

    $response = $sendCommand("MAIL FROM:<$fromEmail>");
    if (strpos($response, '250') !== 0) {
        fclose($fp);
        return ['success' => false, 'error' => "SMTP MAIL FROM failed: $response"];
    }

    $response = $sendCommand("RCPT TO:<$to>");
    if (strpos($response, '250') !== 0 && strpos($response, '251') !== 0) {
        fclose($fp);
        return ['success' => false, 'error' => "SMTP RCPT TO failed: $response"];
    }

    $response = $sendCommand('DATA');
    if (strpos($response, '354') !== 0) {
        fclose($fp);
        return ['success' => false, 'error' => "SMTP DATA command failed: $response"];
    }

    $headers = "From: $fromName <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n\r\n";

    $message = str_replace("\n", "\r\n", $message);
    $message = preg_replace('/^\./m', '..', $message);
    $payload = $headers . $message . "\r\n.\r\n";
    fputs($fp, $payload);

    $response = $getResponse();
    if (strpos($response, '250') !== 0) {
        fclose($fp);
        return ['success' => false, 'error' => "SMTP message send failed: $response"];
    }

    $sendCommand('QUIT');
    fclose($fp);

    return ['success' => true];
}
?>
