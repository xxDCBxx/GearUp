<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php"); exit;
}
$me = $_SESSION["id"];
$chk = mysqli_prepare($link, "SELECT is_admin FROM users WHERE id = ?");
if (!$chk) { die("DB error (admin check): " . mysqli_error($link)); }
mysqli_stmt_bind_param($chk, "i", $me);
mysqli_stmt_execute($chk);
mysqli_stmt_bind_result($chk, $admin_flag);
mysqli_stmt_fetch($chk);
mysqli_stmt_close($chk);
if (!$admin_flag) { header("location: home.php"); exit; }

$active_page = 'admin_users';
$sub = $_GET['sub'] ?? 'manage';   // 'manage' | 'admins' | 'deleted'

// ── Helper: check if a user_id belongs to the protected super-admin ────────
function is_protected_admin($link, $user_id) {
    $s = mysqli_prepare($link, "SELECT email FROM users WHERE id = ?");
    mysqli_stmt_bind_param($s, "i", $user_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_bind_result($s, $email);
    mysqli_stmt_fetch($s);
    mysqli_stmt_close($s);
    return strtolower($email) === 'admin@gearup.com';
}

// ── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    // Edit user (username, email, credits, admin flag)
    if ($action === 'edit_user' && $user_id) {
        $username      = trim($_POST['username'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $credits       = (float)($_POST['credits'] ?? 0);
        $is_admin_flag = isset($_POST['is_admin']) ? 1 : 0;
        if ($user_id == $me) { $is_admin_flag = 1; }               // protect self from de-admining
        if (is_protected_admin($link, $user_id)) { $is_admin_flag = 1; } // protect super-admin

        $s = mysqli_prepare($link,
            "UPDATE users SET name = ?, email = ?, credits = ?, is_admin = ? WHERE id = ?");
        if (!$s) { die("DB error: " . mysqli_error($link)); }
        mysqli_stmt_bind_param($s, "ssdii", $username, $email, $credits, $is_admin_flag, $user_id);
        if (mysqli_stmt_execute($s)) {
            $_SESSION['flash_admin_success'] = "User updated successfully.";
        } else {
            $_SESSION['flash_admin_error'] = "Failed to update user.";
        }
        mysqli_stmt_close($s);
    }

    // Toggle admin flag
    elseif ($action === 'toggle_admin' && $user_id && $user_id != $me) {
        if (is_protected_admin($link, $user_id)) {
            $_SESSION['flash_admin_error'] = "This admin account is protected and cannot have its status removed.";
        } else {
            $s = mysqli_prepare($link, "UPDATE users SET is_admin = NOT is_admin WHERE id = ?");
            if (!$s) { die("DB error: " . mysqli_error($link)); }
            mysqli_stmt_bind_param($s, "i", $user_id);
            mysqli_stmt_execute($s);
            mysqli_stmt_close($s);
            $_SESSION['flash_admin_success'] = "Admin status updated.";
        }
    }

    // Soft-delete a user
    elseif ($action === 'delete_user' && $user_id && $user_id != $me) {
        mysqli_query($link, "UPDATE market_listings SET status='cancelled' WHERE user_id=$user_id AND status='active'");
        $s = mysqli_prepare($link, "UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        if (!$s) { die("DB error: " . mysqli_error($link)); }
        mysqli_stmt_bind_param($s, "i", $user_id);
        if (mysqli_stmt_execute($s)) {
            $_SESSION['flash_admin_success'] = "User soft-deleted. Their data is fully preserved and can be restored.";
        } else {
            $_SESSION['flash_admin_error'] = "Failed to delete user.";
        }
        mysqli_stmt_close($s);
    }

    // Restore a soft-deleted user
    elseif ($action === 'restore_user' && $user_id) {
        $s = mysqli_prepare($link, "UPDATE users SET deleted_at = NULL WHERE id = ?");
        if (!$s) { die("DB error: " . mysqli_error($link)); }
        mysqli_stmt_bind_param($s, "i", $user_id);
        if (mysqli_stmt_execute($s)) {
            $_SESSION['flash_admin_success'] = "Account restored successfully.";
        } else {
            $_SESSION['flash_admin_error'] = "Failed to restore account.";
        }
        mysqli_stmt_close($s);
        $sub = 'deleted';
    }

    // Reset password
    elseif ($action === 'reset_password' && $user_id) {
        $new_pass    = $_POST['new_password']     ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';
        if (strlen($new_pass) < 8) {
            $_SESSION['flash_admin_error'] = "Password must be at least 8 characters.";
        } elseif ($new_pass !== $confirm) {
            $_SESSION['flash_admin_error'] = "Passwords do not match.";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $s = mysqli_prepare($link, "UPDATE users SET password = ? WHERE id = ?");
            if (!$s) { die("DB error: " . mysqli_error($link)); }
            mysqli_stmt_bind_param($s, "si", $hashed, $user_id);
            if (mysqli_stmt_execute($s)) {
                $_SESSION['flash_admin_success'] = "Password reset successfully.";
            } else {
                $_SESSION['flash_admin_error'] = "Failed to reset password.";
            }
            mysqli_stmt_close($s);
        }
    }

    // Update credits only
    elseif ($action === 'update_credits' && $user_id) {
        $new_credits = (float)($_POST['credits'] ?? 0);
        $s = mysqli_prepare($link, "UPDATE users SET credits = ? WHERE id = ?");
        if (!$s) { die("DB error: " . mysqli_error($link)); }
        mysqli_stmt_bind_param($s, "di", $new_credits, $user_id);
        if (mysqli_stmt_execute($s)) {
            $_SESSION['flash_admin_success'] = "Credits updated.";
        } else {
            $_SESSION['flash_admin_error'] = "Failed to update credits.";
        }
        mysqli_stmt_close($s);
    }

    header("Location: admin_users.php?sub=$sub");
    exit;
}

// ── Fetch users ────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');

if ($sub === 'deleted') {
    $sql = "SELECT u.id, u.name, u.email, u.credits, u.is_admin, u.picture, u.deleted_at,
                   (SELECT COUNT(*) FROM user_items WHERE user_id = u.id)                             AS item_count,
                   (SELECT COUNT(*) FROM market_listings WHERE user_id = u.id)                        AS listing_count,
                   (SELECT COUNT(*) FROM trade_offers WHERE sender_id = u.id OR receiver_id = u.id)   AS offer_count
            FROM users u
            WHERE u.deleted_at IS NOT NULL";
    if ($search) { $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; }
    $sql .= " ORDER BY u.deleted_at DESC";

} elseif ($sub === 'admins') {
    // Admins only (not deleted)
    $sql = "SELECT u.id, u.name, u.email, u.credits, u.is_admin, u.picture, u.deleted_at,
                   (SELECT COUNT(*) FROM user_items WHERE user_id = u.id)                                    AS item_count,
                   (SELECT COUNT(*) FROM market_listings WHERE user_id = u.id AND status='active')           AS listing_count,
                   (SELECT COUNT(*) FROM trade_offers WHERE sender_id = u.id OR receiver_id = u.id)          AS offer_count
            FROM users u
            WHERE u.deleted_at IS NULL AND u.is_admin = 1";
    if ($search) { $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; }
    $sql .= " ORDER BY u.name ASC";

} else {
    // Regular (non-admin) users only, not deleted
    $sql = "SELECT u.id, u.name, u.email, u.credits, u.is_admin, u.picture, u.deleted_at,
                   (SELECT COUNT(*) FROM user_items WHERE user_id = u.id)                                    AS item_count,
                   (SELECT COUNT(*) FROM market_listings WHERE user_id = u.id AND status='active')           AS listing_count,
                   (SELECT COUNT(*) FROM trade_offers WHERE sender_id = u.id OR receiver_id = u.id)          AS offer_count
            FROM users u
            WHERE u.deleted_at IS NULL AND u.is_admin = 0";
    if ($search) { $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; }
    $sql .= " ORDER BY u.name ASC";
}

$stmt = mysqli_prepare($link, $sql);
if (!$stmt) { die("DB error (user fetch): " . mysqli_error($link)); }
if ($search) {
    $like = "%$search%";
    mysqli_stmt_bind_param($stmt, "ss", $like, $like);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$users  = [];
while ($row = mysqli_fetch_assoc($result)) { $users[] = $row; }
mysqli_stmt_close($stmt);

// ── Badge counts for sub-nav ───────────────────────────────────────────────
$count_manage  = (int) mysqli_fetch_row(mysqli_query($link, "SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_admin = 0"))[0];
$count_admins  = (int) mysqli_fetch_row(mysqli_query($link, "SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_admin = 1"))[0];
$count_deleted = (int) mysqli_fetch_row(mysqli_query($link, "SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL"))[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — Admin · <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        /* ── Sub-nav bar ──────────────────────────────────────────────── */
        .sub-nav {
            display: flex; align-items: center; gap: 0; padding: 0 40px;
            background: var(--bg-card); border-bottom: 1px solid var(--border); height: 46px;
        }
        .sub-nav-link {
            display: flex; align-items: center; gap: 7px;
            padding: 0 20px; height: 100%;
            font-family: var(--font-display); font-size: 15px; font-weight: 600;
            color: var(--text-dim); text-decoration: none;
            border-bottom: 3px solid transparent; transition: color .2s, border-color .2s;
        }
        .sub-nav-link:hover { color: var(--text); }
        .sub-nav-link.active { color: #fff; border-bottom-color: var(--accent); }
        .sub-nav-badge {
            background: var(--bg-card-2); border: 1px solid var(--border);
            border-radius: 20px; font-size: 11px; font-weight: 700;
            padding: 1px 7px; color: var(--text-muted);
        }
        .sub-nav-link.active .sub-nav-badge { background: var(--accent); color: #fff; border-color: var(--accent); }
        /* ── Page layout ──────────────────────────────────────────────── */
        .admin-page { padding: 32px 40px; max-width: 1400px; margin: 0 auto; }
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 28px; flex-wrap: wrap; gap: 14px;
        }
        .page-title { font-family: var(--font-display); font-size: 26px; font-weight: 700; color: #fff; letter-spacing: .5px; }
        .page-title span { color: var(--accent); }
        .search-bar { display: flex; align-items: center; gap: 10px; }
        .search-bar input {
            background: var(--bg-card-2); border: 1px solid var(--border);
            border-radius: var(--radius); color: var(--text);
            font-family: var(--font-body); font-size: 14px;
            padding: 9px 14px; outline: none; width: 260px; transition: border-color .2s;
        }
        .search-bar input:focus { border-color: var(--accent); }
        /* ── Table card ───────────────────────────────────────────────── */
        .table-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th {
            text-align: left; padding: 13px 18px;
            font-family: var(--font-display); font-size: 13px; font-weight: 600;
            letter-spacing: .5px; color: var(--text-dim);
            border-bottom: 1px solid var(--border); background: var(--bg-card-2);
        }
        .users-table td { padding: 0; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .users-table tr:last-child td { border-bottom: none; }
        .users-table tr:hover td { background: rgba(255,255,255,.02); }
        .td-inner { display: flex; align-items: center; gap: 12px; padding: 12px 18px; }
        /* ── Avatar ───────────────────────────────────────────────────── */
        .user-avatar {
            width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--border); flex-shrink: 0; background: var(--bg-card-2);
            display: flex; align-items: center; justify-content: center;
        }
        .user-avatar svg { color: var(--text-muted); }
        /* ── Badges ───────────────────────────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .badge-admin     { background: rgba(240,168,48,.15); color: var(--warn);     border: 1px solid rgba(240,168,48,.3); }
        .badge-protected { background: rgba(240,168,48,.28); color: var(--warn);     border: 1px solid rgba(240,168,48,.6); }
        .badge-user      { background: var(--bg-card-2);     color: var(--text-muted); border: 1px solid var(--border); }
        /* ── Stats row ────────────────────────────────────────────────── */
        .stat-pills { display: flex; gap: 8px; flex-wrap: wrap; }
        .stat-pill {
            font-size: 11px; font-weight: 600; color: var(--text-dim);
            background: var(--bg-card-2); border: 1px solid var(--border);
            border-radius: 20px; padding: 2px 9px; white-space: nowrap;
        }
        /* ── Actions ──────────────────────────────────────────────────── */
        .action-group { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        /* ── Credits inline edit ──────────────────────────────────────── */
        .credits-form { display: flex; align-items: center; gap: 6px; }
        .credits-form input[type=number] {
            width: 90px; background: var(--bg-dark); border: 1px solid var(--border);
            border-radius: var(--radius); color: var(--text); font-size: 13px;
            font-weight: 700; padding: 5px 8px; outline: none; transition: border-color .2s;
        }
        .credits-form input[type=number]:focus { border-color: var(--accent); }
        /* ── Empty state ──────────────────────────────────────────────── */
        .empty-state { padding: 60px; text-align: center; color: var(--text-dim); font-size: 15px; }
        .empty-state svg { margin-bottom: 14px; opacity: .3; }
        /* ── Deleted at stamp ─────────────────────────────────────────── */
        .deleted-stamp { font-size: 11px; color: var(--danger); }
        /* ── Flash messages ───────────────────────────────────────────── */
        .flash { padding: 13px 18px; border-radius: var(--radius); font-size: 14px; margin-bottom: 22px; }
        .flash-success { background: rgba(60,184,120,.12);  border: 1px solid rgba(60,184,120,.35); color: #a0f0c0; }
        .flash-warn    { background: rgba(240,168,48,.12);  border: 1px solid rgba(240,168,48,.35); color: #ffd580; }
        .flash-error   { background: rgba(224,72,58,.12);   border: 1px solid rgba(224,72,58,.35);  color: #ffaaaa; }
        /* ── Confirm overlay ──────────────────────────────────────────── */
        #confirm-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.72);
            backdrop-filter: blur(4px); z-index: 400;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity .2s;
        }
        #confirm-overlay.open { opacity: 1; pointer-events: all; }
        #confirm-box {
            background: var(--bg-card-2); border: 1px solid var(--border-light);
            border-radius: var(--radius-lg); padding: 32px; width: 90%; max-width: 420px;
            box-shadow: var(--shadow-lg); transform: scale(.95); transition: transform .2s;
        }
        #confirm-overlay.open #confirm-box { transform: scale(1); }
        #confirm-title { font-family: var(--font-display); font-size: 20px; font-weight: 700; color: var(--danger); margin-bottom: 10px; }
        #confirm-body  { font-size: 14px; color: var(--text-dim); margin-bottom: 24px; line-height: 1.6; }
        #confirm-footer { display: flex; gap: 10px; justify-content: flex-end; }
        /* ── Edit user modal ──────────────────────────────────────────── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.72);
            backdrop-filter: blur(4px); z-index: 300;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity .2s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal {
            background: var(--bg-card-2); border: 1px solid var(--border-light);
            border-radius: var(--radius-lg); padding: 32px; width: 90%; max-width: 520px;
            box-shadow: var(--shadow-lg); transform: scale(.95); transition: transform .2s;
        }
        .modal-overlay.open .modal { transform: scale(1); }
        .modal-title  { font-family: var(--font-display); font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 22px; }
        .modal-footer { display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end; }
        .field-group  { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .field-label  { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--text-dim); }
        .field-input  {
            background: var(--bg-dark); border: 1px solid var(--border);
            border-radius: var(--radius); color: var(--text);
            font-family: var(--font-body); font-size: 14px;
            padding: 9px 12px; outline: none; transition: border-color .2s; width: 100%; box-sizing: border-box;
        }
        .field-input:focus { border-color: var(--accent); }
        /* ── Protected notice inline ──────────────────────────────────── */
        .protected-notice {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 700; color: var(--warn);
            background: rgba(240,168,48,.08); border: 1px solid rgba(240,168,48,.25);
            border-radius: var(--radius); padding: 5px 10px; white-space: nowrap;
        }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<!-- Sub-nav -->
<div class="sub-nav">
    <a href="admin_users.php?sub=manage" class="sub-nav-link <?= $sub === 'manage' ? 'active' : '' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        Manage Users
        <span class="sub-nav-badge"><?= $count_manage ?></span>
    </a>
    <a href="admin_users.php?sub=admins" class="sub-nav-link <?= $sub === 'admins' ? 'active' : '' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7l-9-5zm0 4l5 2.8V12c0 3.42-2.42 6.63-5 7.74C9.42 18.63 7 15.42 7 12V8.8L12 6z"/></svg>
        Manage Admins
        <span class="sub-nav-badge"><?= $count_admins ?></span>
    </a>
    <a href="admin_users.php?sub=deleted" class="sub-nav-link <?= $sub === 'deleted' ? 'active' : '' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
        Undo Deleted
        <?php if ($count_deleted > 0): ?>
        <span class="sub-nav-badge"><?= $count_deleted ?></span>
        <?php endif; ?>
    </a>
</div>

<div class="admin-page">

    <?php if (isset($_SESSION['flash_admin_success'])): ?>
        <div class="flash flash-success"><?= htmlspecialchars($_SESSION['flash_admin_success']); unset($_SESSION['flash_admin_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_admin_error'])): ?>
        <div class="flash flash-error"><?= htmlspecialchars($_SESSION['flash_admin_error']); unset($_SESSION['flash_admin_error']); ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-title">
            <?php if ($sub === 'deleted'): ?>
                Undo <span>Deleted</span> Accounts
            <?php elseif ($sub === 'admins'): ?>
                Manage <span>Admins</span>
            <?php else: ?>
                Manage <span>Users</span>
            <?php endif; ?>
        </div>
        <div class="search-bar">
            <form method="GET" action="admin_users.php" style="display:flex;gap:8px;">
                <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
                <input type="text" name="q" placeholder="Search username or email…" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-accent btn-sm">Search</button>
                <?php if ($search): ?><a href="admin_users.php?sub=<?= $sub ?>" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($sub === 'deleted'): ?>
    <p style="color:var(--text-dim);font-size:14px;margin-bottom:20px;">
        Deleted accounts are preserved in full — inventory items, market listings (paused), and all offers (sent/received) remain intact.
        Restoring an account re-activates it immediately.
    </p>
    <?php elseif ($sub === 'admins'): ?>
    <p style="color:var(--text-dim);font-size:14px;margin-bottom:20px;">
        These accounts have admin privileges. You can edit their details or revoke admin access, moving them back to regular users.
        The <strong style="color:var(--warn);">admin@gearup.com</strong> account is permanently protected and cannot be demoted.
    </p>
    <?php endif; ?>

    <div class="table-card">
        <?php if (empty($users)): ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <div>
                <?php if ($sub === 'deleted'): ?>No deleted accounts found.
                <?php elseif ($sub === 'admins'): ?>No admin accounts found.
                <?php else: ?>No users found.<?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <table class="users-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Data Snapshot</th>
                    <?php if ($sub !== 'admins'): ?><th>Credits</th><?php endif; ?>
                    <?php if ($sub === 'deleted'): ?><th>Deleted At</th><?php endif; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $is_protected = strtolower($u['email']) === 'admin@gearup.com';
            ?>
            <tr>
                <!-- User info -->
                <td>
                    <div class="td-inner">
                        <?php if (!empty($u['picture'])): ?>
                            <img src="<?= htmlspecialchars($u['picture']) ?>" class="user-avatar" alt="">
                        <?php else: ?>
                            <div class="user-avatar">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div style="font-family:var(--font-display);font-size:15px;font-weight:600;color:var(--text);"><?= htmlspecialchars($u['name']) ?></div>
                            <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($u['email'] ?? '—') ?></div>
                            <div style="font-size:11px;color:var(--text-muted);">ID #<?= $u['id'] ?></div>
                        </div>
                    </div>
                </td>
                <!-- Role -->
                <td>
                    <div class="td-inner">
                        <?php if ($u['id'] == $me): ?>
                            <span class="badge badge-admin">You</span>
                        <?php elseif ($is_protected): ?>
                            <span class="badge badge-protected">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7l-9-5z"/></svg>
                                Super Admin
                            </span>
                        <?php elseif ($u['is_admin']): ?>
                            <span class="badge badge-admin">Admin</span>
                        <?php else: ?>
                            <span class="badge badge-user">User</span>
                        <?php endif; ?>
                    </div>
                </td>
                <!-- Data snapshot -->
                <td>
                    <div class="td-inner">
                        <div class="stat-pills">
                            <span class="stat-pill">🗂 <?= $u['item_count'] ?> items</span>
                            <span class="stat-pill">🛒 <?= $u['listing_count'] ?> listings</span>
                            <span class="stat-pill">🤝 <?= $u['offer_count'] ?> offers</span>
                        </div>
                    </div>
                </td>
                <!-- Credits — hidden entirely on admins tab -->
                <?php if ($sub !== 'admins'): ?>
                <td>
                    <div class="td-inner">
                        <?php if ($sub !== 'deleted'): ?>
                        <form method="POST" action="admin_users.php?sub=manage" class="credits-form">
                            <input type="hidden" name="action"  value="update_credits">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <span style="color:var(--accent);font-weight:700;font-size:13px;">$</span>
                            <input type="number" name="credits" step="0.01" min="0"
                                   value="<?= number_format($u['credits'], 2, '.', '') ?>" title="Edit credits">
                            <button type="submit" class="btn btn-accent btn-sm" title="Save">✓</button>
                        </form>
                        <?php else: ?>
                            <span style="color:var(--accent);font-weight:700;">$<?= number_format($u['credits'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endif; ?>
                <!-- Deleted at (only for deleted sub) -->
                <?php if ($sub === 'deleted'): ?>
                <td>
                    <div class="td-inner">
                        <span class="deleted-stamp"><?= htmlspecialchars($u['deleted_at']) ?></span>
                    </div>
                </td>
                <?php endif; ?>
                <!-- Actions -->
                <td>
                    <div class="td-inner action-group">

                        <?php if ($sub === 'deleted'): ?>
                            <!-- Restore -->
                            <form method="POST" action="admin_users.php?sub=deleted">
                                <input type="hidden" name="action"  value="restore_user">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
                                    Restore
                                </button>
                            </form>

                        <?php elseif ($sub === 'admins'): ?>
                            <!-- Edit admin (modal, credits + admin checkbox hidden) -->
                            <button class="btn btn-ghost btn-sm"
                                    onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, true)'>
                                Edit
                            </button>
                            <!-- Reset Password -->
                            <button class="btn btn-ghost btn-sm"
                                    onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Reset PW
                            </button>
                            <?php if ($u['id'] != $me && !$is_protected): ?>
                            <!-- Revoke admin -->
                            <form method="POST" action="admin_users.php?sub=admins" style="display:inline;">
                                <input type="hidden" name="action"  value="toggle_admin">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-warn btn-sm"
                                        onclick="return confirm('Remove admin privileges from <?= htmlspecialchars(addslashes($u['name'])) ?>? They will be moved to Manage Users.')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7l-9-5z"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
                                    Revoke Admin
                                </button>
                            </form>
                            <?php elseif ($is_protected): ?>
                            <span class="protected-notice">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7l-9-5z"/></svg>
                                Protected
                            </span>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- Edit user (full modal) -->
                            <button class="btn btn-ghost btn-sm"
                                    onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, false)'>
                                Edit
                            </button>
                            <!-- Reset Password -->
                            <button class="btn btn-ghost btn-sm"
                                    onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Reset PW
                            </button>
                            <?php if ($u['id'] != $me): ?>
                            <!-- Delete -->
                            <button class="btn btn-danger btn-sm"
                                    onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                Delete
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── Edit User / Admin Modal ────────────────────────────────────────────── -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-title" id="editModalTitle">Edit User</div>
        <form method="POST" action="admin_users.php?sub=<?= htmlspecialchars($sub) ?>">
            <input type="hidden" name="action"  value="edit_user">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="field-group">
                <label class="field-label">Username</label>
                <input type="text" name="username" id="editUsername" class="field-input" required>
            </div>
            <div class="field-group">
                <label class="field-label">Email</label>
                <input type="email" name="email" id="editEmail" class="field-input" required>
            </div>
            <!-- Credits: hidden when editing from Admins tab -->
            <div class="field-group" id="editCreditsGroup">
                <label class="field-label">Credits</label>
                <input type="number" step="0.01" name="credits" id="editCredits" class="field-input">
            </div>
            <!-- Admin checkbox: hidden when editing from Admins tab (always admin) -->
            <div class="field-group" id="editAdminGroup" style="flex-direction:row;align-items:center;gap:10px;">
                <input type="checkbox" name="is_admin" id="editIsAdmin" value="1"
                       style="width:16px;height:16px;accent-color:var(--accent);">
                <label class="field-label" style="margin:0;">Grant Admin Privileges</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-accent">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Reset Password Modal ───────────────────────────────────────────────── -->
<div class="modal-overlay" id="resetPasswordModal">
    <div class="modal">
        <div class="modal-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Reset Password — <span id="resetModalName" style="color:var(--accent);"></span>
        </div>
        <form method="POST" action="admin_users.php?sub=<?= htmlspecialchars($sub) ?>">
            <input type="hidden" name="action"  value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <div class="field-group">
                <label class="field-label">New Password <span style="color:var(--text-muted);font-weight:400;">(min 8 characters)</span></label>
                <input type="password" name="new_password" id="resetNewPassword" class="field-input"
                       minlength="8" required autocomplete="new-password"
                       placeholder="Enter new password…">
            </div>
            <div class="field-group">
                <label class="field-label">Confirm Password</label>
                <input type="password" name="confirm_password" id="resetConfirmPassword" class="field-input"
                       minlength="8" required autocomplete="new-password"
                       placeholder="Re-enter new password…">
            </div>
            <div id="resetPasswordError" style="display:none;font-size:13px;color:var(--danger);margin-bottom:10px;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeResetModal()">Cancel</button>
                <button type="submit" class="btn btn-warn" onclick="return validateResetForm()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Soft-delete confirm overlay ───────────────────────────────────────── -->
<div id="confirm-overlay">
    <div id="confirm-box">
        <div id="confirm-title">⚠ Delete User Account</div>
        <div id="confirm-body">
            You are about to <strong>soft-delete</strong> the account of <strong id="confirm-name"></strong>.<br><br>
            Their inventory, listings (paused), and all offer history will be <em>fully preserved</em>
            and can be restored from the <strong>Undo Deleted</strong> tab at any time.
        </div>
        <div id="confirm-footer">
            <button class="btn btn-ghost" onclick="closeConfirm()">Cancel</button>
            <form id="delete-form" method="POST" action="admin_users.php?sub=manage">
                <input type="hidden" name="action"  value="delete_user">
                <input type="hidden" name="user_id" id="delete-uid">
                <button type="submit" class="btn btn-danger">Yes, Soft-Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
// ── Edit User / Admin modal ────────────────────────────────────────────────
function openEditModal(u, isAdminTab) {
    document.getElementById('editUserId').value    = u.id;
    document.getElementById('editUsername').value  = u.name;
    document.getElementById('editEmail').value     = u.email;
    document.getElementById('editCredits').value   = u.credits;
    document.getElementById('editIsAdmin').checked = u.is_admin == 1;
    document.getElementById('editModalTitle').textContent = isAdminTab ? 'Edit Admin' : 'Edit User';

    // On the Admins tab: hide credits and the admin checkbox (they're already admin)
    const creditsGroup = document.getElementById('editCreditsGroup');
    const adminGroup   = document.getElementById('editAdminGroup');
    creditsGroup.style.display = isAdminTab ? 'none' : '';
    adminGroup.style.display   = isAdminTab ? 'none' : '';

    // Ensure is_admin stays checked when saving from Admins tab
    if (isAdminTab) { document.getElementById('editIsAdmin').checked = true; }

    document.getElementById('editUserModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editUserModal').classList.remove('open');
}
document.getElementById('editUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// ── Reset Password modal ───────────────────────────────────────────────────
function openResetModal(uid, name) {
    document.getElementById('resetUserId').value      = uid;
    document.getElementById('resetModalName').textContent = name;
    document.getElementById('resetNewPassword').value = '';
    document.getElementById('resetConfirmPassword').value = '';
    document.getElementById('resetPasswordError').style.display = 'none';
    document.getElementById('resetPasswordModal').classList.add('open');
}
function closeResetModal() {
    document.getElementById('resetPasswordModal').classList.remove('open');
}
document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
});
function validateResetForm() {
    const pw  = document.getElementById('resetNewPassword').value;
    const cpw = document.getElementById('resetConfirmPassword').value;
    const err = document.getElementById('resetPasswordError');
    if (pw.length < 8) {
        err.textContent = 'Password must be at least 8 characters.';
        err.style.display = 'block'; return false;
    }
    if (pw !== cpw) {
        err.textContent = 'Passwords do not match.';
        err.style.display = 'block'; return false;
    }
    return true;
}

// ── Soft-delete confirm ────────────────────────────────────────────────────
function confirmDelete(uid, name) {
    document.getElementById('confirm-name').textContent = name;
    document.getElementById('delete-uid').value = uid;
    document.getElementById('confirm-overlay').classList.add('open');
}
function closeConfirm() {
    document.getElementById('confirm-overlay').classList.remove('open');
}
document.getElementById('confirm-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});
</script>
</body>
</html>