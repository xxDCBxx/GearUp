<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION["id"];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'request_revert') {
    $type = $_POST['type'] ?? '';
    $reference_id = (int)($_POST['reference_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if (in_array($type, ['market', 'trade']) && $reference_id > 0 && !empty($reason)) {
        $stmt = mysqli_prepare($link, "INSERT INTO revert_requests (user_id, type, reference_id, reason) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isis", $user_id, $type, $reference_id, $reason);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $success_msg = "Revert request submitted successfully.";
    }
}

// Fetch Market History
$market_sql = "SELECT mh.*, i.name as item_name, i.image as item_image, i.game,
               b.name as buyer_name, s.name as seller_name,
               rr.id as revert_request_id, rr.status as revert_status
               FROM market_history mh
               JOIN items i ON mh.item_id = i.id
               JOIN users b ON mh.buyer_id = b.id
               JOIN users s ON mh.seller_id = s.id
               LEFT JOIN revert_requests rr ON rr.reference_id = mh.id AND rr.type = 'market'
               WHERE mh.buyer_id = ? OR mh.seller_id = ?
               ORDER BY mh.created_at DESC";
$stmt = mysqli_prepare($link, $market_sql);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt);
$market_res = mysqli_stmt_get_result($stmt);
$market_history = mysqli_fetch_all($market_res, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Fetch Trade History
$trade_sql = "SELECT th.*, u1.name as user1_name, u2.name as user2_name,
              rr.id as revert_request_id, rr.status as revert_status
              FROM trade_history th
              JOIN users u1 ON th.user1_id = u1.id
              JOIN users u2 ON th.user2_id = u2.id
              LEFT JOIN revert_requests rr ON rr.reference_id = th.id AND rr.type = 'trade'
              WHERE th.user1_id = ? OR th.user2_id = ?
              ORDER BY th.created_at DESC";
$stmt = mysqli_prepare($link, $trade_sql);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt);
$trade_res = mysqli_stmt_get_result($stmt);
$trade_history = mysqli_fetch_all($trade_res, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

function get_items_by_ids($link, $ids_string) {
    if (empty($ids_string)) return [];
    $ids = array_filter(array_map('intval', explode(',', $ids_string)));
    if (empty($ids)) return [];
    $in_clause = implode(',', $ids);
    $res = mysqli_query($link, "SELECT id, name, image FROM items WHERE id IN ($in_clause)");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

$active_page = 'history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction History — <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .history-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .history-tabs {
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
        
        .history-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .history-info { flex: 1; }
        .history-date { font-size: 12px; color: var(--text-dim); margin-bottom: 8px; }
        .history-title { font-family: var(--font-display); font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 4px; }
        .history-desc { font-size: 14px; color: var(--text-muted); }
        .price-tag { font-family: var(--font-display); font-size: 20px; font-weight: 700; color: var(--success); }
        .price-tag.negative { color: var(--danger); }
        
        .trade-items { display: flex; gap: 10px; margin-top: 10px; }
        .trade-item-img { width: 40px; height: 40px; background: var(--bg-card-2); border-radius: 4px; padding: 4px; object-fit: contain; }
        
        .btn-revert {
            background: transparent;
            border: 1px solid var(--warn);
            color: var(--warn);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-revert:hover { background: rgba(240, 168, 48, 0.1); }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page">
    <div class="history-container">
        <h1 style="font-family:var(--font-display); font-size: 32px; margin-bottom:24px;">Transaction History</h1>
        
        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success"><?= $success_msg ?></div>
        <?php endif; ?>

        <div class="history-tabs">
            <button class="tab-btn active" onclick="showTab('market')">Market Purchases</button>
            <button class="tab-btn" onclick="showTab('trades')">Trade Offers</button>
        </div>

        <div id="market-tab" class="tab-content">
            <?php if(empty($market_history)): ?>
                <div class="empty-state">No market history found.</div>
            <?php else: ?>
                <?php foreach($market_history as $mh): 
                    $is_buyer = ($mh['buyer_id'] == $user_id);
                ?>
                <div class="history-card">
                    <div class="history-info">
                        <div class="history-date"><?= date('M j, Y g:i A', strtotime($mh['created_at'])) ?></div>
                        <div class="history-title" style="display:flex; align-items:center; gap:10px;">
                            <img src="<?= $mh['item_image'] ?>" style="width:30px; height:30px; object-fit:contain;">
                            <?= htmlspecialchars($mh['item_name']) ?>
                        </div>
                        <div class="history-desc">
                            <?= $is_buyer ? 'Bought from ' . htmlspecialchars($mh['seller_name']) : 'Sold to ' . htmlspecialchars($mh['buyer_name']) ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div class="price-tag <?= $is_buyer ? 'negative' : '' ?>">
                            <?= $is_buyer ? '-' : '+' ?>$<?= number_format($mh['price'], 2) ?>
                        </div>
                        <?php if(!empty($mh['revert_request_id'])): ?>
                            <div style="margin-top:10px; font-size:12px; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Revert: <span style="color:<?= $mh['revert_status'] === 'approved' ? 'var(--success)' : ($mh['revert_status'] === 'rejected' ? 'var(--danger)' : 'var(--warn)') ?>"><?= $mh['revert_status'] ?></span></div>
                        <?php else: ?>
                            <button class="btn-revert" style="margin-top:10px;" onclick="openRevertModal('market', <?= $mh['id'] ?>)">Request Revert</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="trades-tab" class="tab-content" style="display:none;">
            <?php if(empty($trade_history)): ?>
                <div class="empty-state">No trade history found.</div>
            <?php else: ?>
                <?php foreach($trade_history as $th): 
                    $is_user1 = ($th['user1_id'] == $user_id);
                    $partner_name = $is_user1 ? $th['user2_name'] : $th['user1_name'];
                    $my_items_str = $is_user1 ? $th['user1_items'] : $th['user2_items'];
                    $their_items_str = $is_user1 ? $th['user2_items'] : $th['user1_items'];
                    
                    $my_items = get_items_by_ids($link, $my_items_str);
                    $their_items = get_items_by_ids($link, $their_items_str);
                ?>
                <div class="history-card" style="flex-direction:column; align-items:flex-start;">
                    <div style="display:flex; justify-content:space-between; width:100%; margin-bottom:15px;">
                        <div>
                            <div class="history-date"><?= date('M j, Y g:i A', strtotime($th['created_at'])) ?></div>
                            <div class="history-title">Trade with <?= htmlspecialchars($partner_name) ?></div>
                        </div>
                        <?php if(!empty($th['revert_request_id'])): ?>
                            <div style="font-size:12px; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Revert: <span style="color:<?= $th['revert_status'] === 'approved' ? 'var(--success)' : ($th['revert_status'] === 'rejected' ? 'var(--danger)' : 'var(--warn)') ?>"><?= $th['revert_status'] ?></span></div>
                        <?php else: ?>
                            <button class="btn-revert" onclick="openRevertModal('trade', <?= $th['id'] ?>)">Request Revert</button>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display:flex; gap:40px; width:100%;">
                        <div>
                            <div style="font-size:12px; color:var(--text-dim); margin-bottom:5px;">You Lost:</div>
                            <div class="trade-items">
                                <?php foreach($my_items as $item): ?>
                                    <img src="<?= $item['image'] ?>" class="trade-item-img" title="<?= htmlspecialchars($item['name']) ?>">
                                <?php endforeach; ?>
                                <?php if(empty($my_items)) echo '<span style="color:var(--text-muted); font-size:13px;">Nothing</span>'; ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:12px; color:var(--text-dim); margin-bottom:5px;">You Received:</div>
                            <div class="trade-items">
                                <?php foreach($their_items as $item): ?>
                                    <img src="<?= $item['image'] ?>" class="trade-item-img" title="<?= htmlspecialchars($item['name']) ?>">
                                <?php endforeach; ?>
                                <?php if(empty($their_items)) echo '<span style="color:var(--text-muted); font-size:13px;">Nothing</span>'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal-overlay" id="revertModal">
    <div class="modal">
        <div class="modal-title">Request Reversion</div>
        <form method="POST">
            <input type="hidden" name="action" value="request_revert">
            <input type="hidden" name="type" id="revertType">
            <input type="hidden" name="reference_id" id="revertId">
            <p style="font-size:14px; color:var(--text-dim); margin-bottom:15px;">Please explain why you are requesting to revert this transaction. An admin will review your request.</p>
            <textarea name="reason" rows="4" style="width:100%; background:var(--bg-dark); border:1px solid var(--border); border-radius:4px; color:#fff; padding:10px; font-family:var(--font-body);" required></textarea>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeRevertModal()">Cancel</button>
                <button type="submit" class="btn btn-accent">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabName + '-tab').style.display = 'block';
    event.currentTarget.classList.add('active');
}

function openRevertModal(type, id) {
    document.getElementById('revertType').value = type;
    document.getElementById('revertId').value = id;
    document.getElementById('revertModal').classList.add('open');
}

function closeRevertModal() {
    document.getElementById('revertModal').classList.remove('open');
}
</script>
</body>
</html>