<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION["id"];
$error = "";
$success = "";
$step = "form";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "request_topup") {
        $amount = (float)$_POST["amount"];
        $email = $_POST["email"] ?? "";

        if ($amount > 0 && !empty($email)) {
            $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = mysqli_prepare($link, "INSERT INTO topup_requests (user_id, amount, verification_code) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ids", $user_id, $amount, $code);
            
            if (mysqli_stmt_execute($stmt)) {
                $request_id = mysqli_insert_id($link);
                $_SESSION["topup_request_id"] = $request_id;
                
                $subject = "Top-up Verification Code - " . SITE_NAME;
                $body = "Your verification code for the top-up of $" . number_format($amount, 2) . " is: " . $code . "\r\n\r\nIf you did not request this, ignore this email.";
                
                $mailResult = send_smtp_email($email, $subject, $body);
                
                if ($mailResult['success']) {
                    $step = "verify";
                    $success = "A verification code has been sent to your email.";
                } else {
                    $error = "Failed to send verification email.";
                }
            } else {
                $error = "Failed to process top-up request.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Invalid amount or email.";
        }
    } elseif ($action === "verify_code") {
        $code = $_POST["code"] ?? "";
        $req_id = $_SESSION["topup_request_id"] ?? 0;

        if ($req_id > 0 && !empty($code)) {
            $stmt = mysqli_prepare($link, "SELECT id FROM topup_requests WHERE id = ? AND verification_code = ? AND user_id = ? AND email_verified = 0");
            mysqli_stmt_bind_param($stmt, "isi", $req_id, $code, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) == 1) {
                $upd = mysqli_prepare($link, "UPDATE topup_requests SET email_verified = 1 WHERE id = ?");
                mysqli_stmt_bind_param($upd, "i", $req_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
                
                unset($_SESSION["topup_request_id"]);
                $step = "done";
            } else {
                $error = "Invalid verification code.";
                $step = "verify";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Top-up Balance - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page" style="display:flex;justify-content:center;align-items:center;min-height:70vh;">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:40px;width:100%;max-width:400px;text-align:center;">
        <h1 style="font-family:var(--font-display);font-size:24px;margin-bottom:20px;color:#fff;">BALANCE TOP-UP</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($step === "verify"): ?>
            <p style="color:var(--text-dim);font-size:14px;margin-bottom:20px;">Please enter the 6-digit code sent to your email to verify your top-up request.</p>
            <form action="topup.php" method="POST">
                <input type="hidden" name="action" value="verify_code">
                <input type="text" name="code" placeholder="000000" maxlength="6" required style="width:100%;background:var(--bg-dark);border:1px solid var(--border);border-radius:4px;color:#fff;padding:12px;font-size:24px;text-align:center;letter-spacing:8px;margin-bottom:20px;">
                <button type="submit" class="btn btn-accent btn-full">Verify Code</button>
            </form>
        <?php elseif ($step === "done"): ?>
            <p style="color:var(--text-dim);font-size:14px;margin-bottom:20px;">Your top-up request has been verified and is now pending admin approval.</p>
            <a href="home.php" class="btn btn-ghost btn-full">Return Home</a>
        <?php else: ?>
            <p style="color:var(--text-dim);font-size:14px;margin-bottom:20px;">Something went wrong or the session expired.</p>
            <a href="home.php" class="btn btn-ghost btn-full">Return Home</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>