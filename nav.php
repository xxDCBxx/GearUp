<?php
require_once "connections.php";
start_safe_session();

$active_page = $active_page ?? '';

$user_credits = 0;
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $user_id = $_SESSION["id"];
    $sql = "SELECT credits FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $credits);
            if (mysqli_stmt_fetch($stmt)) {
                $user_credits = $credits;
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
                <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                </svg>
                <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <a href="profile.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                    My Profile
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