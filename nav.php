<?php
require_once "connections.php";
start_safe_session();

$active_page = $active_page ?? '';

$user_credits = 0;
$user_picture = '';
$user_display_name = '';
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $user_id = $_SESSION["id"];
    $sql = "SELECT name, credits, picture FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $name, $credits, $picture);
            if (mysqli_stmt_fetch($stmt)) {
                $user_display_name = $name;
                $user_credits = $credits;
                $user_picture = $picture;
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<nav class="navbar">
    <a href="home.php" class="nav-logo">GEAR<span style="color: var(--accent);">UP!</span></a>
    <div class="nav-links">
        <a href="home.php"      class="nav-link <?= $active_page === 'home'      ? 'active' : '' ?>">Home</a>
        <a href="inventory.php" class="nav-link <?= $active_page === 'inventory' ? 'active' : '' ?>">Inventory</a>
        <a href="market.php"    class="nav-link <?= $active_page === 'market'    ? 'active' : '' ?>">Market</a>
        <a href="offers.php"    class="nav-link <?= $active_page === 'offers'    ? 'active' : '' ?>">Offers</a>
    </div>
    <div class="nav-right">
        <div class="credits" style="cursor: default; display: flex; align-items: center; gap: 12px; padding: 0 16px; background: var(--bg-card-2); border: 1px solid var(--border); height: 34px; border-radius: 50px;">
            <span style="color: var(--text-dim); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px;">
                Balance
            </span>

            <div style="display: flex; align-items: center; gap: 1px; font-weight: 700; font-size: 14px; line-height: 1;">
                <span style="color: var(--accent);">$</span>
                <span style="color: #fff;">
                    <?= number_format($user_credits, 2) ?>
                </span>
            </div>
        </div>

        <div class="nav-user-menu">
            <div class="nav-avatar-btn" id="userMenuToggle">
                <?php if (!empty($user_picture)): ?>
                    <img src="<?= htmlspecialchars($user_picture) ?>" alt="Avatar" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                    </svg>
                <?php endif; ?>
                <span style="font-family: var(--font-body); font-size: 14px; font-weight: 600; color: var(--text); padding-left: 4px; padding-right: 2px;">
                    <?= htmlspecialchars($user_display_name) ?>
                </span>
                <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <?php
                $is_admin = false;
                if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
                    $admin_sql = "SELECT is_admin FROM users WHERE id = ?";
                    if ($admin_stmt = mysqli_prepare($link, $admin_sql)) {
                        mysqli_stmt_bind_param($admin_stmt, "i", $_SESSION["id"]);
                        if (mysqli_stmt_execute($admin_stmt)) {
                            mysqli_stmt_bind_result($admin_stmt, $admin_flag);
                            if (mysqli_stmt_fetch($admin_stmt)) {
                                $is_admin = ($admin_flag == 1);
                            }
                        }
                        mysqli_stmt_close($admin_stmt);
                    }
                }
                if ($is_admin):
                ?>
                <a href="admin.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    Admin Dashboard
                </a>
                <div class="dropdown-divider"></div>
                <?php endif; ?>
                <a href="profile.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                    My Profile
                </a>
                <a href="history.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Transaction History
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="logout-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
const toggle = document.getElementById('userMenuToggle');
const dropdown = document.getElementById('userDropdown');
if (toggle && dropdown) {
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });
    document.addEventListener('click', () => dropdown.classList.remove('open'));
}
</script>
