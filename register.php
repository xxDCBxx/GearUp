<?php
require_once "connections.php";
start_safe_session();

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: home.php");
    exit;
}

$username = $email = $password = $confirm_password = "";
$username_err = $email_err = $password_err = $confirm_password_err = "";

if (isset($_SESSION['flash_register_errors'])) {
    $errors = $_SESSION['flash_register_errors'];
    $username_err = $errors['username_err'] ?? "";
    $email_err = $errors['email_err'] ?? "";
    $password_err = $errors['password_err'] ?? "";
    $confirm_password_err = $errors['confirm_password_err'] ?? "";
    
    $inputs = $_SESSION['flash_register_inputs'] ?? [];
    $username = $inputs['username'] ?? "";
    $email = $inputs['email'] ?? "";
    
    unset($_SESSION['flash_register_errors']);
    unset($_SESSION['flash_register_inputs']);
}

if($_SERVER["REQUEST_METHOD"] == "POST"){

    if(empty(trim($_POST["username"]))){ 
        $username_err = "Please enter a username."; 
    } else { 
        $sql = "SELECT id FROM users WHERE name = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = trim($_POST["username"]);
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else{
                $username_err = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if(empty(trim($_POST["email"]))){ 
        $email_err = "Please enter an email."; 
    } else { 
        $sql = "SELECT id FROM users WHERE email = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $email_err = "This email is already in use.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else{
                $email_err = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if(empty(trim($_POST["password"]))){ 
        $password_err = "Please enter a password."; 
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "Password must have at least 6 characters.";
    } else { 
        $password = trim($_POST["password"]); 
    }
    
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Passwords do not match.";
        }
    }

    if(empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)){
        // Changed credits from 0 to 10000
        $sql = "INSERT INTO users (name, email, password, picture, credits) VALUES (?, ?, ?, '', 10000)";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "sss", $param_username, $param_email, $param_password);
            
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            
            if(mysqli_stmt_execute($stmt)){
                header("location: index.php?registered=true");
                exit;
            } else{
                $username_err = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($link);

    if(!empty($username_err) || !empty($email_err) || !empty($password_err) || !empty($confirm_password_err)){
        $_SESSION['flash_register_errors'] = [
            'username_err' => $username_err,
            'email_err' => $email_err,
            'password_err' => $password_err,
            'confirm_password_err' => $confirm_password_err
        ];
        $_SESSION['flash_register_inputs'] = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? ''
        ];
        header("Location: register.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="static-bg">

    <div class="auth-panel register-panel">
        <div class="brand-logos">
            <div class="logo-wrapper">
                <img src="logos/logo_cs2.png" class="logo-cs2" alt="CS:GO Logo">
            </div>
            <div class="logo-wrapper">
                <img src="logos/logo_dota2.png" class="logo-dota2" alt="Dota 2 Logo">
            </div>
            <div class="logo-wrapper">
                <img src="logos/logo_rust.webp" class="logo-rust" alt="Rust Logo">
            </div>
            <div class="logo-wrapper">
                <img src="logos/logo_tf2.png" class="logo-tf2" alt="Team Fortress 2 Logo">
            </div>
        </div>

        <div class="site-brand-name register-brand">GEAR<span style="color: #4a9fd4;">UP!</span></div>

        <h1>REGISTRATION</h1>

        <?php 
        if(!empty($username_err)){
            echo '<div class="alert-error">' . $username_err . '</div>';
        } elseif(!empty($email_err)){
            echo '<div class="alert-error">' . $email_err . '</div>';
        } elseif(!empty($password_err)){
            echo '<div class="alert-error">' . $password_err . '</div>';
        } elseif(!empty($confirm_password_err)){
            echo '<div class="alert-error">' . $confirm_password_err . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" required>
            <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
            <div class="password-field-wrapper">
                <input type="password" name="password" placeholder="Password" id="register_password" required>
                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('register_password')">
                    <span class="toggle-icon">👁️</span>
                </button>
            </div>
            <div class="password-field-wrapper">
                <input type="password" name="confirm_password" placeholder="Confirm Password" id="register_confirm_password" required>
                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('register_confirm_password')">
                    <span class="toggle-icon">👁️</span>
                </button>
            </div>

            <button type="submit" class="btn-primary btn-register">Register</button>
        </form>

        <div class="auth-sub-links" style="justify-content: center; margin-top: 15px;">
            <a href="index.php" style="color: #92bce3;">Sign In</a>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
            } else {
                field.type = 'password';
            }
        }
    </script>

</body>
</html>
