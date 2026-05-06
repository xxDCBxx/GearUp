<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php"); exit;
}
$me = $_SESSION["id"];
$chk = mysqli_prepare($link, "SELECT is_admin FROM users WHERE id = ?");
if (!$chk) { die("DB error: " . mysqli_error($link)); }
mysqli_stmt_bind_param($chk, "i", $me);
mysqli_stmt_execute($chk);
mysqli_stmt_bind_result($chk, $admin_flag);
mysqli_stmt_fetch($chk);
mysqli_stmt_close($chk);
if (!$admin_flag) { header("location: home.php"); exit; }

$active_page = 'admin_requests';
$sub = $_GET['sub'] ?? 'revert';

function req_game_info(string $game): array {
    return match($game) {
        'cs2'   => ['name' => 'CS2',             'logo' => 'logos/logo_cs2.png'],
        'dota2' => ['name' => 'Dota 2',          'logo' => 'logos/logo_dota2.png'],
        'rust'  => ['name' => 'Rust',            'logo' => 'logos/logo_rust.webp'],
        'tf2'   => ['name' => 'Team Fortress 2', 'logo' => 'logos/logo_tf2.png'],
        default => ['name' => 'Unknown',         'logo' => '']
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']     ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);
    $type       = $_POST['req_type']   ?? 'revert';

    if ($request_id) {

        if ($type === 'revert') {

            if ($action === 'approve') {
                $tx_type = $_POST['tx_type']     ?? '';
                $ref_id  = intval($_POST['reference_id'] ?? 0);

                mysqli_begin_transaction($link);
                try {
                    if ($tx_type === 'market') {
                        $stmt = mysqli_prepare($link,
                            "SELECT buyer_id, seller_id, item_id, price
                             FROM market_history WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "i", $ref_id);
                        mysqli_stmt_execute($stmt);
                        $trade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                        mysqli_stmt_close($stmt);

                        if (!$trade) throw new Exception("Market history record not found.");

                        $check = mysqli_query($link,
                            "SELECT id FROM user_items
                             WHERE user_id = {$trade['buyer_id']}
                               AND item_id = {$trade['item_id']}
                             LIMIT 1");
                        if (mysqli_num_rows($check) == 0) {
                            throw new Exception(
                                "Revert blocked: the buyer no longer has the received item."
                            );
                        }
                        $ui_row = mysqli_fetch_assoc($check);

                        mysqli_query($link,
                            "UPDATE user_items SET user_id = {$trade['seller_id']}
                             WHERE id = {$ui_row['id']}");
                        mysqli_query($link,
                            "UPDATE users SET credits = credits + {$trade['price']}
                             WHERE id = {$trade['buyer_id']}");
                        mysqli_query($link,
                            "UPDATE users SET credits = credits - {$trade['price']}
                             WHERE id = {$trade['seller_id']}");

                    } elseif ($tx_type === 'trade') {
                        $stmt = mysqli_prepare($link,
                            "SELECT user1_id, user2_id, user1_items, user2_items
                             FROM trade_history WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "i", $ref_id);
                        mysqli_stmt_execute($stmt);
                        $trade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                        mysqli_stmt_close($stmt);

                        if (!$trade) throw new Exception("Trade history record not found.");

                        $u1_items = array_filter(array_map('intval', explode(',', $trade['user1_items'])));
                        $u2_items = array_filter(array_map('intval', explode(',', $trade['user2_items'])));

                        foreach ($u1_items as $item_id) {
                            $chk = mysqli_query($link,
                                "SELECT id FROM user_items
                                 WHERE user_id = {$trade['user2_id']} AND item_id = $item_id LIMIT 1");
                            if (mysqli_num_rows($chk) == 0)
                                throw new Exception("Revert blocked: user2 no longer has item #$item_id.");
                        }
                        foreach ($u2_items as $item_id) {
                            $chk = mysqli_query($link,
                                "SELECT id FROM user_items
                                 WHERE user_id = {$trade['user1_id']} AND item_id = $item_id LIMIT 1");
                            if (mysqli_num_rows($chk) == 0)
                                throw new Exception("Revert blocked: user1 no longer has item #$item_id.");
                        }

                        foreach ($u1_items as $item_id) {
                            mysqli_query($link,
                                "UPDATE user_items SET user_id = {$trade['user1_id']}
                                 WHERE user_id = {$trade['user2_id']} AND item_id = $item_id LIMIT 1");
                        }
                        foreach ($u2_items as $item_id) {
                            mysqli_query($link,
                                "UPDATE user_items SET user_id = {$trade['user2_id']}
                                 WHERE user_id = {$trade['user1_id']} AND item_id = $item_id LIMIT 1");
                        }
                    }

                    mysqli_query($link,
                        "UPDATE revert_requests SET status = 'approved',
                         reviewed_by = $me, reviewed_at = NOW()
                         WHERE id = $request_id");

                    mysqli_commit($link);
                    $_SESSION['flash_admin_success'] = "Trade reverted successfully.";

                } catch (Exception $e) {
                    mysqli_rollback($link);
                    $_SESSION['flash_admin_error'] = "Revert failed: " . $e->getMessage();
                }

            } elseif ($action === 'deny') {
                mysqli_query($link,
                    "UPDATE revert_requests SET status = 'rejected',
                     reviewed_by = $me, reviewed_at = NOW()
                     WHERE id = $request_id");
                $_SESSION['flash_admin_success'] = "Revert request rejected.";
            }
        }

        elseif ($type === 'deletion') {

            if ($action === 'approve') {
                $rs = mysqli_prepare($link,
                    "SELECT user_id FROM deletion_requests WHERE id = ? AND status = 'pending'");
                mysqli_stmt_bind_param($rs, "i", $request_id);
                mysqli_stmt_execute($rs);
                $req = mysqli_fetch_assoc(mysqli_stmt_get_result($rs));
                mysqli_stmt_close($rs);

                if ($req) {
                    $uid = $req['user_id'];
                    mysqli_begin_transaction($link);
                    try {
                        mysqli_query($link,
                            "UPDATE market_listings SET status = 'cancelled'
                             WHERE user_id = $uid AND status = 'active'");

                        $ds = mysqli_prepare($link,
                            "UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                        mysqli_stmt_bind_param($ds, "i", $uid);
                        mysqli_stmt_execute($ds);
                        mysqli_stmt_close($ds);

                        mysqli_query($link,
                            "UPDATE deletion_requests SET status = 'approved',
                             reviewed_by = $me, reviewed_at = NOW()
                             WHERE id = $request_id");

                        mysqli_commit($link);
                        $_SESSION['flash_admin_success'] =
                            "Account soft-deleted. Data preserved.";
                    } catch (Exception $e) {
                        mysqli_rollback($link);
                        $_SESSION['flash_admin_error'] = "Failed to delete user: " . $e->getMessage();
                    }
                }

            } elseif ($action === 'deny') {
                mysqli_query($link,
                    "UPDATE deletion_requests SET status = 'rejected',
                     reviewed_by = $me, reviewed_at = NOW()
                     WHERE id = $request_id");
                $_SESSION['flash_admin_success'] = "Deletion request rejected. Account kept active.";
            }
        }

        elseif ($type === 'topup') {
            if ($action === 'approve') {
                $rs = mysqli_prepare($link, "SELECT user_id, amount FROM topup_requests WHERE id = ? AND status = 'pending' AND email_verified = 1");
                mysqli_stmt_bind_param($rs, "i", $request_id);
                mysqli_stmt_execute($rs);
                $req = mysqli_fetch_assoc(mysqli_stmt_get_result($rs));
                mysqli_stmt_close($rs);

                if ($req) {
                    $uid = $req['user_id'];
                    $amount = $req['amount'];
                    mysqli_begin_transaction($link);
                    try {
                        mysqli_query($link, "UPDATE users SET credits = credits + $amount WHERE id = $uid");
                        mysqli_query($link, "UPDATE topup_requests SET status = 'approved', reviewed_by = $me, reviewed_at = NOW() WHERE id = $request_id");
                        mysqli_commit($link);
                        $_SESSION['flash_admin_success'] = "Top-up request approved. Balance updated.";
                    } catch (Exception $e) {
                        mysqli_rollback($link);
                        $_SESSION['flash_admin_error'] = "Failed to approve top-up: " . $e->getMessage();
                    }
                }
            } elseif ($action === 'deny') {
                mysqli_query($link, "UPDATE topup_requests SET status = 'denied', reviewed_by = $me, reviewed_at = NOW() WHERE id = $request_id");
                $_SESSION['flash_admin_success'] = "Top-up request denied.";
            }
        }
    }

    header("Location: admin_requests.php?sub=$sub");
    exit;
}

$requests = [];
if ($sub === 'deletion') {
    $sql = "SELECT dr.id, dr.reason, dr.status, dr.created_at, dr.reviewed_at,
                   u.id AS user_id, u.name AS user_name, u.email AS user_email, u.picture,
                   a.name AS reviewed_by_name
            FROM deletion_requests dr
            JOIN users u  ON dr.user_id    = u.id
            LEFT JOIN users a ON dr.reviewed_by = a.id
            ORDER BY FIELD(dr.status,'pending','approved','denied'), dr.created_at DESC";
} elseif ($sub === 'topup') {
    $sql = "SELECT t.id, t.amount, t.status, t.created_at, t.reviewed_at,
                   u.id AS user_id, u.name AS user_name, u.email AS user_email, u.picture,
                   a.name AS reviewed_by_name
            FROM topup_requests t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.reviewed_by = a.id
            WHERE t.email_verified = 1
            ORDER BY FIELD(t.status,'pending','approved','denied'), t.created_at DESC";
} else {
    $sql = "SELECT rr.id, rr.type, rr.reference_id, rr.reason, rr.amount, rr.status,
                   rr.created_at, rr.reviewed_at,
                   u.id AS user_id, u.name AS user_name, u.email AS user_email,
                   a.name AS reviewed_by_name
            FROM revert_requests rr
            JOIN users u  ON rr.user_id    = u.id
            LEFT JOIN users a ON rr.reviewed_by = a.id
            ORDER BY FIELD(rr.status,'pending','approved','rejected'), rr.created_at DESC";
}
$stmt = mysqli_prepare($link, $sql);
if (!$stmt) { die("DB error: " . mysqli_error($link)); }
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) { $requests[] = $row; }
mysqli_stmt_close($stmt);

if ($sub === 'revert') {
    foreach ($requests as &$r) {
        $tdata = ['type' => $r['type']];

        if ($r['type'] === 'market') {
            $stmt = mysqli_prepare($link,
                "SELECT mh.*, i.name AS item_name, i.image, i.game, i.wear_rating, i.rarity,
                        b.name AS buyer_name, s.name AS seller_name
                 FROM market_history mh
                 JOIN items i ON mh.item_id   = i.id
                 JOIN users b ON mh.buyer_id  = b.id
                 JOIN users s ON mh.seller_id = s.id
                 WHERE mh.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $r['reference_id']);
            mysqli_stmt_execute($stmt);
            $trade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($trade) {
                $gi = req_game_info($trade['game']);
                $tdata['buyer_name']  = $trade['buyer_name'];
                $tdata['seller_name'] = $trade['seller_name'];
                $tdata['price']       = $trade['price'];
                $tdata['item']        = [
                    'name'      => $trade['item_name'],
                    'image'     => $trade['image'],
                    'wear'      => $trade['wear_rating'],
                    'rarity'    => $trade['rarity'],
                    'game_logo' => $gi['logo'],
                ];
                $chk = mysqli_query($link,
                    "SELECT id FROM user_items
                     WHERE user_id = {$trade['buyer_id']}
                       AND item_id = {$trade['item_id']}
                     LIMIT 1");
                $tdata['buyer_has_item'] = (mysqli_num_rows($chk) > 0);
                $tdata['buyer_id']  = $trade['buyer_id'];
                $tdata['seller_id'] = $trade['seller_id'];
            }

        } elseif ($r['type'] === 'trade') {
            $stmt = mysqli_prepare($link,
                "SELECT th.*, u1.name AS user1_name, u2.name AS user2_name
                 FROM trade_history th
                 JOIN users u1 ON th.user1_id = u1.id
                 JOIN users u2 ON th.user2_id = u2.id
                 WHERE th.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $r['reference_id']);
            mysqli_stmt_execute($stmt);
            $trade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($trade) {
                $tdata['user1_name'] = $trade['user1_name'];
                $tdata['user2_name'] = $trade['user2_name'];

                $get_items = function ($ids) use ($link) {
                    if (empty($ids)) return [];
                    $res = mysqli_query($link,
                        "SELECT id AS item_id, name, image, game, wear_rating, rarity
                         FROM items WHERE id IN ($ids)");
                    $arr = [];
                    while ($row = mysqli_fetch_assoc($res)) {
                        $gi = req_game_info($row['game']);
                        $row['game_logo'] = $gi['logo'];
                        $arr[] = $row;
                    }
                    return $arr;
                };

                $tdata['user1_items'] = $get_items($trade['user1_items']);
                $tdata['user2_items'] = $get_items($trade['user2_items']);

                $u1_ids  = array_filter(array_map('intval', explode(',', $trade['user1_items'])));
                $u2_ids  = array_filter(array_map('intval', explode(',', $trade['user2_items'])));
                $missing = [];
                foreach ($u1_ids as $iid) {
                    $c = mysqli_query($link,
                        "SELECT id FROM user_items
                         WHERE user_id = {$trade['user2_id']} AND item_id = $iid LIMIT 1");
                    if (mysqli_num_rows($c) == 0) $missing[] = $iid;
                }
                foreach ($u2_ids as $iid) {
                    $c = mysqli_query($link,
                        "SELECT id FROM user_items
                         WHERE user_id = {$trade['user1_id']} AND item_id = $iid LIMIT 1");
                    if (mysqli_num_rows($c) == 0) $missing[] = $iid;
                }
                $tdata['missing_items'] = $missing;
                $tdata['can_revert']    = empty($missing);
                $tdata['user1_id']      = $trade['user1_id'];
                $tdata['user2_id']      = $trade['user2_id'];
            }
        }
        $r['transaction_data'] = $tdata;
    }
    unset($r);
}

$pc = mysqli_query($link, "SELECT COUNT(*) FROM revert_requests WHERE status='pending'");
$pending_revert = mysqli_fetch_row($pc)[0];
$dc = mysqli_query($link, "SELECT COUNT(*) FROM deletion_requests WHERE status='pending'");
$pending_deletion = mysqli_fetch_row($dc)[0];
$tc = mysqli_query($link, "SELECT COUNT(*) FROM topup_requests WHERE status='pending' AND email_verified=1");
$pending_topup = mysqli_fetch_row($tc)[0];
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
        .flash { padding: 13px 18px; border-radius: var(--radius); font-size: 14px; margin-bottom: 22px; }
        .flash-success { background: rgba(60,184,120,.12);  border: 1px solid rgba(60,184,120,.35); color: #a0f0c0; }
        .flash-warn    { background: rgba(240,168,48,.12);  border: 1px solid rgba(240,168,48,.35); color: #ffd580; }
        .flash-error   { background: rgba(224,72,58,.12);   border: 1px solid rgba(224,72,58,.35);  color: #ffaaaa; }
        .req-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 32px; }
        .req-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); overflow: hidden; border-left: 4px solid var(--warn);
        }
        .req-card.blocked { border-left-color: var(--danger); }
        .req-head {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            background: var(--bg-card-2);
        }
        .req-head-info { flex: 1; }
        .req-head-user { font-family: var(--font-display); font-size: 16px; font-weight: 700; color: var(--text); }
        .req-head-sub  { font-size: 12px; color: var(--text-muted); }
        .req-body  { padding: 16px 20px; }
        .req-reason {
            background: var(--bg-dark); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 11px 14px;
            font-size: 13px; color: var(--text-dim); line-height: 1.6; margin-bottom: 14px;
        }
        .req-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .type-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; cursor: pointer; transition: filter .15s;
            border: none;
        }
        .type-chip:hover { filter: brightness(1.2); }
        .chip-market { background: rgba(74,159,212,.15); color: var(--accent);  border: 1px solid rgba(74,159,212,.3); }
        .chip-trade  { background: rgba(60,184,120,.15); color: var(--success); border: 1px solid rgba(60,184,120,.3); }
        .warn-strip {
            background: rgba(224,72,58,.1); border: 1px solid rgba(224,72,58,.3);
            border-radius: var(--radius); padding: 9px 14px; font-size: 12px;
            color: #ffaaaa; margin-bottom: 12px;
        }
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
        .reason-cell  { font-size: 13px; color: var(--text-dim); max-width: 220px; }
        .user-line    { font-weight: 600; color: var(--text); }
        .user-sub     { font-size: 11px; color: var(--text-muted); }
        .empty-state { padding: 60px; text-align: center; color: var(--text-dim); font-size: 15px; }
        .empty-state svg { display: block; margin: 0 auto 14px; opacity: .3; }
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.72);
            backdrop-filter: blur(4px); z-index: 300;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity .2s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal {
            background: var(--bg-card-2); border: 1px solid var(--border-light);
            border-radius: var(--radius-lg); padding: 32px; width: 90%; max-width: 660px;
            box-shadow: var(--shadow-lg); transform: scale(.95); transition: transform .2s;
            max-height: 90vh; overflow-y: auto;
        }
        .modal-overlay.open .modal { transform: scale(1); }
        .modal-title { font-family: var(--font-display); font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 22px; }
        .modal-footer { display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end; }
        .tx-item-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .tx-item-box {
            background: var(--bg-card); border: 1px dashed var(--border-light);
            border-radius: var(--radius); padding: 12px; text-align: center;
        }
        .tx-item-box img { width: 72px; height: 54px; object-fit: contain; margin-bottom: 8px; }
        .tx-item-name { font-family: var(--font-display); font-size: 13px; font-weight: 600; color: var(--text); }
        .tx-item-meta { font-size: 10px; color: var(--text-muted); margin-top: 4px; }
        .tx-blocked-item { border-color: var(--danger); background: rgba(224,72,58,.06); }
        .del-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); overflow: hidden; border-left: 4px solid var(--accent);
            margin-bottom: 14px;
        }
        .del-head {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            background: var(--bg-card-2);
        }
        .del-head-info { flex: 1; }
        .del-head-user { font-family: var(--font-display); font-size: 16px; font-weight: 700; color: var(--text); }
        .del-head-sub  { font-size: 12px; color: var(--text-muted); }
        .del-body  { padding: 16px 20px; }
        .u-avatar {
            width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--border); flex-shrink: 0; background: var(--bg-card-2);
            display: inline-flex; align-items: center; justify-content: center;
        }
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
    <a href="admin_requests.php?sub=topup" class="sub-nav-link <?= $sub === 'topup' ? 'active' : '' ?>">
        Top-up Requests
        <span class="sub-nav-badge <?= $pending_topup > 0 ? 'has-pending' : '' ?>"><?= $pending_topup ?> pending</span>
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
            <?php if ($sub === 'deletion'): ?>
                Deletion <span>Requests</span>
            <?php elseif ($sub === 'topup'): ?>
                Top-up <span>Requests</span>
            <?php else: ?>
                Revert <span>Requests</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($sub === 'revert'): ?>

        <?php if (empty($requests)): ?>
        <div class="table-card">
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8h16v10z"/></svg>
                No revert requests found.
            </div>
        </div>
        <?php else: ?>
        <div class="req-list">
        <?php foreach ($requests as $r):
            $td   = $r['transaction_data'] ?? ['type' => $r['type']];
            $type = $td['type'];

            $blocked      = false;
            $block_reason = '';
            if ($type === 'market' && isset($td['buyer_has_item']) && !$td['buyer_has_item']) {
                $blocked      = true;
                $block_reason = 'The buyer no longer has the received item.';
            } elseif ($type === 'trade' && isset($td['can_revert']) && !$td['can_revert']) {
                $blocked      = true;
                $block_reason = 'One or more items from this trade have been moved.';
            }
            $is_pending = ($r['status'] === 'pending');
        ?>
        <div class="req-card <?= $blocked ? 'blocked' : '' ?>">
            <div class="req-head">
                <div class="req-head-info">
                    <div class="req-head-user">
                        <?= htmlspecialchars($r['user_name']) ?>
                        <span style="font-size:12px;color:var(--text-muted);">ID #<?= $r['user_id'] ?></span>
                    </div>
                    <div class="req-head-sub">
                        Request #<?= $r['id'] ?> · <?= htmlspecialchars($r['user_email']) ?> · Submitted <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?>
                        <?php if (!empty($r['reviewed_by_name'])): ?>
                            · Reviewed by <?= htmlspecialchars($r['reviewed_by_name']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($is_pending): ?>
                <button class="type-chip <?= $type === 'market' ? 'chip-market' : 'chip-trade' ?>"
                        onclick='openTxModal(<?= json_encode($td, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    <?= strtoupper($type) ?> · Ref #<?= $r['reference_id'] ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                </button>
                <?php else: ?>
                <span class="type-chip <?= $type === 'market' ? 'chip-market' : 'chip-trade' ?>" style="cursor:default;">
                    <?= strtoupper($type) ?> · Ref #<?= $r['reference_id'] ?>
                </span>
                <?php endif; ?>

                <?php if (!$is_pending):
                    $sc = match($r['status']) { 'approved' => 'chip-approved', default => 'chip-denied' };
                ?>
                <span class="status-chip <?= $sc ?>"><?= ucfirst($r['status']) ?></span>
                <?php endif; ?>
            </div>
            <div class="req-body">
                <?php if (!empty($r['reason'])): ?>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--text-muted);margin-bottom:6px;">Reason</div>
                <div class="req-reason"><?= nl2br(htmlspecialchars($r['reason'])) ?></div>
                <?php endif; ?>

                <?php if ($r['amount']): ?>
                <div style="font-size:13px;color:var(--text-dim);margin-bottom:12px;">
                    Amount: <strong style="color:var(--accent);">$<?= number_format($r['amount'], 2) ?></strong>
                </div>
                <?php endif; ?>

                <?php if ($blocked && $is_pending): ?>
                <div class="warn-strip">⚠ <strong>Revert blocked:</strong> <?= htmlspecialchars($block_reason) ?></div>
                <?php endif; ?>

                <?php if ($is_pending): ?>
                <div class="req-actions">
                    <form method="POST" action="admin_requests.php?sub=revert">
                        <input type="hidden" name="action"     value="deny">
                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="req_type"   value="revert">
                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                    </form>
                    <form method="POST" action="admin_requests.php?sub=revert"
                          onsubmit="return <?= $blocked ? 'confirmBlockedRevert()' : "confirm('Revert this transaction?')" ?>;">
                        <input type="hidden" name="action"       value="approve">
                        <input type="hidden" name="request_id"   value="<?= $r['id'] ?>">
                        <input type="hidden" name="req_type"     value="revert">
                        <input type="hidden" name="tx_type"      value="<?= htmlspecialchars($type) ?>">
                        <input type="hidden" name="reference_id" value="<?= $r['reference_id'] ?>">
                        <button type="submit" class="btn <?= $blocked ? 'btn-ghost' : 'btn-success' ?> btn-sm">
                            <?= $blocked ? '⚠ Force Approve' : 'Approve Revert' ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php elseif ($sub === 'topup'): ?>

        <?php if (empty($requests)): ?>
        <div class="table-card">
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8h16v10z"/></svg>
                No top-up requests found.
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($requests as $t):
            $is_pending = ($t['status'] === 'pending');
        ?>
        <div class="del-card">
            <div class="del-head">
                <div class="del-head-info">
                    <div class="del-head-user">
                        <?php if (!empty($t['picture'])): ?>
                            <img src="<?= htmlspecialchars($t['picture']) ?>" class="u-avatar" alt="" style="vertical-align:middle;margin-right:8px;">
                        <?php endif; ?>
                        <?= htmlspecialchars($t['user_name']) ?>
                        <span style="font-size:12px;color:var(--text-muted);">ID #<?= $t['user_id'] ?></span>
                    </div>
                    <div class="del-head-sub">
                        Request #<?= $t['id'] ?> · <?= htmlspecialchars($t['user_email']) ?> · Submitted <?= date('M j, Y g:i A', strtotime($t['created_at'])) ?>
                        <?php if (!empty($t['reviewed_by_name'])): ?>
                            · Reviewed by <?= htmlspecialchars($t['reviewed_by_name']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$is_pending):
                    $sc = match($t['status']) { 'approved' => 'chip-approved', default => 'chip-denied' };
                ?>
                <span class="status-chip <?= $sc ?>"><?= ucfirst($t['status']) ?></span>
                <?php endif; ?>
            </div>
            <div class="del-body">
                <div style="font-size:13px;color:var(--text-dim);margin-bottom:12px;">
                    Requested Amount: <strong style="color:var(--accent);font-size:16px;">$<?= number_format($t['amount'], 2) ?></strong>
                </div>
                <?php if ($is_pending): ?>
                <div class="req-actions">
                    <form method="POST" action="admin_requests.php?sub=topup">
                        <input type="hidden" name="action"     value="deny">
                        <input type="hidden" name="request_id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="req_type"   value="topup">
                        <button type="submit" class="btn btn-ghost btn-sm">Deny</button>
                    </form>
                    <form method="POST" action="admin_requests.php?sub=topup"
                          onsubmit="return confirm('Approve this top-up?');">
                        <input type="hidden" name="action"     value="approve">
                        <input type="hidden" name="request_id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="req_type"   value="topup">
                        <button type="submit" class="btn btn-success btn-sm">Approve Top-up</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>

        <?php if (empty($requests)): ?>
        <div class="table-card">
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8h16v10z"/></svg>
                No deletion requests found.
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($requests as $d):
            $is_pending = ($d['status'] === 'pending');
        ?>
        <div class="del-card">
            <div class="del-head">
                <div class="del-head-info">
                    <div class="del-head-user">
                        <?php if (!empty($d['picture'])): ?>
                            <img src="<?= htmlspecialchars($d['picture']) ?>" class="u-avatar" alt="" style="vertical-align:middle;margin-right:8px;">
                        <?php endif; ?>
                        <?= htmlspecialchars($d['user_name']) ?>
                        <span style="font-size:12px;color:var(--text-muted);">ID #<?= $d['user_id'] ?></span>
                    </div>
                    <div class="del-head-sub">
                        Request #<?= $d['id'] ?> · <?= htmlspecialchars($d['user_email']) ?> · Submitted <?= date('M j, Y g:i A', strtotime($d['created_at'])) ?>
                        <?php if (!empty($d['reviewed_by_name'])): ?>
                            · Reviewed by <?= htmlspecialchars($d['reviewed_by_name']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$is_pending):
                    $sc = match($d['status']) { 'approved' => 'chip-approved', default => 'chip-denied' };
                ?>
                <span class="status-chip <?= $sc ?>"><?= ucfirst($d['status']) ?></span>
                <?php endif; ?>
            </div>
            <div class="del-body">
                <?php if (!empty($d['reason'])): ?>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--text-muted);margin-bottom:6px;">Reason</div>
                <div class="req-reason"><?= nl2br(htmlspecialchars($d['reason'])) ?></div>
                <?php endif; ?>
                <?php if ($is_pending): ?>
                <div class="req-actions">
                    <form method="POST" action="admin_requests.php?sub=deletion">
                        <input type="hidden" name="action"     value="deny">
                        <input type="hidden" name="request_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="req_type"   value="deletion">
                        <button type="submit" class="btn btn-ghost btn-sm">Reject</button>
                    </form>
                    <form method="POST" action="admin_requests.php?sub=deletion"
                          onsubmit="return confirm('Soft-delete this account?');">
                        <input type="hidden" name="action"     value="approve">
                        <input type="hidden" name="request_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="req_type"   value="deletion">
                        <button type="submit" class="btn btn-danger btn-sm">Approve Deletion</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

</div>

<div class="modal-overlay" id="txModal">
    <div class="modal">
        <div class="modal-title">Transaction Details</div>
        <div id="txModalContent"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeTxModal()">Close</button>
        </div>
    </div>
</div>

<script>
function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function openTxModal(data) {
    const content = document.getElementById('txModalContent');
    let html = '';

    if (data.type === 'market') {
        const price = parseFloat(data.price || 0).toFixed(2);
        html += `
        <div style="text-align:center;font-size:15px;color:var(--text);margin-bottom:20px;">
            <strong>${esc(data.buyer_name)}</strong>
            <span style="color:var(--text-muted);"> bought from </span>
            <strong>${esc(data.seller_name)}</strong>
            <span style="color:var(--text-muted);"> for </span>
            <strong style="color:var(--success);">$${price}</strong>
        </div>`;

        if (data.item) {
            const warned = data.buyer_has_item === false;
            const logoHtml = data.item.game_logo
                ? `<img src="${esc(data.item.game_logo)}" style="width:14px;height:14px;vertical-align:middle;margin-right:5px;">`
                : '';
            html += `
            <div style="display:flex;justify-content:center;">
                <div class="tx-item-box${warned ? ' tx-blocked-item' : ''}" style="width:180px;">
                    <img src="${esc(data.item.image)}" alt="">
                    <div class="tx-item-name">${logoHtml}${esc(data.item.name)}</div>
                    <div class="tx-item-meta">Wear: ${esc(data.item.wear || 'N/A')} · ${esc(data.item.rarity || 'N/A')}</div>
                    ${warned ? '<div style="font-size:11px;color:var(--danger);margin-top:6px;">⚠ Buyer no longer holds this item</div>' : ''}
                </div>
            </div>`;
        }

    } else if (data.type === 'trade') {
        html += `
        <div style="text-align:center;font-size:15px;color:var(--text);margin-bottom:20px;">
            Trade between <strong>${esc(data.user1_name)}</strong> and <strong>${esc(data.user2_name)}</strong>
        </div>`;

        const missing = data.missing_items || [];

        const renderItems = (items) => {
            if (!items || items.length === 0)
                return `<div style="color:var(--text-muted);font-size:13px;text-align:center;padding:16px 0;">Nothing</div>`;
            return items.map(item => {
                const isBlocked = missing.includes(item.item_id);
                const logo = item.game_logo
                    ? `<img src="${esc(item.game_logo)}" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;">`
                    : '';
                return `
                <div class="tx-item-box${isBlocked ? ' tx-blocked-item' : ''}">
                    <img src="${esc(item.image)}" alt="">
                    <div class="tx-item-name">${logo}${esc(item.name)}</div>
                    <div class="tx-item-meta">${esc(item.wear_rating || 'N/A')}</div>
                    ${isBlocked ? '<div style="font-size:10px;color:var(--danger);margin-top:4px;">⚠ Missing</div>' : ''}
                </div>`;
            }).join('');
        };

        html += `
        <div style="display:flex;gap:24px;">
            <div style="flex:1;">
                <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);text-align:center;margin-bottom:10px;">
                    ${esc(data.user1_name)} gave
                </div>
                <div class="tx-item-grid">${renderItems(data.user1_items)}</div>
            </div>
            <div style="flex:1;">
                <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);text-align:center;margin-bottom:10px;">
                    ${esc(data.user2_name)} gave
                </div>
                <div class="tx-item-grid">${renderItems(data.user2_items)}</div>
            </div>
        </div>`;

        if (!data.can_revert) {
            html += `<div class="warn-strip" style="margin-top:16px;">⚠ One or more items are missing from their expected holder.</div>`;
        }
    }

    content.innerHTML = html;
    document.getElementById('txModal').classList.add('open');
}

function closeTxModal() {
    document.getElementById('txModal').classList.remove('open');
}
document.getElementById('txModal').addEventListener('click', function(e) {
    if (e.target === this) closeTxModal();
});

function confirmBlockedRevert() {
    return confirm(
        '⚠ Warning: This revert is flagged as blocked because one or more items ' +
        'are no longer with their expected holder.\n\n' +
        'Continue anyway?'
    );
}
</script>
</body>
</html>
