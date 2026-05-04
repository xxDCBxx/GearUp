<?php
require_once "market_func.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'buy_item') {
    header('Content-Type: application/json');
    $result = process_market_purchase($link, $_SESSION["id"], (int)$_POST['listing_id']);
    echo json_encode($result);
    exit;
}

$active_page = 'market';
$page      = max(1, (int)($_GET['page'] ?? 1));
$search    = trim($_GET['search'] ?? "");
$game      = $_GET['game'] ?? "";
$min_p     = $_GET['min_p'] ?? "";
$max_p     = $_GET['max_p'] ?? "";
$sort      = $_GET['sort'] ?? "newest";
$per_page  = 5;

$data = get_market_listings($link, $page, $per_page, $search, $game, $min_p, $max_p, $sort);
$listings = $data['listings'];
$total = $data['total'];
$total_pages = max(1, ceil($total / $per_page));

$user_listings = count_user_listings($link, $_SESSION["id"]);
$my_inventory = get_my_trade_inventory($link, $_SESSION["id"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Market — GEARUP!</title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .market-layout { display: flex; gap: 24px; align-items: flex-start; }
        .market-main { flex: 1; min-width: 0; }
        .sidebar-v2 { width: 280px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px 20px; }
        .sidebar-label { font-family: var(--font-display); font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px; }
        .game-selector { background: var(--bg-card-2); border: 1px solid var(--accent); border-radius: var(--radius); padding: 10px 14px; color: #fff; font-size: 14px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; cursor: pointer; text-decoration: none; }
        .game-nav-list { display: flex; flex-direction: column; gap: 4px; margin-bottom: 24px; }
        .game-nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: var(--radius); color: var(--text-dim); text-decoration: none; transition: 0.2s; font-size: 14px; }
        .game-nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .game-nav-item.active { background: rgba(74,159,212,0.1); color: #fff; }
        .game-nav-item img { width: 20px; height: 20px; object-fit: contain; }
        .market-controls { display: flex; align-items: center; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 12px 16px; gap: 16px; margin-bottom: 24px; }
        .search-group { flex: 1; display: flex; align-items: center; background: var(--bg-card-2); border: 1px solid var(--border-light); border-radius: var(--radius); padding: 0 14px; gap: 10px; }
        .search-group input { background: none; border: none; padding: 10px 0; color: #fff; width: 100%; outline: none; }
        .price-group { display: flex; align-items: center; gap: 10px; padding: 0 16px; border-left: 1px solid var(--border-light); border-right: 1px solid var(--border-light); }
        .price-group label { font-size: 12px; font-weight: 700; color: var(--text-dim); }
        .price-group input { width: 70px; background: var(--bg-card-2); border: 1px solid var(--border-light); border-radius: var(--radius); padding: 8px; color: #fff; font-size: 13px; text-align: center; }
        .btn-apply { background: linear-gradient(135deg, var(--accent), #3a86b9); color: #fff; border: none; border-radius: var(--radius); padding: 10px 20px; font-weight: 700; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .listing-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
        .listing-table th { text-align: left; padding: 0 16px 8px; color: var(--text-dim); font-size: 14px; }
        .listing-row { background: var(--bg-card); border: 1px solid var(--border); transition: 0.3s; }
        .listing-row td { padding: 16px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .listing-row td:first-child { border-left: 1px solid var(--border); border-radius: 8px 0 0 8px; }
        .listing-row td:last-child { border-right: 1px solid var(--border); border-radius: 0 8px 8px 0; }
        .item-cell { display: flex; gap: 16px; align-items: center; }
        .item-thumb { width: 80px; height: 60px; object-fit: contain; background: var(--bg-card-2); border-radius: 4px; }
        .item-name { font-weight: 700; color: #fff; font-size: 15px; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .price-val { font-family: var(--font-display); font-size: 20px; font-weight: 700; color: #fff; }
        .action-group { display: flex; gap: 8px; justify-content: flex-end; }
        .page-btn { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--bg-card-2); border: 1px solid var(--border-light); border-radius: var(--radius); color: var(--text); text-decoration: none; transition: 0.2s; }
        .page-btn:hover:not(.disabled) { background: var(--accent); border-color: var(--accent); }
        .page-btn.disabled { opacity: 0.3; pointer-events: none; cursor: not-allowed; }
        .market-footer { display: flex; justify-content: flex-end; align-items: center; gap: 16px; margin-top: 32px; color: var(--text-dim); font-size: 14px; }
        .buy-confirm-text { font-size: 14px; color: var(--text-dim); margin: 20px 0; line-height: 1.6; }
        .buy-price-tag { font-family: var(--font-display); font-size: 24px; color: var(--accent); font-weight: 700; display: block; margin-top: 10px; }
        .btn-offer { background: transparent; color: var(--accent); border: 1px solid var(--accent); border-radius: var(--radius); padding: 8px 16px; font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-offer:hover { background: rgba(74, 159, 212, 0.1); color: #fff; }
        
        .steam-modal-body { padding: 0; }
        .steam-side-title { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 1px; }
        .steam-side-desc { font-size: 12px; color: var(--text-dim); margin-bottom: 12px; }
        .steam-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; background: var(--bg-dark); padding: 12px; border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 24px; min-height: 104px; box-sizing: border-box; width: 100%; }
        .steam-box { width: 100%; min-width: 0; aspect-ratio: 1; background: var(--bg-card-2); border: 1px dashed var(--border-light); border-radius: var(--radius); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 8px; position: relative; gap: 4px; text-align: center; box-sizing: border-box; overflow: hidden; }
        .steam-box.filled { border-style: solid; border-color: var(--border-light); background: var(--bg-card); cursor: pointer; transition: 0.2s; }
        .steam-box.filled:hover { border-color: var(--accent); }
        .steam-box img { max-width: 100%; height: 45px; object-fit: contain; margin-bottom: 2px; }
        .steam-box-name { font-family: var(--font-display); font-size: 11px; font-weight: 700; color: #fff; line-height: 1.1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-wrap: break-word; word-break: break-all; width: 100%; }
        .steam-box-meta { font-size: 9px; color: var(--text-dim); line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        
        .steam-confirm-wrap { display: flex; align-items: center; gap: 10px; background: rgba(74, 159, 212, 0.05); padding: 12px 16px; border: 1px solid var(--accent); border-radius: var(--radius); }
        .steam-confirm-wrap input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
        .steam-confirm-wrap label { font-size: 13px; color: #fff; cursor: pointer; user-select: none; font-weight: 600; margin: 0; }
        
        .inventory-selector { margin-top: 20px; border-top: 1px solid var(--border); padding-top: 15px; margin-bottom: 20px; }
        .inventory-title { font-size: 14px; color: var(--text-dim); margin-bottom: 10px; font-weight: 600; }
        .trade-item-list { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; max-height: 200px; overflow-y: auto; padding-right: 4px; box-sizing: border-box; width: 100%; }
        .trade-item-option { background: var(--bg-card-2); border: 1px solid var(--border-light); border-radius: var(--radius); padding: 8px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: 0.2s; text-align: center; box-sizing: border-box; min-width: 0; overflow: hidden; }
        .trade-item-option:hover { border-color: var(--accent); }
        .trade-item-option.selected { border-color: var(--accent); opacity: 0.3; pointer-events: none; }
        .trade-item-option img { max-width: 100%; height: 40px; object-fit: contain; }
        
        .btn:disabled, .btn-accent:disabled {
            background: var(--bg-card-2) !important;
            color: var(--text-muted) !important;
            cursor: not-allowed !important;
            opacity: 0.5 !important;
            pointer-events: none;
            box-shadow: none !important;
        }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
    <div class="market-layout">
        <aside class="sidebar-v2">
            <div class="sidebar-label">Game</div>
            <a href="market.php" class="game-selector">
                <?= empty($game) ? 'All Games' : market_game_info($game)['name'] ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
            </a>
            <div class="game-nav-list">
                <?php foreach(['cs2','dota2','rust','tf2'] as $g): $info = market_game_info($g); ?>
                <a href="?game=<?= $g ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>" class="game-nav-item <?= $game===$g?'active':'' ?>">
                    <img src="<?= $info['logo'] ?>"> <?= $info['name'] ?>
                </a>
                <?php endforeach; ?>
            </div>
            <div style="height:1px; background:var(--border); margin: 24px 0;"></div>
            <div class="sidebar-label">My Listings</div>
            <div style="font-size:20px; font-weight:700; color:#fff;"><?= $user_listings ?> <span style="color:var(--text-dim); font-size:14px; font-weight:500;">Active Listings</span></div>
            <a href="profile.php" class="btn btn-ghost btn-full" style="margin-top:12px;">View My Listings →</a>
        </aside>

        <main class="market-main">
            <h1 style="font-family: var(--font-display); font-size: 28px; font-weight: 800; color: #fff; text-transform: uppercase; margin-bottom: 20px;">Market Listings</h1>
            <form action="" method="GET" class="market-controls">
                <input type="hidden" name="game" value="<?= htmlspecialchars($game) ?>">
                <div class="search-group">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" name="search" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="price-group">
                    <label>VALUE:</label>
                    <input type="number" name="min_p" placeholder="Min" value="<?= htmlspecialchars($min_p) ?>" step="0.01">
                    <span style="color:var(--border-light)">-</span>
                    <input type="number" name="max_p" placeholder="Max" value="<?= htmlspecialchars($max_p) ?>" step="0.01">
                </div>
                <div class="select-wrap" style="width:180px;">
                    <select name="sort">
                        <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Sort: Newest</option>
                        <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Value: Low → High</option>
                        <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Value: High → Low</option>
                    </select>
                </div>
                <button type="submit" class="btn-apply">Apply Filters</button>
            </form>

            <table class="listing-table">
                <thead><tr><th width="45%">Item</th><th width="15%">Seller</th><th width="15%">Price</th><th width="25%">Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($listings)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-dim);">No items found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($listings as $row): $gi = market_game_info($row['game']); ?>
                            <tr class="listing-row" id="listing-row-<?= $row['listing_id'] ?>">
                                <td><div class="item-cell"><img src="<?= htmlspecialchars($row['image']) ?>" class="item-thumb"><div class="item-details"><div class="item-name"><?= htmlspecialchars($row['name']) ?> <?php if($gi['logo']): ?><img src="<?= $gi['logo'] ?>" width="16"><?php endif; ?></div><div class="item-meta">Wear: <?= $row['wear_rating'] ?></div></div></div></td>
                                <td style="color:var(--text-dim);"><?= htmlspecialchars($row['seller_name']) ?></td>
                                <td class="price-val">$<?= number_format($row['price'], 2) ?></td>
                                <td><div class="action-group">
                                    <?php if($row['owner_id'] != $_SESSION['id']): ?>
                                        <button class="btn btn-sm btn-offer" onclick="openOfferModal('<?= $row['owner_id'] ?>', '<?= $row['item_id'] ?>', '<?= addslashes($row['name']) ?>', '<?= htmlspecialchars($row['image']) ?>', '<?= htmlspecialchars($row['wear_rating'] ?? 'N/A') ?>', '<?= htmlspecialchars($row['rarity'] ?? 'N/A') ?>', '<?= $gi['logo'] ?>')">Make Offer</button>
                                        <button type="button" class="btn btn-sm btn-accent" onclick="openBuyModal('<?= $row['listing_id'] ?>', '<?= addslashes($row['name']) ?>', '<?= number_format($row['price'], 2) ?>')">Buy Now</button>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:var(--accent); font-weight:800; letter-spacing:1px;">YOUR LISTING</span>
                                    <?php endif; ?>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="market-footer">
                <span>Items <?= $total > 0 ? (($page-1)*$per_page)+1 : 0 ?>-<?= min($page*$per_page, $total) ?> of <?= $total ?></span>
                <div style="display:flex; gap:8px;">
                    <a href="?page=<?= $page-1 ?>&game=<?= $game ?>&search=<?= urlencode($search) ?>&min_p=<?= $min_p ?>&max_p=<?= $max_p ?>&sort=<?= $sort ?>" 
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                       <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"/></svg>
                    </a>
                    <a href="?page=<?= $page+1 ?>&game=<?= $game ?>&search=<?= urlencode($search) ?>&min_p=<?= $min_p ?>&max_p=<?= $max_p ?>&sort=<?= $sort ?>" 
                       class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                       <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal-overlay" id="offerModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-title">Make a Trade Offer</div>
        <form action="offers_func.php" method="POST" id="offerForm">
            <input type="hidden" name="action" value="make_offer">
            <input type="hidden" name="receiver_id" id="modalReceiverId">
            <input type="hidden" name="receiver_item_id" id="modalReceiverItemId">
            <input type="hidden" name="sender_item_id" id="modalSenderItemId">
            
            <div class="steam-modal-body">
                <div class="steam-side-title">Your items:</div>
                <div class="steam-side-desc">These are the items you will lose in the trade.</div>
                <div class="steam-grid" id="senderGrid">
                </div>

                <div class="steam-side-title">Their items:</div>
                <div class="steam-side-desc">These are the items you will receive in the trade.</div>
                <div class="steam-grid" id="receiverGrid">
                </div>

                <div class="inventory-selector">
                    <div class="inventory-title">Select items from your inventory:</div>
                    <div class="trade-item-list">
                        <?php foreach($my_inventory as $inv): $inv_gi = market_game_info($inv['game'] ?? ''); ?>
                        <div class="trade-item-option" id="inv-opt-<?= $inv['item_id'] ?>" onclick="addTradeItem('<?= $inv['item_id'] ?>', '<?= htmlspecialchars($inv['image']) ?>', '<?= htmlspecialchars(addslashes($inv['name'])) ?>', '<?= htmlspecialchars($inv['wear_rating'] ?? 'N/A') ?>', '<?= htmlspecialchars($inv['rarity'] ?? 'N/A') ?>', '<?= $inv_gi['logo'] ?>')">
                            <img src="<?= $inv['image'] ?>" alt="">
                            <div class="steam-box-name">
                                <?php if($inv_gi['logo']): ?><img src="<?= $inv_gi['logo'] ?>" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;display:inline-block;margin-bottom:0;"><?php endif; ?>
                                <?= htmlspecialchars($inv['name']) ?>
                            </div>
                            <div class="steam-box-meta">Wear: <?= htmlspecialchars($inv['wear_rating'] ?? 'N/A') ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; width: 100%; border-top: 1px solid var(--border); padding-top: 20px;">
                <div class="steam-confirm-wrap" style="padding: 8px 12px; margin: 0;">
                    <input type="checkbox" id="confirmTradeCheck" onchange="checkFormReady()">
                    <label for="confirmTradeCheck">Click here to confirm trade contents.</label>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-ghost" onclick="closeOfferModal()">Cancel</button>
                    <button type="submit" class="btn btn-accent" id="sendOfferBtn" disabled>Make Offer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="buyModal">
    <div class="modal">
        <div class="modal-title">Confirm Purchase</div>
        <div id="buyError" style="display:none; background:rgba(224,72,58,0.1); border:1px solid var(--danger); color:#ff9d94; padding:10px; border-radius:4px; margin-bottom:15px; font-size:13px;"></div>
        <div class="buy-confirm-text">Are you sure you want to purchase <strong id="buyItemName"></strong>?<span class="buy-price-tag">$<span id="buyItemPrice"></span></span></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeBuyModal()">Cancel</button>
            <button type="button" class="btn btn-accent" id="confirmBuyBtn" onclick="processPurchase()">Confirm & Buy</button>
        </div>
    </div>
</div>

<script>
let currentListingId = null;
let selectedItems = [];

function openOfferModal(ownerId, itemId, itemName, itemImg, itemWear, itemRarity, gameLogo) {
    document.getElementById('modalReceiverId').value = ownerId;
    document.getElementById('modalReceiverItemId').value = itemId;
    
    const logoHtml = gameLogo ? `<img src="${gameLogo}" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;display:inline-block;margin-bottom:0;">` : '';
    
    const recGrid = document.getElementById('receiverGrid');
    recGrid.innerHTML = `
        <div class="steam-box filled">
            <img src="${itemImg}" alt="">
            <div class="steam-box-name">${logoHtml}${itemName}</div>
            <div class="steam-box-meta">Wear: ${itemWear}</div>
            <div class="steam-box-meta">Rarity: ${itemRarity}</div>
        </div>
        <div class="steam-box empty"></div>
        <div class="steam-box empty"></div>
        <div class="steam-box empty"></div>
    `;

    selectedItems = [];
    document.querySelectorAll('.trade-item-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('confirmTradeCheck').checked = false;
    
    renderSenderGrid();
    document.getElementById('offerModal').classList.add('open');
}

function closeOfferModal() {
    document.getElementById('offerModal').classList.remove('open');
}

function addTradeItem(id, img, name, wear, rarity, gameLogo) {
    if (selectedItems.length >= 8) {
        alert("You can only select up to 8 items for a single trade.");
        return;
    }
    selectedItems.push({id, img, name, wear, rarity, gameLogo});
    document.getElementById('inv-opt-' + id).classList.add('selected');
    renderSenderGrid();
}

function removeTradeItem(index, id) {
    selectedItems.splice(index, 1);
    document.getElementById('inv-opt-' + id).classList.remove('selected');
    document.getElementById('confirmTradeCheck').checked = false;
    renderSenderGrid();
}

function renderSenderGrid() {
    const grid = document.getElementById('senderGrid');
    grid.innerHTML = '';
    
    const totalBoxes = selectedItems.length > 4 ? 8 : 4;
    
    for (let i = 0; i < totalBoxes; i++) {
        const box = document.createElement('div');
        box.className = 'steam-box';
        if (selectedItems[i]) {
            box.classList.add('filled');
            const logoHtml = selectedItems[i].gameLogo ? `<img src="${selectedItems[i].gameLogo}" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;display:inline-block;margin-bottom:0;">` : '';
            box.innerHTML = `
                <img src="${selectedItems[i].img}" alt="">
                <div class="steam-box-name">${logoHtml}${selectedItems[i].name}</div>
                <div class="steam-box-meta">Wear: ${selectedItems[i].wear}</div>
                <div class="steam-box-meta">Rarity: ${selectedItems[i].rarity}</div>
            `;
            box.onclick = () => removeTradeItem(i, selectedItems[i].id);
        } else {
            box.classList.add('empty');
        }
        grid.appendChild(box);
    }
    
    document.getElementById('modalSenderItemId').value = selectedItems.map(item => item.id).join(',');
    checkFormReady();
}

function checkFormReady() {
    const isChecked = document.getElementById('confirmTradeCheck').checked;
    const hasItems = selectedItems.length > 0;
    document.getElementById('sendOfferBtn').disabled = !(isChecked && hasItems);
}

function openBuyModal(listingId, itemName, itemPrice) {
    currentListingId = listingId;
    document.getElementById('buyItemName').textContent = itemName;
    document.getElementById('buyItemPrice').textContent = itemPrice;
    document.getElementById('buyError').style.display = 'none';
    document.getElementById('buyModal').classList.add('open');
}

function closeBuyModal() { 
    document.getElementById('buyModal').classList.remove('open'); 
}

async function processPurchase() {
    const btn = document.getElementById('confirmBuyBtn');
    const errorDiv = document.getElementById('buyError');
    btn.disabled = true;
    btn.textContent = "Processing...";
    const formData = new FormData();
    formData.append('ajax_action', 'buy_item');
    formData.append('listing_id', currentListingId);
    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            document.getElementById('listing-row-' + currentListingId).remove();
            const creditEl = document.getElementById('user-credits');
            if (creditEl) creditEl.textContent = result.new_balance;
            closeBuyModal();
            alert("Purchase successful!");
        } else {
            errorDiv.textContent = result.error;
            errorDiv.style.display = 'block';
            btn.disabled = false;
            btn.textContent = "Confirm & Buy";
        }
    } catch (e) {
        errorDiv.textContent = "Error occurred.";
        errorDiv.style.display = 'block';
        btn.disabled = false;
        btn.textContent = "Confirm & Buy";
    }
}
</script>
</body>
</html>
