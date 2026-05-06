<?php
require_once "admin_func.php";
$active_page = 'admin';

$current_tab      = $_GET['tab'] ?? 'users';
$users            = get_all_users($link);
$deleted_users    = get_deleted_users($link);
$revert_requests  = get_revert_requests($link);
$deletion_requests= get_deletion_requests($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        /* ── Layout ───────────────────────────────────────────────────────── */
        .admin-wrap { max-width: 1280px; margin: 0 auto; padding: 32px 40px; }

        /* ── Tab bar ──────────────────────────────────────────────────────── */
        .admin-tabs {
            display: flex; gap: 0; margin-bottom: 32px;
            border-bottom: 1px solid var(--border);
        }
        .tab-btn {
            background: none; border: none; border-bottom: 3px solid transparent;
            color: var(--text-dim); font-family: var(--font-display);
            font-size: 16px; font-weight: 700; cursor: pointer;
            padding: 12px 24px; text-transform: uppercase; letter-spacing: .5px;
            transition: color .2s, border-color .2s;
            display: flex; align-items: center; gap: 8px;
            margin-bottom: -1px;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active { color: #fff; border-bottom-color: var(--accent); }
        .tab-count {
            font-size: 11px; font-weight: 700; padding: 2px 7px;
            border-radius: 20px; background: var(--danger); color: #fff;
        }
        .tab-count.zero { background: var(--bg-card-2); color: var(--text-muted); border: 1px solid var(--border); }

        /* ── Flash alerts ─────────────────────────────────────────────────── */
        .flash { padding: 13px 18px; border-radius: var(--radius); font-size: 14px; margin-bottom: 24px; }
        .flash-success { background: rgba(60,184,120,.12); border: 1px solid rgba(60,184,120,.35); color: #a0f0c0; }
        .flash-error   { background: rgba(224,72,58,.12);  border: 1px solid rgba(224,72,58,.35);  color: #ffaaaa; }

        /* ── Section title ────────────────────────────────────────────────── */
        .section-title {
            font-family: var(--font-display); font-size: 22px; font-weight: 700;
            color: #fff; margin-bottom: 20px;
        }
        .section-title span { color: var(--accent); }

        /* ── Card table wrapper ───────────────────────────────────────────── */
        .table-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 32px;
        }
        .adm-table { width: 100%; border-collapse: collapse; }
        .adm-table th {
            text-align: left; padding: 13px 18px;
            font-family: var(--font-display); font-size: 13px; font-weight: 600;
            letter-spacing: .5px; color: var(--text-dim);
            border-bottom: 1px solid var(--border); background: var(--bg-card-2);
        }
        .adm-table td {
            padding: 0; border-bottom: 1px solid var(--border); vertical-align: middle;
        }
        .adm-table tr:last-child td { border-bottom: none; }
        .adm-table tr:hover td { background: rgba(255,255,255,.02); }
        .td-p { padding: 12px 18px; }

        /* ── User avatar ──────────────────────────────────────────────────── */
        .u-avatar {
            width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--border); flex-shrink: 0;
            background: var(--bg-card-2); display: flex; align-items: center; justify-content: center;
        }
        .u-row { display: flex; align-items: center; gap: 11px; }
        .u-name { font-family: var(--font-display); font-size: 15px; font-weight: 600; color: var(--text); }
        .u-sub  { font-size: 11px; color: var(--text-muted); }

        /* ── Role badge ───────────────────────────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; padding: 3px 9px;
            border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .badge-admin { background: rgba(240,168,48,.15); color: var(--warn);    border: 1px solid rgba(240,168,48,.3); }
        .badge-user  { background: var(--bg-card-2);     color: var(--text-muted); border: 1px solid var(--border); }

        /* ── Action group ─────────────────────────────────────────────────── */
        .act { display: flex; gap: 7px; align-items: center; flex-wrap: wrap; }

        /* ── Credits inline form ──────────────────────────────────────────── */
        .credits-form { display: flex; align-items: center; gap: 6px; }
        .credits-form input[type=number] {
            width: 88px; background: var(--bg-dark); border: 1px solid var(--border);
            border-radius: var(--radius); color: var(--text); font-size: 13px;
            font-weight: 700; padding: 5px 8px; outline: none; transition: border-color .2s;
        }
        .credits-form input[type=number]:focus { border-color: var(--accent); }

        /* ── Stat pills ───────────────────────────────────────────────────── */
        .stat-pills { display: flex; gap: 6px; flex-wrap: wrap; }
        .stat-pill {
            font-size: 11px; font-weight: 600; color: var(--text-dim);
            background: var(--bg-card-2); border: 1px solid var(--border);
            border-radius: 20px; padding: 2px 9px; white-space: nowrap;
        }

        /* ── Deleted-at stamp ─────────────────────────────────────────────── */
        .deleted-stamp { font-size: 11px; color: var(--danger); }

        /* ── Revert request cards ─────────────────────────────────────────── */
        .req-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 32px; }
        .req-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); overflow: hidden;
            border-left: 4px solid var(--warn);
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

        /* type chip */
        .type-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; cursor: pointer; transition: filter .15s;
        }
        .type-chip:hover { filter: brightness(1.2); }
        .chip-market { background: rgba(74,159,212,.15); color: var(--accent); border: 1px solid rgba(74,159,212,.3); }
        .chip-trade  { background: rgba(60,184,120,.15); color: var(--success); border: 1px solid rgba(60,184,120,.3); }

        /* warn strip */
        .warn-strip {
            background: rgba(224,72,58,.1); border: 1px solid rgba(224,72,58,.3);
            border-radius: var(--radius); padding: 9px 14px; font-size: 12px;
            color: #ffaaaa; margin-bottom: 12px;
        }

        /* ── Empty state ──────────────────────────────────────────────────── */
        .empty {
            padding: 48px; text-align: center; color: var(--text-muted); font-size: 14px;
        }

        /* ── Modals ───────────────────────────────────────────────────────── */
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
        .modal-title {
            font-family: var(--font-display); font-size: 22px; font-weight: 700;
            color: var(--text); margin-bottom: 22px;
        }
        .modal-footer { display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end; }

        /* tx modal wide */
        .modal-wide { max-width: 660px; }

        /* item card in tx modal */
        .tx-item-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .tx-item-box {
            background: var(--bg-card); border: 1px dashed var(--border-light);
            border-radius: var(--radius); padding: 12px; text-align: center;
        }
        .tx-item-box img { width: 72px; height: 54px; object-fit: contain; margin-bottom: 8px; }
        .tx-item-name { font-family: var(--font-display); font-size: 13px; font-weight: 600; color: var(--text); }
        .tx-item-meta { font-size: 10px; color: var(--text-muted); margin-top: 4px; }
        .tx-blocked-item { border-color: var(--danger); background: rgba(224,72,58,.06); }

        /* confirm overlay */
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
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="admin-wrap">

    <?php if (isset($_SESSION['flash_admin_success'])): ?>
        <div class="flash flash-success"><?= htmlspecialchars($_SESSION['flash_admin_success']); unset($_SESSION['flash_admin_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_admin_error'])): ?>
        <div class="flash flash-error"><?= htmlspecialchars($_SESSION['flash_admin_error']); unset($_SESSION['flash_admin_error']); ?></div>
    <?php endif; ?>

    <!-- Tab bar -->
    <div class="admin-tabs">
        <button class="tab-btn <?= $current_tab === 'users'     ? 'active' : '' ?>" onclick="showTab('users')">
            Manage Users
            <span class="tab-count zero"><?= count($users) ?></span>
        </button>
        <button class="tab-btn <?= $current_tab === 'deleted'   ? 'active' : '' ?>" onclick="showTab('deleted')">
            Undo Deleted
            <span class="tab-count <?= count($deleted_users) == 0 ? 'zero' : '' ?>"><?= count($deleted_users) ?></span>
        </button>
        <button class="tab-btn <?= $current_tab === 'reverts'   ? 'active' : '' ?>" onclick="showTab('reverts')">
            Revert Requests
            <span class="tab-count <?= count($revert_requests) == 0 ? 'zero' : '' ?>"><?= count($revert_requests) ?></span>
        </button>
        <button class="tab-btn <?= $current_tab === 'deletions' ? 'active' : '' ?>" onclick="showTab('deletions')">
            Deletion Requests
            <span class="tab-count <?= count($deletion_requests) == 0 ? 'zero' : '' ?>"><?= count($deletion_requests) ?></span>
        </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: Manage Users                                                   -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="users-tab" class="tab-content" style="display:<?= $current_tab === 'users' ? 'block' : 'none' ?>;">
        <div class="section-title">Manage <span>Users</span></div>
        <div class="table-card">
            <?php if (empty($users)): ?>
                <div class="empty">No active users.</div>
            <?php else: ?>
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Items</th>
                        <th>Credits</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="td-p">
                            <div class="u-row">
                                <?php if (!empty($u['picture'])): ?>
                                    <img src="<?= htmlspecialchars($u['picture']) ?>" class="u-avatar" alt="">
                                <?php else: ?>
                                    <div class="u-avatar">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="color:var(--text-muted)"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="u-name"><?= htmlspecialchars($u['name']) ?></div>
                                    <div class="u-sub"><?= htmlspecialchars($u['email'] ?? '') ?> · ID #<?= $u['id'] ?></div>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="td-p">
                            <?php if ($u['id'] == $user_id): ?>
                                <span class="badge badge-admin">You</span>
                            <?php elseif ($u['is_admin']): ?>
                                <span class="badge badge-admin">Admin</span>
                            <?php else: ?>
                                <span class="badge badge-user">User</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="td-p">
                            <span class="stat-pill">🗂 <?= $u['item_count'] ?> items</span>
                        </div>
                    </td>
                    <td>
                        <div class="td-p">
                            <form method="POST" action="admin_func.php" class="credits-form">
                                <input type="hidden" name="action"    value="update_credits">
                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                <span style="color:var(--accent);font-weight:700;font-size:13px;">$</span>
                                <input type="number" name="credits" step="0.01" min="0"
                                       value="<?= number_format($u['credits'], 2, '.', '') ?>">
                                <button type="submit" class="btn btn-accent btn-sm" title="Save">✓</button>
                            </form>
                        </div>
                    </td>
                    <td>
                        <div class="td-p">
                            <div class="act">
                                <button class="btn btn-ghost btn-sm"
                                        onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    Edit
                                </button>
                                <?php if ($u['id'] != $user_id): ?>
                                <button class="btn btn-danger btn-sm"
                                        onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                    Delete
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: Undo Deleted                                                   -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="deleted-tab" class="tab-content" style="display:<?= $current_tab === 'deleted' ? 'block' : 'none' ?>;">
        <div class="section-title">Undo <span>Deleted</span> Accounts</div>
        <p style="font-size:14px;color:var(--text-dim);margin-bottom:20px;">
            All deleted accounts are soft-deleted — inventory, listings, and offer history are fully preserved.
            Restoring re-activates the account immediately.
        </p>
        <div class="table-card">
            <?php if (empty($deleted_users)): ?>
                <div class="empty">No deleted accounts.</div>
            <?php else: ?>
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Data Snapshot</th>
                        <th>Credits</th>
                        <th>Deleted At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deleted_users as $u): ?>
                <tr>
                    <td>
                        <div class="td-p">
                            <div class="u-row">
                                <?php if (!empty($u['picture'])): ?>
                                    <img src="<?= htmlspecialchars($u['picture']) ?>" class="u-avatar" alt="">
                                <?php else: ?>
                                    <div class="u-avatar">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="color:var(--text-muted)"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="u-name"><?= htmlspecialchars($u['name']) ?></div>
                                    <div class="u-sub"><?= htmlspecialchars($u['email'] ?? '') ?> · ID #<?= $u['id'] ?></div>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="td-p">
                            <div class="stat-pills">
                                <span class="stat-pill">🗂 <?= $u['item_count'] ?> items</span>
                                <span class="stat-pill">🛒 <?= $u['listing_count'] ?> listings</span>
                                <span class="stat-pill">🤝 <?= $u['offer_count'] ?> offers</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="td-p">
                            <span style="color:var(--accent);font-weight:700;">$<?= number_format($u['credits'], 2) ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="td-p">
                            <span class="deleted-stamp"><?= htmlspecialchars($u['deleted_at']) ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="td-p">
                            <form method="POST" action="admin_func.php">
                                <input type="hidden" name="action"    value="restore_user">
                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">
                                    ↩ Restore
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: Revert Requests                                                -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="reverts-tab" class="tab-content" style="display:<?= $current_tab === 'reverts' ? 'block' : 'none' ?>;">
        <div class="section-title">Revert <span>Requests</span></div>
        <?php if (empty($revert_requests)): ?>
            <div class="table-card"><div class="empty">No pending revert requests.</div></div>
        <?php else: ?>
        <div class="req-list">
            <?php foreach ($revert_requests as $r):
                $td   = $r['transaction_data'];
                $type = $td['type'];

                // Determine if this request is blocked (cannot be reverted)
                $blocked = false;
                $block_reason = '';
                if ($type === 'market' && isset($td['buyer_has_item']) && !$td['buyer_has_item']) {
                    $blocked = true;
                    $block_reason = 'The buyer no longer has the received item — it may have been re-traded or sold.';
                } elseif ($type === 'trade' && isset($td['can_revert']) && !$td['can_revert']) {
                    $blocked = true;
                    $block_reason = 'One or more items from this trade have been moved — full revert is no longer possible.';
                }
            ?>
            <div class="req-card <?= $blocked ? 'blocked' : '' ?>">
                <div class="req-head">
                    <div class="req-head-info">
                        <div class="req-head-user"><?= htmlspecialchars($r['username']) ?> <span style="font-size:12px;color:var(--text-muted);">ID #<?= $r['user_id'] ?></span></div>
                        <div class="req-head-sub">Request #<?= $r['id'] ?> · Submitted <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></div>
                    </div>
                    <!-- Type chip opens detail modal -->
                    <button class="type-chip <?= $type === 'market' ? 'chip-market' : 'chip-trade' ?>"
                            onclick='openTxModal(<?= json_encode($td, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <?= strtoupper($type) ?> · Ref #<?= $r['reference_id'] ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    </button>
                </div>
                <div class="req-body">
                    <?php if (!empty($r['reason'])): ?>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--text-muted);margin-bottom:6px;">Reason</div>
                    <div class="req-reason"><?= nl2br(htmlspecialchars($r['reason'])) ?></div>
                    <?php endif; ?>

                    <?php if ($blocked): ?>
                    <div class="warn-strip">⚠ <strong>Revert blocked:</strong> <?= htmlspecialchars($block_reason) ?></div>
                    <?php endif; ?>

                    <div class="req-actions">
                        <form method="POST" action="admin_func.php">
                            <input type="hidden" name="action"       value="reject_revert">
                            <input type="hidden" name="request_id"   value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                        </form>
                        <form method="POST" action="admin_func.php"
                              onsubmit="return <?= $blocked ? 'confirmBlockedRevert()' : 'confirm(\'Revert this transaction?\')' ?>;">
                            <input type="hidden" name="action"       value="approve_revert">
                            <input type="hidden" name="request_id"   value="<?= $r['id'] ?>">
                            <input type="hidden" name="type"         value="<?= htmlspecialchars($type) ?>">
                            <input type="hidden" name="reference_id" value="<?= $r['reference_id'] ?>">
                            <button type="submit" class="btn <?= $blocked ? 'btn-ghost' : 'btn-success' ?> btn-sm">
                                <?= $blocked ? '⚠ Force Approve' : 'Approve Revert' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: Deletion Requests                                              -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="deletions-tab" class="tab-content" style="display:<?= $current_tab === 'deletions' ? 'block' : 'none' ?>;">
        <div class="section-title">Deletion <span>Requests</span></div>
        <p style="font-size:14px;color:var(--text-dim);margin-bottom:20px;">
            Approving will <strong>soft-delete</strong> the account — all inventory, listings (paused), and offer history are preserved.
            The account can be fully restored from the <em>Undo Deleted</em> tab.
        </p>
        <?php if (empty($deletion_requests)): ?>
            <div class="table-card"><div class="empty">No pending deletion requests.</div></div>
        <?php else: ?>
        <div class="req-list">
            <?php foreach ($deletion_requests as $d): ?>
            <div class="req-card">
                <div class="req-head">
                    <div class="req-head-info">
                        <div class="req-head-user">
                            <?php if (!empty($d['picture'])): ?>
                                <img src="<?= htmlspecialchars($d['picture']) ?>" class="u-avatar" alt="" style="display:inline-block;vertical-align:middle;margin-right:8px;">
                            <?php endif; ?>
                            <?= htmlspecialchars($d['username']) ?>
                            <span style="font-size:12px;color:var(--text-muted);">ID #<?= $d['user_id'] ?></span>
                        </div>
                        <div class="req-head-sub">
                            Request #<?= $d['id'] ?> · <?= htmlspecialchars($d['email'] ?? '') ?> · Submitted <?= date('M j, Y g:i A', strtotime($d['created_at'])) ?>
                        </div>
                    </div>
                </div>
                <div class="req-body">
                    <?php if (!empty($d['reason'])): ?>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--text-muted);margin-bottom:6px;">Reason</div>
                    <div class="req-reason"><?= nl2br(htmlspecialchars($d['reason'])) ?></div>
                    <?php endif; ?>
                    <div class="req-actions">
                        <form method="POST" action="admin_func.php">
                            <input type="hidden" name="action"     value="reject_deletion">
                            <input type="hidden" name="request_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm">Reject — Keep Account</button>
                        </form>
                        <form method="POST" action="admin_func.php"
                              onsubmit="return confirm('Soft-delete this account? All data will be preserved and restorable.');">
                            <input type="hidden" name="action"     value="approve_deletion">
                            <input type="hidden" name="request_id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="target_id"  value="<?= $d['user_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Approve Deletion</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.admin-wrap -->

<!-- ── Edit User Modal ──────────────────────────────────────────────────── -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-title">Edit User</div>
        <form method="POST" action="admin_func.php">
            <input type="hidden" name="action"    value="edit_user">
            <input type="hidden" name="target_id" id="editUserId">
            <div class="field-group">
                <label class="field-label">Username</label>
                <input type="text" name="username" id="editUsername" class="field-input" required>
            </div>
            <div class="field-group">
                <label class="field-label">Email</label>
                <input type="email" name="email" id="editEmail" class="field-input" required>
            </div>
            <div class="field-group">
                <label class="field-label">Credits</label>
                <input type="number" step="0.01" name="credits" id="editCredits" class="field-input" required>
            </div>
            <div class="field-group" style="flex-direction:row;align-items:center;gap:10px;">
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

<!-- ── Transaction Detail Modal ─────────────────────────────────────────── -->
<div class="modal-overlay" id="txModal">
    <div class="modal modal-wide">
        <div class="modal-title">Transaction Details</div>
        <div id="txModalContent"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeTxModal()">Close</button>
        </div>
    </div>
</div>

<!-- ── Soft-delete confirm overlay ──────────────────────────────────────── -->
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
            <form id="delete-form" method="POST" action="admin_func.php">
                <input type="hidden" name="action"    value="delete_user">
                <input type="hidden" name="target_id" id="delete-uid">
                <button type="submit" class="btn btn-danger">Yes, Soft-Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
// ── Tab switching ──────────────────────────────────────────────────────────
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(name + '-tab').style.display = 'block';
    const btn = [...document.querySelectorAll('.tab-btn')]
        .find(b => b.getAttribute('onclick')?.includes(name));
    if (btn) btn.classList.add('active');
    const url = new URL(window.location);
    url.searchParams.set('tab', name);
    window.history.pushState({}, '', url);
}

// ── Edit User modal ────────────────────────────────────────────────────────
function openEditModal(u) {
    document.getElementById('editUserId').value   = u.id;
    document.getElementById('editUsername').value = u.name;
    document.getElementById('editEmail').value    = u.email;
    document.getElementById('editCredits').value  = u.credits;
    document.getElementById('editIsAdmin').checked= u.is_admin == 1;
    document.getElementById('editUserModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editUserModal').classList.remove('open');
}
document.getElementById('editUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

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

// ── Blocked revert warning ─────────────────────────────────────────────────
function confirmBlockedRevert() {
    return confirm(
        '⚠ Warning: This revert is flagged as blocked because one or more items ' +
        'are no longer with their expected holder.\n\n' +
        'The server will still perform a final check and reject it if items are missing. ' +
        'Continue anyway?'
    );
}

// ── Transaction detail modal ───────────────────────────────────────────────
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

        const renderItems = (items, label) => {
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
            html += `<div class="warn-strip" style="margin-top:16px;">⚠ One or more items are missing from their expected holder. Highlighted items above cannot be returned.</div>`;
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

function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
</body>
</html>