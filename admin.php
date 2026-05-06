<?php
require_once "admin_func.php";
$active_page = 'admin';

$current_tab = $_GET['tab'] ?? 'users';

$users = get_all_users($link);
$revert_requests = get_revert_requests($link);
$deletion_requests = get_deletion_requests($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard — <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .admin-tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-dim);
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            padding: 8px 16px;
            text-transform: uppercase;
            transition: color 0.2s;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active { color: var(--accent); border-bottom: 2px solid var(--accent); margin-bottom: -16px; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: var(--bg-card-2);
            font-family: var(--font-display);
            font-size: 16px;
            color: var(--text-dim);
            text-transform: uppercase;
        }
        td {
            font-size: 14px;
            color: var(--text);
        }
        .action-btns {
            display: flex;
            gap: 10px;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page">
    <div class="admin-container">
        <h1 style="font-family:var(--font-display); font-size: 32px; margin-bottom:24px;">Admin Dashboard</h1>
        
        <?php if(isset($_SESSION['flash_admin_success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['flash_admin_success']; unset($_SESSION['flash_admin_success']); ?></div>
        <?php endif; ?>
        <?php if(isset($_SESSION['flash_admin_error'])): ?>
            <div class="alert alert-error"><?= $_SESSION['flash_admin_error']; unset($_SESSION['flash_admin_error']); ?></div>
        <?php endif; ?>

        <div class="admin-tabs">
            <button class="tab-btn <?= $current_tab === 'users' ? 'active' : '' ?>" onclick="showTab('users')">Manage Users</button>
            <button class="tab-btn <?= $current_tab === 'reverts' ? 'active' : '' ?>" onclick="showTab('reverts')">Revert Requests (<?= count($revert_requests) ?>)</button>
            <button class="tab-btn <?= $current_tab === 'deletions' ? 'active' : '' ?>" onclick="showTab('deletions')">Deletion Requests (<?= count($deletion_requests) ?>)</button>
        </div>

        <div id="users-tab" class="tab-content" style="<?= $current_tab === 'users' ? 'display:block;' : 'display:none;' ?>">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Credits</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>$<?= number_format($u['credits'], 2) ?></td>
                        <td><?= $u['is_admin'] ? 'Admin' : 'User' ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-ghost btn-sm" onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                <?php if($u['id'] != $user_id): ?>
                                <form method="POST" action="admin_func.php" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="reverts-tab" class="tab-content" style="<?= $current_tab === 'reverts' ? 'display:block;' : 'display:none;' ?>">
            <table>
                <thead>
                    <tr>
                        <th>Req ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Ref ID</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($revert_requests)): ?>
                        <tr><td colspan="6" style="text-align:center;">No pending requests.</td></tr>
                    <?php else: foreach($revert_requests as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['username']) ?><br><span style="font-size:11px;color:var(--text-dim);">ID: <?= $r['user_id'] ?></span></td>
                        <td>
                            <button type="button" 
                                    onclick='openTransactionModal(<?= json_encode($r['transaction_data'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                    style="background:transparent; border:1px solid var(--accent); color:var(--accent); text-transform:uppercase; font-size:11px; font-weight:700; padding:4px 8px; border-radius:4px; cursor:pointer; transition:0.2s;"
                                    onmouseover="this.style.background='rgba(74, 159, 212, 0.1)'"
                                    onmouseout="this.style.background='transparent'">
                                <?= $r['type'] ?>
                            </button>
                        </td>
                        <td><?= $r['reference_id'] ?></td>
                        <td style="max-width: 300px; word-wrap: break-word;"><?= htmlspecialchars($r['reason']) ?></td>
                        <td>
                            <div class="action-btns">
                                <form method="POST" action="admin_func.php" onsubmit="return confirm('Are you sure you want to revert this transaction?');">
                                    <input type="hidden" name="action" value="approve_revert">
                                    <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="type" value="<?= $r['type'] ?>">
                                    <input type="hidden" name="reference_id" value="<?= $r['reference_id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                </form>
                                <form method="POST" action="admin_func.php">
                                    <input type="hidden" name="action" value="reject_revert">
                                    <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div id="deletions-tab" class="tab-content" style="<?= $current_tab === 'deletions' ? 'display:block;' : 'display:none;' ?>">
            <table>
                <thead>
                    <tr>
                        <th>Req ID</th>
                        <th>User</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($deletion_requests)): ?>
                        <tr><td colspan="5" style="text-align:center;">No pending requests.</td></tr>
                    <?php else: foreach($deletion_requests as $d): ?>
                    <tr>
                        <td><?= $d['id'] ?></td>
                        <td><?= htmlspecialchars($d['username']) ?> (ID: <?= $d['user_id'] ?>)</td>
                        <td style="max-width: 300px; word-wrap: break-word;"><?= htmlspecialchars($d['reason']) ?></td>
                        <td><?= date('M j, Y g:i A', strtotime($d['created_at'])) ?></td>
                        <td>
                            <div class="action-btns">
                                <form method="POST" action="admin_func.php" onsubmit="return confirm('Are you sure you want to approve this request and permanently delete this user?');">
                                    <input type="hidden" name="action" value="approve_deletion">
                                    <input type="hidden" name="request_id" value="<?= $d['id'] ?>">
                                    <input type="hidden" name="target_id" value="<?= $d['user_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Approve & Delete</button>
                                </form>
                                <form method="POST" action="admin_func.php">
                                    <input type="hidden" name="action" value="reject_deletion">
                                    <input type="hidden" name="request_id" value="<?= $d['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-title">Edit User</div>
        <form method="POST" action="admin_func.php">
            <input type="hidden" name="action" value="edit_user">
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
            <div class="field-group" style="flex-direction:row; align-items:center; gap:10px;">
                <input type="checkbox" name="is_admin" id="editIsAdmin" value="1" style="width:16px; height:16px; accent-color:var(--accent);">
                <label class="field-label" style="margin:0;">Grant Admin Privileges</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeEditUserModal()">Cancel</button>
                <button type="submit" class="btn btn-accent">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="transactionModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-title">Transaction Details</div>
        <div id="transactionModalContent" style="margin-bottom: 20px;"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeTransactionModal()">Close</button>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabName + '-tab').style.display = 'block';
    
    // Find the button that was clicked or matching the tabName and make it active
    const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick').includes(tabName));
    if(activeBtn) activeBtn.classList.add('active');
    
    // Update the URL without reloading the page
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function openEditUserModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUsername').value = user.name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editCredits').value = user.credits;
    document.getElementById('editIsAdmin').checked = user.is_admin == 1;
    document.getElementById('editUserModal').classList.add('open');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.remove('open');
}

function openTransactionModal(data) {
    const modal = document.getElementById('transactionModal');
    const content = document.getElementById('transactionModalContent');
    
    let html = '';
    if (data.type === 'market') {
        html += `<div style="margin-bottom:20px; font-size:15px; color:var(--text); text-align:center;"><strong>${data.buyer_name}</strong> bought from <strong>${data.seller_name}</strong> for <strong style="color:var(--success);">$${parseFloat(data.price).toFixed(2)}</strong></div>`;
        if (data.item) {
            const logoHtml = data.item.game_logo ? `<img src="${data.item.game_logo}" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;">` : '';
            html += `
            <div style="display:flex; justify-content:center;">
                <div style="background:var(--bg-card-2); border:1px dashed var(--border-light); padding:16px; border-radius:12px; text-align:center; width:200px;">
                    <img src="${data.item.image}" style="width:100px;height:100px;object-fit:contain;margin-bottom:12px;">
                    <div style="font-weight:700;font-size:14px;color:#fff;line-height:1.2;">${logoHtml}${data.item.name}</div>
                    <div style="font-size:11px;color:var(--text-dim);margin-top:8px;">Wear: ${data.item.wear || 'N/A'}</div>
                    <div style="font-size:11px;color:var(--text-dim);margin-top:2px;">Rarity: ${data.item.rarity || 'N/A'}</div>
                </div>
            </div>`;
        } else {
            html += `<div style="text-align:center; color:var(--danger); font-size:12px;">Item data not found.</div>`;
        }
    } else if (data.type === 'trade') {
        html += `<div style="margin-bottom:20px; font-size:15px; color:var(--text); text-align:center;">Trade between <strong>${data.user1_name}</strong> and <strong>${data.user2_name}</strong></div>`;
        
        const renderItems = (items) => {
            if(!items || items.length === 0) return `<div style="color:var(--text-muted);font-size:13px;grid-column:1/-1;text-align:center;padding:20px 0;">Nothing</div>`;
            return items.map(item => {
                const logoHtml = item.game_logo ? `<img src="${item.game_logo}" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;">` : '';
                return `
                <div style="background:var(--bg-card-2); border:1px dashed var(--border-light); padding:10px; border-radius:8px; text-align:center; display:flex; flex-direction:column; align-items:center;">
                    <img src="${item.image}" style="width:60px;height:60px;object-fit:contain;margin-bottom:8px;">
                    <div style="font-weight:700;font-size:11px;color:#fff;line-height:1.2;">${logoHtml}${item.name}</div>
                    <div style="font-size:10px;color:var(--text-dim);margin-top:4px;">${item.wear || 'N/A'}</div>
                </div>
                `;
            }).join('');
        };

        html += `
        <div style="display:flex; gap:30px;">
            <div style="flex:1;">
                <div style="font-size:13px; color:var(--text-dim); margin-bottom:12px; text-transform:uppercase; font-weight:700; text-align:center;">${data.user1_name} Lost:</div>
                <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:12px;">
                    ${renderItems(data.user1_items)}
                </div>
            </div>
            <div style="flex:1;">
                <div style="font-size:13px; color:var(--text-dim); margin-bottom:12px; text-transform:uppercase; font-weight:700; text-align:center;">${data.user2_name} Lost:</div>
                <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:12px;">
                    ${renderItems(data.user2_items)}
                </div>
            </div>
        </div>
        `;
    }
    
    content.innerHTML = html;
    modal.classList.add('open');
}

function closeTransactionModal() {
    document.getElementById('transactionModal').classList.remove('open');
}
</script>
</body>
</html>