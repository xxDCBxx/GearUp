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

$active_page = 'admin_requests';
$sub      = $_GET['sub'] ?? 'revert';   // 'revert' | 'deletion'
$msg      = '';
$msg_type = 'success';

// ── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']     ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);
    $type       = $_POST['type']       ?? 'revert';

    if ($request_id) {
        if ($type === 'revert') {
            if ($action === 'approve') {
                $rs = mysqli_prepare($link, "SELECT user_id, amount FROM revert_requests WHERE id = ? AND status = 'pending'");
                if (!$rs) { die("DB error: " . mysqli_error($link)); }
                mysqli_stmt_bind_param($rs, "i", $request_id);
                mysqli_stmt_execute($rs);
                $req = mysqli_fetch_assoc(mysqli_stmt_get_result($rs));
                mysqli_stmt_close($rs);
                if ($req) {
                    $cs = mysqli_prepare($link, "UPDATE users SET credits = credits + ? WHERE id = ?");
                    if (!$cs) { die("DB error: " . mysqli_error($link)); }
                    mysqli_stmt_bind_param($cs, "di", $req['amount'], $req['user_id']);
                    mysqli_stmt_execute($cs); mysqli_stmt_close($cs);
                    $us = mysqli_prepare($link, "UPDATE revert_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
                    if (!$us) { die("DB error: " . mysqli_error($link)); }
                    mysqli_stmt_bind_param($us, "ii", $me, $request_id);
                    mysqli_stmt_execute($us); mysqli_stmt_close($us);
                    $msg = "Revert request approved and credits refunded.";
                }
            } elseif ($action === 'deny') {
                $us = mysqli_prepare($link, "UPDATE revert_requests SET status='denied', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
                if (!$us) { die("DB error: " . mysqli_error($link)); }
                mysqli_stmt_bind_param($us, "ii", $me, $request_id);
                mysqli_stmt_execute($us); mysqli_stmt_close($us);
                $msg = "Revert request denied."; $msg_type = 'warn';
            }
        } elseif ($type === 'deletion') {
            if ($action === 'approve') {
                $rs = mysqli_prepare($link, "SELECT user_id FROM deletion_requests WHERE id = ? AND status = 'pending'");
                if (!$rs) { die("DB error: " . mysqli_error($link)); }
                mysqli_stmt_bind_param($rs, "i", $request_id);
                mysqli_stmt_execute($rs);
                $req = mysqli_fetch_assoc(mysqli_stmt_get_result($rs));
                mysqli_stmt_close($rs);
                if ($req) {
                    $uid = $req['user_id'];
                    mysqli_query($link, "UPDATE market_listings SET status='cancelled' WHERE user_id=$uid AND status='active'");
                    $ds = mysqli_prepare($link, "UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                    if (!$ds) { die("DB error: " . mysqli_error($link)); }
                    mysqli_stmt_bind_param($ds, "i", $uid);
                    mysqli_stmt_execute($ds); mysqli_stmt_close($ds);
                    $us = mysqli_prepare($link, "UPDATE deletion_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
                    if (!$us) { die("DB error: " . mysqli_error($link)); }
                    mysqli_stmt_bind_param($us, "ii", $me, $request_id);
                    mysqli_stmt_execute($us); mysqli_stmt_close($us);
                    $msg = "Account deletion approved and user soft-deleted."; $msg_type = 'warn';
                }
            } elseif ($action === 'deny') {
                $us = mysqli_prepare($link, "UPDATE deletion_requests SET status='denied', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
                if (!$us) { die("DB error: " . mysqli_error($link)); }
                mysqli_stmt_bind_param($us, "ii", $me, $request_id);
                mysqli_stmt_execute($us); mysqli_stmt_close($us);
                $msg = "Deletion request denied."; $msg_type = 'warn';
            }
        }
    }

    header("Location: admin_requests.php?sub=$sub" . ($msg ? "&msg=" . urlencode($msg) . "&mt=$msg_type" : ''));
    exit;
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msg_type = $_GET['mt'] ?? 'success'; }

// ── Fetch requests ─────────────────────────────────────────────────────────
$requests = [];
if ($sub === 'deletion') {
    $sql = "SELECT dr.id, dr.reason, dr.status, dr.created_at, dr.reviewed_at,
                   u.id AS user_id, u.name AS user_name, u.email AS user_email,
                   a.name AS reviewed_by_name
            FROM deletion_requests dr
            JOIN users u ON dr.user_id = u.id
            LEFT JOIN users a ON dr.reviewed_by = a.id
            ORDER BY FIELD(dr.status,'pending','approved','denied'), dr.created_at DESC";
} else {
    $sql = "SELECT rr.id, rr.type, rr.reference_id, rr.reason, rr.amount, rr.status, rr.created_at, rr.reviewed_at,
                   u.id AS user_id, u.name AS user_name, u.email AS user_email,
                   a.name AS reviewed_by_name
            FROM revert_requests rr
            JOIN users u ON rr.user_id = u.id
            LEFT JOIN users a ON rr.reviewed_by = a.id
            ORDER BY FIELD(rr.status,'pending','approved','denied'), rr.created_at DESC";
}
$stmt = mysqli_prepare($link, $sql);
if (!$stmt) { die("DB error (fetch): " . mysqli_error($link)); }
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) { $requests[] = $row; }
mysqli_stmt_close($stmt);

// Pending counts for sub-nav badges
$pc = mysqli_query($link, "SELECT COUNT(*) FROM revert_requests WHERE status='pending'");
$pending_revert   = mysqli_fetch_row($pc)[0];
$dc = mysqli_query($link, "SELECT COUNT(*) FROM deletion_requests WHERE status='pending'");
$pending_deletion = mysqli_fetch_row($dc)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests — Admin · <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .sub-nav {
            display: flex; align-items: center; padding: 0 40px;
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
        .sub-nav-badge.has-pending { background: rgba(224,72,58,.2); border-color: rgba(224,72,58,.4); color: var(--danger); }

        .admin-page { padding: 32px 40px; max-width: 1200px; margin: 0 auto; }
        .page-header { display: flex; align-items: center; margin-bottom: 28px; }
        .page-title { font-family: var(--font-display); font-size: 26px; font-weight: 700; color: #fff; }
        .page-title span { color: var(--accent); }

        .table-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
        .req-table { width: 100%; border-collapse: collapse; }
        .req-table th {
            text-align: left; padding: 13px 18px;
            font-family: var(--font-display); font-size: 13px; font-weight: 600;
            letter-spacing: .5px; color: var(--text-dim);
            border-bottom: 1px solid var(--border); background: var(--bg-card-2);
        }
        .req-table td { padding: 14px 18px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 14px; }
        .req-table tr:last-child td { border-bottom: none; }
        .req-table tr:hover td { background: rgba(255,255,255,.02); }

        .status-chip {
            display: inline-flex; align-items: center; padding: 3px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
        }
        .chip-pending  { background: rgba(240,168,48,.15); color: var(--warn);    border: 1px solid rgba(240,168,48,.3); }
        .chip-approved { background: rgba(60,184,120,.15); color: var(--success); border: 1px solid rgba(60,184,120,.3); }
        .chip-denied   { background: rgba(224,72,58,.15);  color: var(--danger);  border: 1px solid rgba(224,72,58,.3); }

        .action-group { display: flex; gap: 6px; }
        .reason-cell { font-size: 13px; color: var(--text-dim); max-width: 220px; }
        .user-line { font-weight: 600; color: var(--text); }
        .user-sub  { font-size: 11px; color: var(--text-muted); }

        .empty-state { padding: 60px; text-align: center; color: var(--text-dim); font-size: 15px; }
        .empty-state svg { display: block; margin: 0 auto 14px; opacity: .3; }
        .flash { padding: 13px 18px; border-radius: var(--radius); font-size: 14px; margin-bottom: 22px; }
        .flash-success { background: rgba(60,184,120,.12); border: 1px solid rgba(60,184,120,.35); color: #a0f0c0; }
        .flash-warn    { background: rgba(240,168,48,.12); border: 1px solid rgba(240,168,48,.35); color: #ffd580; }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="sub-nav">
    <a href="admin_requests.php?sub=revert" class="sub-nav-link <?= $sub === 'revert' ? 'active' : '' ?>">
        Revert Requests
        <span class="sub-nav-badge <?= $pending_revert > 0 ? 'has-pending' : '' ?>"><?= $pending_revert ?> pending</span>
    </a>
    <a href="admin_requests.php?sub=deletion" class="sub-nav-link <?= $sub === 'deletion' ? 'active' : '' ?>">
        Deletion Requests
        <span class="sub-nav-badge <?= $pending_deletion > 0 ? 'has-pending' : '' ?>"><?= $pending_deletion ?> pending</span>
    </a>
</div>

<div class="admin-page">
    <?php if ($msg): ?>
    <div class="flash flash-<?= htmlspecialchars($msg_type) ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-title">
            <?= $sub === 'deletion' ? 'Deletion <span>Requests</span>' : 'Revert <span>Requests</span>' ?>
        </div>
    </div>

    <div class="table-card">
        <?php if (empty($requests)): ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8h16v10z"/></svg>
            No <?= $sub === 'deletion' ? 'deletion' : 'revert' ?> requests found.
        </div>
        <?php else: ?>
        <table class="req-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <?php if ($sub === 'revert'): ?>
                    <th>Ref</th>
                    <th>Amount</th>
                    <?php endif; ?>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
            <tr>
                <td style="color:var(--text-muted);font-size:12px;">#<?= $r['id'] ?></td>
                <td>
                    <div class="user-line"><?= htmlspecialchars($r['user_name']) ?></div>
                    <div class="user-sub"><?= htmlspecialchars($r['user_email']) ?></div>
                </td>
                <?php if ($sub === 'revert'): ?>
                <td style="font-size:13px;text-transform:capitalize;color:var(--text-dim);">
                    <?= htmlspecialchars($r['type']) ?> #<?= $r['reference_id'] ?>
                </td>
                <td style="color:var(--accent);font-weight:700;">$<?= number_format($r['amount'], 2) ?></td>
                <?php endif; ?>
                <td class="reason-cell"><?= $r['reason'] ? htmlspecialchars($r['reason']) : '—' ?></td>
                <td>
                    <?php $chip = match($r['status']) { 'approved' => 'chip-approved', 'denied' => 'chip-denied', default => 'chip-pending' }; ?>
                    <span class="status-chip <?= $chip ?>"><?= ucfirst($r['status']) ?></span>
                    <?php if (!empty($r['reviewed_by_name'])): ?>
                    <div class="user-sub" style="margin-top:3px;">by <?= htmlspecialchars($r['reviewed_by_name']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                    <?= date('M j, Y', strtotime($r['created_at'])) ?><br>
                    <?= date('g:i A', strtotime($r['created_at'])) ?>
                </td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <div class="action-group">
                        <form method="POST" action="admin_requests.php?sub=<?= $sub ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="type" value="<?= $sub === 'deletion' ? 'deletion' : 'revert' ?>">
                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                        </form>
                        <form method="POST" action="admin_requests.php?sub=<?= $sub ?>">
                            <input type="hidden" name="action" value="deny">
                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="type" value="<?= $sub === 'deletion' ? 'deletion' : 'revert' ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Deny</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted);">Reviewed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>