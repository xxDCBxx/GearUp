<?php
require_once "connections.php";
start_safe_session();

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: home.php");
    exit;
}

if(!isset($_GET['action']) || $_GET['action'] === 'restart'){
    unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_verified'],
          $_SESSION['fp_error'], $_SESSION['fp_success']);
    header("Location: index_func.php?action=forgot");
    exit;
}

if(!isset($_SESSION['fp_step'])){
    $_SESSION['fp_step'] = 'email';
}

$email_err   = "";
$success_msg = "";

// ════════════════════════════════════════════════════════════════════════════
if($_SERVER["REQUEST_METHOD"] == "POST"){

    $posted_step = $_POST['step'] ?? '';

    // ── Step 1: Send Code ──────────────────────────────────────────────────
    if($posted_step === 'email'){
        $email_input = trim($_POST['email'] ?? '');

        if(empty($email_input)){
            $email_err = "Please enter your email address.";
        } elseif(!filter_var($email_input, FILTER_VALIDATE_EMAIL)){
            $email_err = "Please enter a valid email address.";
        } else {
            $stmt = mysqli_prepare($link, "SELECT id FROM users WHERE email = ?");
            if(!$stmt){
                $email_err = "Database error: " . mysqli_error($link);
            } else {
                mysqli_stmt_bind_param($stmt, "s", $email_input);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                $found = (mysqli_stmt_num_rows($stmt) == 1);
                mysqli_stmt_close($stmt);

                if(!$found){
                    $email_err = "No account found with that email address.";
                } else {
                    // Generate a simple 6-digit numeric code — avoids any case issues
                    $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

                    // Use UTC timestamp for both PHP and MySQL to avoid timezone mismatch
                    $expiration = gmdate('Y-m-d H:i:s', strtotime('+15 minutes'));

                    $del = mysqli_prepare($link, "DELETE FROM password_resets WHERE email = ?");
                    if(!$del){
                        $email_err = "Database error: " . mysqli_error($link);
                    } else {
                        mysqli_stmt_bind_param($del, "s", $email_input);
                        mysqli_stmt_execute($del);
                        mysqli_stmt_close($del);

                        $ins = mysqli_prepare($link, "INSERT INTO password_resets (email, code, expiration) VALUES (?, ?, ?)");
                        if(!$ins){
                            $email_err = "Database error: " . mysqli_error($link);
                        } else {
                            mysqli_stmt_bind_param($ins, "sss", $email_input, $code, $expiration);
                            $inserted = mysqli_stmt_execute($ins);
                            mysqli_stmt_close($ins);

                            if(!$inserted){
                                $email_err = "Database error: " . mysqli_error($link);
                            } else {
                                $subject = "Password Reset Code - " . SITE_NAME;
                                $body    = "Your password reset code is: " . $code . "\r\n\r\n"
                                         . "This code expires in 15 minutes.\r\n\r\n"
                                         . "If you did not request this, ignore this email.";

                                $mailResult = send_smtp_email($email_input, $subject, $body);

                                if($mailResult['success']){
                                    $_SESSION['fp_email']   = $email_input;
                                    $_SESSION['fp_step']    = 'code';
                                    $_SESSION['fp_success'] = 'A reset code has been sent to your email.';
                                    mysqli_close($link);
                                    header("Location: index_func.php?action=forgot");
                                    exit;
                                } else {
                                    $del2 = mysqli_prepare($link, "DELETE FROM password_resets WHERE email = ?");
                                    if($del2){
                                        mysqli_stmt_bind_param($del2, "s", $email_input);
                                        mysqli_stmt_execute($del2);
                                        mysqli_stmt_close($del2);
                                    }
                                    $email_err = "Failed to send email: " . $mailResult['error'];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // ── Step 2: Verify Code ────────────────────────────────────────────────
    elseif($posted_step === 'code'){
        $email_session = $_SESSION['fp_email'] ?? '';
        $code_input    = trim($_POST['code'] ?? '');

        if(empty($code_input)){
            $email_err = "Please enter the code sent to your email.";
        } elseif(empty($email_session)){
            unset($_SESSION['fp_step']);
            mysqli_close($link);
            header("Location: index_func.php?action=forgot");
            exit;
        } else {
            // Compare using UTC_TIMESTAMP() to match gmdate() used when storing
            $stmt = mysqli_prepare($link,
                "SELECT id FROM password_resets WHERE email = ? AND code = ? AND expiration > UTC_TIMESTAMP()");
            if(!$stmt){
                $email_err = "Database error: " . mysqli_error($link);
            } else {
                mysqli_stmt_bind_param($stmt, "ss", $email_session, $code_input);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                $valid = (mysqli_stmt_num_rows($stmt) == 1);
                mysqli_stmt_close($stmt);

                if($valid){
                    $_SESSION['fp_step']     = 'newpassword';
                    $_SESSION['fp_verified'] = true;
                    mysqli_close($link);
                    header("Location: index_func.php?action=forgot");
                    exit;
                } else {
                    $email_err = "Invalid or expired code. Please try again.";
                }
            }
        }
    }

    // ── Step 3: Reset Password ─────────────────────────────────────────────
    elseif($posted_step === 'newpassword'){
        $email_session = $_SESSION['fp_email']    ?? '';
        $verified      = $_SESSION['fp_verified'] ?? false;
        $password      = trim($_POST['password']         ?? '');
        $confirm       = trim($_POST['confirm_password'] ?? '');

        if(!$verified || empty($email_session)){
            unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_verified']);
            mysqli_close($link);
            header("Location: index_func.php?action=forgot");
            exit;
        }

        if(empty($password)){
            $email_err = "Please enter a new password.";
        } elseif(strlen($password) < 6){
            $email_err = "Password must be at least 6 characters.";
        } elseif($password !== $confirm){
            $email_err = "Passwords do not match.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $upd    = mysqli_prepare($link, "UPDATE users SET password = ? WHERE email = ?");
            if(!$upd){
                $email_err = "Database error: " . mysqli_error($link);
            } else {
                mysqli_stmt_bind_param($upd, "ss", $hashed, $email_session);
                mysqli_stmt_execute($upd);
                $affected = mysqli_stmt_affected_rows($upd);
                mysqli_stmt_close($upd);

                if($affected > 0){
                    $del = mysqli_prepare($link, "DELETE FROM password_resets WHERE email = ?");
                    if($del){
                        mysqli_stmt_bind_param($del, "s", $email_session);
                        mysqli_stmt_execute($del);
                        mysqli_stmt_close($del);
                    }
                    unset($_SESSION['fp_step'], $_SESSION['fp_email'],
                          $_SESSION['fp_verified'], $_SESSION['fp_success']);
                    mysqli_close($link);
                    header("Location: index.php?reset=success");
                    exit;
                } else {
                    $email_err = "Failed to update password. Please try again.";
                }
            }
        }
    }

    if(!empty($email_err)){
        $_SESSION['fp_error'] = $email_err;
        mysqli_close($link);
        header("Location: index_func.php?action=forgot");
        exit;
    }
}

if(isset($_SESSION['fp_error'])){
    $email_err = $_SESSION['fp_error'];
    unset($_SESSION['fp_error']);
}
if(isset($_SESSION['fp_success'])){
    $success_msg = $_SESSION['fp_success'];
    unset($_SESSION['fp_success']);
}

$step          = $_SESSION['fp_step']  ?? 'email';
$display_email = $_SESSION['fp_email'] ?? '';

mysqli_close($link);

function eyeIconSvg(){
    return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .password-field-wrapper {
            position: relative;
            width: 100%;
        }
        .password-field-wrapper input {
            width: 100%;
            padding-right: 42px;
            box-sizing: border-box;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.55);
            transition: color 0.2s;
        }
        .toggle-password:hover { color: rgba(255,255,255,0.9); }
        .toggle-password svg { width: 20px; height: 20px; pointer-events: none; }
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .step-indicator .dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
        }
        .step-indicator .dot.active { background: #EF6421; }
        .step-indicator .dot.done   { background: rgba(255,255,255,0.6); }
        .step-indicator .line {
            width: 30px; height: 2px;
            background: rgba(255,255,255,0.2);
        }
        .step-indicator .line.done  { background: rgba(255,255,255,0.6); }
        input.code-input {
            letter-spacing: 8px;
            font-size: 1.4rem;
            text-align: center;
        }
        .email-hint {
            text-align: center;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.65);
            margin-bottom: 14px;
            line-height: 1.5;
        }
        .email-hint strong { color: #fff; }
        .restart-link {
            display: block;
            text-align: center;
            margin-top: 10px;
            font-size: 0.82rem;
            color: #92bce3;
            text-decoration: underline;
        }
        .restart-link:hover { color: #fff; }
        .alert-success {
            background: rgba(0,200,80,0.18);
            color: #ccffcc;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid rgba(0,200,80,0.4);
        }
    </style>
</head>
<body class="static-bg">

    <div class="auth-panel register-panel">

        <div class="brand-logos">
            <div class="logo-wrapper">
                <img src="logos/logo_cs2.png"   class="logo-cs2"  alt="CS2">
            </div>
            <div class="logo-wrapper">
                <img src="logos/logo_dota2.png" class="logo-dota2" alt="Dota 2">
            </div>
            <div class="logo-wrapper">
                <img src="logos/logo_rust.webp" class="logo-rust"  alt="Rust">
            </div>
            <div class="logo-wrapper">
                <img src="logos/logo_tf2.png"   class="logo-tf2"   alt="TF2">
            </div>
        </div>

        <div class="site-brand-name register-brand">GEAR<span style="color: #4a9fd4;">UP!</span></div>

        <h1>RESET PASSWORD</h1>

        <div class="step-indicator">
            <span class="dot <?php echo $step==='email' ? 'active' : 'done'; ?>"></span>
            <span class="line <?php echo in_array($step,['code','newpassword']) ? 'done' : ''; ?>"></span>
            <span class="dot <?php echo $step==='code' ? 'active' : ($step==='newpassword' ? 'done' : ''); ?>"></span>
            <span class="line <?php echo $step==='newpassword' ? 'done' : ''; ?>"></span>
            <span class="dot <?php echo $step==='newpassword' ? 'active' : ''; ?>"></span>
        </div>

        <?php if(!empty($email_err)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($email_err); ?></div>
        <?php endif; ?>
        <?php if(!empty($success_msg)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if($step === 'email'): ?>

            <form action="index_func.php?action=forgot" method="post">
                <input type="hidden" name="step" value="email">
                <input type="email" name="email" placeholder="Enter your email" required>
                <button type="submit" class="btn-primary btn-register">Send Reset Code</button>
            </form>

        <?php elseif($step === 'code'): ?>

            <p class="email-hint">
                A 6-digit code was sent to<br>
                <strong><?php echo htmlspecialchars($display_email); ?></strong>
            </p>

            <form action="index_func.php?action=forgot" method="post">
                <input type="hidden" name="step" value="code">
                <input type="text" name="code" class="code-input"
                       placeholder="000000" maxlength="6"
                       inputmode="numeric" autocomplete="one-time-code" required>
                <button type="submit" class="btn-primary btn-register">Verify Code</button>
            </form>

            <a class="restart-link" href="index_func.php?action=restart">Wrong email? Start over</a>

        <?php elseif($step === 'newpassword'): ?>

            <form action="index_func.php?action=forgot" method="post">
                <input type="hidden" name="step" value="newpassword">

                <div class="password-field-wrapper">
                    <input type="password" name="password" id="fp_pw1"
                           placeholder="New Password" required>
                    <button type="button" class="toggle-password" onclick="togglePw('fp_pw1',this)">
                        <?php echo eyeIconSvg(); ?>
                    </button>
                </div>

                <div class="password-field-wrapper">
                    <input type="password" name="confirm_password" id="fp_pw2"
                           placeholder="Confirm New Password" required>
                    <button type="button" class="toggle-password" onclick="togglePw('fp_pw2',this)">
                        <?php echo eyeIconSvg(); ?>
                    </button>
                </div>

                <button type="submit" class="btn-primary btn-register">Reset Password</button>
            </form>

        <?php endif; ?>

        <div class="auth-sub-links" style="justify-content: center; margin-top: 15px;">
            <a href="index.php" style="color: #92bce3;">Back to Login</a>
        </div>

    </div>

    <script>
    const eyeOpen = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>`;
    const eyeShut = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>`;

    function togglePw(inputId, btn) {
        const input = document.getElementById(inputId);
        const show  = input.type === 'password';
        input.type  = show ? 'text' : 'password';
        btn.innerHTML = show ? eyeShut : eyeOpen;
    }
    </script>
</body>
</html>