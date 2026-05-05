<?php
require_once "inventory_func.php";
$active_page = 'inventory';

$page       = max(1, (int)($_GET["page"] ?? 1));
$per_page   = 8;
$game_filter = $_GET["game"] ?? "";
$search     = trim($_GET["search"] ?? "");
$sort       = $_GET["sort"] ?? "newest";
$min_price  = $_GET["min_price"] ?? "";
$max_price  = $_GET["max_price"] ?? "";

$result       = get_user_inventory($link, $user_id, $page, $per_page, $game_filter, $search, $sort, $min_price, $max_price);
$items        = $result["items"];
$total        = $result["total"];
$total_pages  = max(1, (int)ceil($total / $per_page));
$active_count = count_user_listings($link, $user_id);
$username     = $_SESSION["username"] ?? "User";

$total_inv_value = 0;
foreach($items as $row) {
    $total_inv_value += ($row['ml_price'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory — <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .inv-layout {
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }
        .inv-main { flex: 1; min-width: 0; }
        .inv-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .inv-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .inv-title span { color: var(--accent); }
        .search-bar {
            display: flex;
            align-items: center;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0 14px;
            gap: 10px;
            flex: 1;
            max-width: 300px;
            transition: border-color 0.2s;
        }
        .search-bar:focus-within { border-color: var(--accent); }
        .search-bar input {
            background: none;
            border: none;
            outline: none;
            color: var(--text);
            font-family: var(--font-body);
            font-size: 14px;
            padding: 10px 0;
            width: 100%;
        }
        .search-bar input::placeholder { color: var(--text-muted); }
        .search-bar svg { color: var(--text-dim); flex-shrink: 0; }
        .inv-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            background: var(--bg-card);
            padding: 15px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
        }
        .price-filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 15px;
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
        }
        .price-input {
            width: 80px;
            background: var(--bg-card-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: #fff;
            padding: 8px;
            font-size: 13px;
        }
        .price-input:focus { border-color: var(--accent); outline: none; }
        .inv-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        @media (max-width: 1200px) { .inv-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 900px)  { .inv-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px)  { .inv-grid { grid-template-columns: 1fr; } }

        .item-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
        }
        .item-card:hover {
            border-color: var(--border-light);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        .item-card-value-badge {
            padding: 6px 12px;
            font-family: var(--font-display);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--text-dim);
            text-transform: uppercase;
        }
        .item-card-value-badge span { color: var(--accent); }
        .pagination-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state svg { margin-bottom: 16px; opacity: 0.4; }
        .empty-state p { font-size: 15px; }

        .modal-item-preview {
            display: flex;
            gap: 16px;
            align-items: center;
            background: var(--bg-dark);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 20px;
        }
        .modal-item-preview img { width: 80px; height: 60px; object-fit: contain; }
        .modal-item-preview-info { flex: 1; }
        .modal-item-preview-name { font-family: var(--font-display); font-size: 17px; font-weight: 700; color: var(--text); }

        .value-summary {
            text-align: right;
            padding: 12px 20px;
            background: var(--bg-card-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
        }
        .value-label { font-size: 11px; color: var(--text-dim); text-transform: uppercase; font-weight: 800; }
        .value-amount { font-family: var(--font-display); font-size: 22px; font-weight: 700; color: var(--accent); }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page">
    <div class="inv-layout">
        <aside class="sidebar">
            <div>
                <div class="sidebar-section-label">Game</div>
                <div class="game-list">
                    <?php
                    $games = [
                        '' => ['name' => 'All Games', 'logo' => ''],
                        'cs2'   => inv_game_info('cs2'),
                        'dota2' => inv_game_info('dota2'),
                        'rust'  => inv_game_info('rust'),
                        'tf2'   => inv_game_info('tf2'),
                    ];
                    foreach ($games as $slug => $info):
                        $sel = $game_filter === $slug ? 'selected' : '';
                    ?>
                    <a href="?game=<?= urlencode($slug) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>"
                       class="game-item <?= $sel ?>" style="text-decoration:none;">
                        <?php if ($info['logo']): ?><img src="<?= htmlspecialchars($info['logo']) ?>" alt=""><?php endif; ?>
                        <span><?= htmlspecialchars($info['name']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="height:1px;background:var(--border); margin: 15px 0;"></div>

            <div>
                <div class="sidebar-section-label">My Listings</div>
                <div style="font-size:14px;color:var(--text-dim);">
                    <span style="color:#fff;font-family:var(--font-display);font-size:20px;font-weight:700;"><?= $active_count ?></span>
                    Active Listing<?= $active_count !== 1 ? 's' : '' ?>
                </div>
                <a href="profile.php" class="btn btn-ghost btn-full" style="margin-top:10px;font-size:13px;">
                    View My Listings →
                </a>
            </div>
        </aside>

        <div class="inv-main">
            <div class="inv-header">
                <div class="inv-title">
                    USER INVENTORY: <span>[<?= htmlspecialchars($username) ?>]</span>
                </div>
                <div class="value-summary">
                    <div class="value-label">Estimated Value</div>
                    <div class="value-amount">$<?= number_format($total_inv_value, 2) ?></div>
                </div>
            </div>

            <div class="inv-controls">
                <form method="GET" style="display:flex;align-items:center;gap:12px;flex:1;flex-wrap:wrap;">
                    <input type="hidden" name="game" value="<?= htmlspecialchars($game_filter) ?>">
                    
                    <div class="search-bar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" name="search" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="price-filter-group">
                        <span style="font-size: 12px; color: var(--text-dim); font-weight: 600;">VALUE:</span>
                        <input type="number" name="min_price" class="price-input" placeholder="Min" value="<?= htmlspecialchars($min_price) ?>" step="0.01">
                        <span style="color: var(--border-light)">-</span>
                        <input type="number" name="max_price" class="price-input" placeholder="Max" value="<?= htmlspecialchars($max_price) ?>" step="0.01">
                    </div>

                    <div class="select-wrap" style="min-width:160px;">
                        <select name="sort" onchange="this.form.submit()">
                            <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Sort: Newest</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Value: High → Low</option>
                            <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Value: Low → High</option>
                            <option value="name_asc"   <?= $sort === 'name_asc'   ? 'selected' : '' ?>>Name: A → Z</option>
                            <option value="name_desc"  <?= $sort === 'name_desc'  ? 'selected' : '' ?>>Name: Z → A</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-accent btn-sm">Apply Filters</button>
                </form>
            </div>

            <div class="inv-grid">
                <?php if (empty($items)): ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    <p>No items found in your inventory.</p>
                </div>
                <?php else: ?>
                <?php foreach ($items as $item):
                    $gi = inv_game_info($item['game']);
                    $price_label = $item['ml_price'] ? '$' . number_format($item['ml_price'], 2) : 'N/A';
                ?>
                <div class="item-card">
                    <div class="item-card-value-badge">Value: <span><?= $price_label ?></span></div>
                    <div class="item-card-img">
                        <img src="<?= htmlspecialchars($item['image'] ?? 'item_images/placeholder.png') ?>"
                             alt="<?= htmlspecialchars($item['name']) ?>"
                             onerror="this.src='item_images/placeholder.png'">
                    </div>
                    <div class="item-card-body">
                        <div class="item-card-name">
                            <?php if ($gi['logo']): ?><img src="<?= htmlspecialchars($gi['logo']) ?>" alt=""><?php endif; ?>
                            <?= htmlspecialchars($item['name']) ?>
                        </div>
                        <div class="item-meta">
                            Wear: <span class="fn"><?= htmlspecialchars($item['wear_rating'] ?? 'N/A') ?></span>
                        </div>
                        <div class="item-meta">
                            Rarity: <span class="cov"><?= htmlspecialchars($item['rarity'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="item-card-footer">
                        <button type="button" class="btn btn-accent btn-sm" style="width:100%;"
                                onclick='openListModal(<?= $item['user_item_id'] ?>, <?= htmlspecialchars(json_encode($item['name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($item['image'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)'>
                            List Item
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="pagination-row">
                <div class="pagination">
                    <span class="page-info">Items <?= (($page-1)*$per_page)+1 ?>–<?= min($page*$per_page, $total) ?> of <?= $total ?></span>
                    <a href="?page=<?= $page-1 ?>&game=<?= urlencode($game_filter) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>"
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                       <?= $page <= 1 ? 'aria-disabled="true" style="pointer-events:none;opacity:0.35;"' : '' ?>>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                    </a>
                    <a href="?page=<?= $page+1 ?>&game=<?= urlencode($game_filter) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>"
                       class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                       <?= $page >= $total_pages ? 'aria-disabled="true" style="pointer-events:none;opacity:0.35;"' : '' ?>>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="listModal">
    <div class="modal">
        <div class="modal-title">List Item on Market</div>
        <div class="modal-item-preview">
            <img id="modalItemImg" src="" alt="">
            <div class="modal-item-preview-info">
                <div class="modal-item-preview-name" id="modalItemName"></div>
                <div style="font-size:13px;color:var(--text-dim);margin-top:4px;">Set your asking price below</div>
            </div>
        </div>
        <form method="POST" id="listForm">
            <input type="hidden" name="action" value="list_item">
            <input type="hidden" name="user_item_id" id="modalUserItemId">
            <div class="field-group">
                <label class="field-label">Listing Price (USD)</label>
                <div class="field-input-icon">
                    <input type="number" name="price" id="modalPrice" class="field-input"
                           placeholder="0.00" step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeListModal()">Cancel</button>
                <button type="submit" class="btn btn-accent">Confirm Listing</button>
            </div>
        </form>
    </div>
</div>

<script>
function openListModal(userItemId, name, image) {
    document.getElementById('modalUserItemId').value = userItemId;
    document.getElementById('modalItemName').textContent = name;
    document.getElementById('modalItemImg').src = image || 'item_images/placeholder.png';
    document.getElementById('modalPrice').value = '';
    document.getElementById('listModal').classList.add('open');
}
function closeListModal() {
    document.getElementById('listModal').classList.remove('open');
}
document.getElementById('listModal').addEventListener('click', function(e) {
    if (e.target === this) closeListModal();
});
</script>
</body>
</html>
