<?php
require_once "connections.php";
start_safe_session();

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: home.php");
    exit;
}

$username = $password = "";
$username_err = $password_err = $login_err = "";
$selected_theme = 0;
$success_msg = "";

if(isset($_GET['reset']) && $_GET['reset'] === 'success'){
    $success_msg = "Your password has been reset successfully. You can now login with your new password.";
}

if (isset($_SESSION['flash_login_err'])) {
    $login_err = $_SESSION['flash_login_err'];
    $selected_theme = $_SESSION['flash_selected_theme'] ?? 0;
    $username = $_SESSION['flash_username'] ?? "";
    unset($_SESSION['flash_login_err']);
    unset($_SESSION['flash_selected_theme']);
    unset($_SESSION['flash_username']);
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $selected_theme = $_POST['selected_theme'] ?? 0;

    if(empty(trim($_POST["username"]))){ $username_err = "err"; } else { $username = trim($_POST["username"]); }
    if(empty(trim($_POST["password"]))){ $password_err = "err"; } else { $password = trim($_POST["password"]); }

    if(empty($username_err) && empty($password_err)){
        $sql = "SELECT id, name, password FROM users WHERE name = ? AND deleted_at IS NULL";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1){
                    mysqli_stmt_bind_result($stmt, $id, $db_name, $hashed_password);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $db_name;
                            
                            header("location: home.php");
                            exit;
                        } else { $login_err = "Invalid username or password."; }
                    }
                } else { $login_err = "Invalid username or password."; }
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($link);

    if (!empty($login_err) || !empty($username_err) || !empty($password_err)) {
        if(empty($login_err)) { $login_err = "Invalid username or password."; }
        $_SESSION['flash_login_err'] = $login_err;
        $_SESSION['flash_selected_theme'] = $selected_theme;
        $_SESSION['flash_username'] = $username;
        header("Location: index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .password-field-wrapper {
            position: relative;
            width: 100%;
        }
        .password-field-wrapper input {
            width: 100%;
            padding-right: 42px;
            padding-left: 42px;
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
    </style>
</head>
<body id="dynamic-body">

    <div id="bg-1" class="bg-layer active"></div>
    <div id="bg-2" class="bg-layer"></div>

    <div class="auth-panel" id="main-panel">
        <div class="brand-logos" id="logo-container">
            <div class="logo-wrapper" data-index="0">
                <img src="logos/logo_cs2.png" class="logo-cs2" alt="CS2">
            </div>
            <div class="logo-wrapper" data-index="1">
                <img src="logos/logo_dota2.png" class="logo-dota2" alt="Dota 2">
            </div>
            <div class="logo-wrapper" data-index="2">
                <img src="logos/logo_rust.webp" class="logo-rust" alt="Rust">
            </div>
            <div class="logo-wrapper" data-index="3">
                <img src="logos/logo_tf2.png" class="logo-tf2" alt="TF2">
            </div>
        </div>

        <h1>LOGIN</h1>

        <?php if(!empty($success_msg)): ?>
            <div style="background-color: rgba(0, 255, 0, 0.2); color: #ccffcc; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid rgba(0, 255, 0, 0.5);">
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($login_err)): ?>
            <div class="alert-error"><?php echo $login_err; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" required>
            <div class="password-field-wrapper">
                <input type="password" name="password" placeholder="Password" id="login_password" required>
                <button type="button" class="toggle-password" onclick="togglePw('login_password', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            <input type="hidden" name="selected_theme" id="selected_theme_input" value="<?php echo htmlspecialchars($selected_theme); ?>">
            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="auth-sub-links" style="display: flex; justify-content: space-between; margin-top: 15px;">
            <a href="index_func.php?action=restart">Forgot Password?</a>
            <a href="register.php">Create Account</a>
        </div>

        <div class="site-brand-name login-brand">GEAR<span style="color: #4a9fd4;">UP!</span></div>
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

        const themes = [
            {
                bg: 'backgrounds/bg_cs2.webp',
                panel: 'rgba(173, 216, 230, 0.5)',
                input: 'rgba(50, 70, 90, 0.8)',
                btn: 'rgba(40, 60, 80, 0.9)',
                accent: '#FFD700',
                border: '#FFD700'
            },
            {
                bg: 'backgrounds/bg_dota2.png',
                panel: 'rgba(45, 60, 40, 0.7)',
                input: 'rgba(30, 40, 30, 0.8)',
                btn: 'rgba(50, 80, 50, 0.9)',
                accent: '#00FFFF',
                border: '#00FFFF'
            },
            {
                bg: 'backgrounds/bg_rust.jpg',
                panel: 'rgba(60, 55, 45, 0.75)',
                input: 'rgba(40, 35, 30, 0.8)',
                btn: 'rgba(80, 60, 40, 0.9)',
                accent: '#7DA145', 
                border: '#7DA145'  
            },
            {
                bg: 'backgrounds/bg_tf2.jpg',
                panel: 'rgba(80, 60, 50, 0.7)',
                input: 'rgba(50, 35, 30, 0.8)',
                btn: 'rgba(100, 65, 45, 0.9)',
                accent: '#EF6421',
                border: '#EF6421'
            }
        ];

        let currentIndex = <?php echo intval($selected_theme); ?>;
        let currentBgLayer = 1;
        let cycleTimer;

        const panel = document.getElementById('main-panel');
        const wrappers = document.querySelectorAll('.logo-wrapper');
        const themeInput = document.getElementById('selected_theme_input');

        function setTheme(index) {
            const theme = themes[index];
            currentIndex = index;
            themeInput.value = index;

            const nextLayer = currentBgLayer === 1 ? 2 : 1;
            const bgElement = document.getElementById(`bg-${nextLayer}`);
            const oldElement = document.getElementById(`bg-${currentBgLayer}`);
            
            bgElement.style.backgroundImage = `url('${theme.bg}')`;
            bgElement.classList.add('active');
            oldElement.classList.remove('active');
            
            currentBgLayer = nextLayer;
            
            document.documentElement.style.setProperty('--panel-bg', theme.panel);
            document.documentElement.style.setProperty('--input-bg', theme.input);
            document.documentElement.style.setProperty('--btn-bg', theme.btn);
            document.documentElement.style.setProperty('--accent-color', theme.accent);
            document.documentElement.style.setProperty('--panel-border', theme.border);

            wrappers.forEach((wrapper, i) => {
                if(i == index) {
                    wrapper.classList.add('active');
                } else {
                    wrapper.classList.remove('active');
                }
            });
        }

        wrappers.forEach(wrapper => {
            wrapper.addEventListener('click', () => {
                setTheme(parseInt(wrapper.getAttribute('data-index')));
                resetTimer(); 
            });
        });

        function autoCycle() {
            let nextIndex = (currentIndex + 1) % themes.length;
            setTheme(nextIndex);
        }

        function startTimer() {
            cycleTimer = setInterval(autoCycle, <?php echo BACKGROUND_INTERVAL * 1000; ?>);
        }

        function resetTimer() {
            clearInterval(cycleTimer);
            startTimer();
        }

        setTheme(currentIndex);
        startTimer();
    </script>
</body>
</html>