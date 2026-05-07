<?php
require_once "connections.php";
start_safe_session();

$active_page = $active_page ?? '';

$user_credits       = 0;
$user_picture       = '';
$user_display_name  = '';
$user_email         = '';
$is_admin           = false;

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $user_id = $_SESSION["id"];
    $sql = "SELECT name, email, credits, picture, is_admin FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $name, $email, $credits, $picture, $admin_flag);
            if (mysqli_stmt_fetch($stmt)) {
                $user_display_name = $name;
                $user_email        = $email;
                $user_credits      = $credits;
                $user_picture      = $picture;
                $is_admin          = ($admin_flag == 1);
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<nav class="navbar">
    <?php if ($is_admin): ?>
        <a href="admin_users.php" class="nav-logo">GEAR<span style="color:var(--accent);">UP!</span> <span style="font-size:11px;font-weight:600;letter-spacing:2px;color:var(--warn);vertical-align:middle;margin-left:6px;">ADMIN</span></a>
        <div class="nav-links">
            <a href="admin_users.php"    class="nav-link <?= $active_page === 'admin_users'    ? 'active' : '' ?>">Users</a>
            <a href="admin_requests.php" class="nav-link <?= $active_page === 'admin_requests' ? 'active' : '' ?>">Requests</a>
        </div>
    <?php else: ?>
        <a href="home.php" class="nav-logo">GEAR<span style="color:var(--accent);">UP!</span></a>
        <div class="nav-links">
            <a href="home.php"      class="nav-link <?= $active_page === 'home'      ? 'active' : '' ?>">Home</a>
            <a href="inventory.php" class="nav-link <?= $active_page === 'inventory' ? 'active' : '' ?>">Inventory</a>
            <a href="market.php"    class="nav-link <?= $active_page === 'market'    ? 'active' : '' ?>">Market</a>
            <a href="offers.php"    class="nav-link <?= $active_page === 'offers'    ? 'active' : '' ?>">Offers</a>
        </div>
    <?php endif; ?>

    <div class="nav-right">
        <?php if (!$is_admin): ?>
        <div class="credits" id="balanceDropdownToggle" style="cursor:pointer;position:relative;display:flex;align-items:center;gap:12px;padding:0 16px;background:var(--bg-card-2);border:1px solid var(--border);height:34px;border-radius:50px;">
            <span style="color:var(--text-dim);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;">Balance</span>
            <div style="display:flex;align-items:center;gap:1px;font-weight:700;font-size:14px;line-height:1;">
                <span style="color:var(--accent);">$</span>
                <span style="color:#fff;"><?= number_format($user_credits, 2) ?></span>
            </div>
            
            <div class="user-dropdown" id="balanceDropdown" style="width:250px; right:0; padding:15px; cursor:default;">
                <div style="font-size:12px; font-weight:bold; color:var(--text-dim); margin-bottom:10px; text-transform:uppercase;">Top-up Balance</div>
                <form action="topup.php" method="POST" style="display:flex; flex-direction:column; gap:10px;">
                    <input type="hidden" name="action" value="request_topup">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($user_email ?? '') ?>">
                    <input type="number" name="amount" placeholder="Amount (USD)" step="0.01" min="1" required style="width:100%; background:var(--bg-dark); border:1px solid var(--border); border-radius:4px; color:#fff; padding:8px 12px; font-size:14px;">
                    <button type="submit" class="btn btn-accent btn-sm" style="width:100%;">Request Top-up</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-user-menu">
            <div class="nav-avatar-btn" id="userMenuToggle">
                <?php if (!empty($user_picture)): ?>
                    <img src="<?= htmlspecialchars($user_picture) ?>" alt="Avatar" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                    </svg>
                <?php endif; ?>
                <span style="font-family:var(--font-body);font-size:14px;font-weight:600;color:var(--text);padding-left:4px;padding-right:2px;">
                    <?= htmlspecialchars($user_display_name) ?>
                </span>
                <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <?php if (!$is_admin): ?>
                <a href="profile.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                    My Profile
                </a>
                <a href="history.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Transaction History
                </a>
                <div class="dropdown-divider"></div>
                <?php endif; ?>
                <a href="logout.php" class="logout-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
const toggle   = document.getElementById('userMenuToggle');
const dropdown = document.getElementById('userDropdown');
const balToggle = document.getElementById('balanceDropdownToggle');
const balDropdown = document.getElementById('balanceDropdown');

if (toggle && dropdown) {
    toggle.addEventListener('click', (e) => { 
        e.stopPropagation(); 
        dropdown.classList.toggle('open'); 
    });
    dropdown.addEventListener('click', (e) => {
        e.stopPropagation();
    });
}

if (balToggle && balDropdown) {
    balToggle.addEventListener('click', (e) => { 
        e.stopPropagation();
        if(!balDropdown.contains(e.target)) {
            balDropdown.classList.toggle('open'); 
        }
    });
    balDropdown.addEventListener('click', (e) => {
        e.stopPropagation();
    });
}

document.addEventListener('click', () => {
    if(dropdown) dropdown.classList.remove('open');
    if(balDropdown) balDropdown.classList.remove('open');
});
</script>
