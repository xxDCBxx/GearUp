<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php"); exit;
}
$me = $_SESSION["id"];
$chk = mysqli_prepare($link, "SELECT is_admin FROM users WHERE id = ?");
mysqli_stmt_bind_param($chk, "i", $me);
mysqli_stmt_execute($chk);
mysqli_stmt_bind_result($chk, $admin_flag);
mysqli_stmt_fetch($chk);
mysqli_stmt_close($chk);
if (!$admin_flag) { header("location: home.php"); exit; }

$active_page = 'admin_records';

// ── Filters ────────────────────────────────────────────────────────────────
$page_num  = max(1, intval($_GET['p'] ?? 1));
$per_page  = 20;
$offset    = ($page_num - 1) * $per_page;
$search    = trim($_GET['q'] ?? '');
$type_f    = $_GET['type'] ?? '';   // sale | offer | credit

// ── Total count ────────────────────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = [];
$types  = '';
if ($search) {
    $where   .= " AND (buyer.name LIKE ? OR seller.name LIKE ? OR i.name LIKE ?)";
    $like     = "%$search%";
    $params   = [$like, $like, $like];
    $types    = 'sss';
}
if ($type_f === 'sale') {
    $where .= " AND t.type = 'sale'";
} elseif ($type_f === 'offer') {
    $where .= " AND t.type = 'offer'";
} elseif ($type_f === 'credit') {
    $where .= " AND t.type = 'credit'";
}

$count_sql = "SELECT COUNT(*) FROM transactions t
              LEFT JOIN users buyer  ON t.buyer_id  = buyer.id
              LEFT JOIN users seller ON t.seller_id = seller.id
              LEFT JOIN items i      ON t.item_id   = i.id
              $where";

$cs = mysqli_prepare($link, $count_sql);
if ($types) mysqli_stmt_bind_param($cs, $types, ...$params);
mysqli_stmt_execute($cs);
$total = mysqli_fetch_row(mysqli_stmt_get_result($cs))[0];
mysqli_stmt_close($cs);
$total_pages = max(1, ceil($total / $per_page));

// ── Fetch records ──────────────────────────────────────────────────────────
$sql = "SELECT t.id, t.type, t.amount, t.created_at, t.notes,
               buyer.id AS buyer_id,   buyer.name  AS buyer_name,
               seller.id AS seller_id, seller.name AS seller_name,
               i.id AS item_id, i.name AS item_name, i.image AS item_image, i.game
        FROM transactions t
        LEFT JOIN users buyer  ON t.buyer_id  = buyer.id
        LEFT JOIN users seller ON t.seller_id = seller.id
        LEFT JOIN items i      ON t.item_id   = i.id
        $where
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($link, $sql);
if ($types) {
    $all_params = array_merge($params, [$per_page, $offset]);
    $all_types  = $types . 'ii';
    mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $per_page, $offset);
}
mysqli_stmt_execute($stmt);
$records = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) { $records[] = $row; }
mysqli_stmt_close($stmt);

function game_badge(string $game): string {
    return match(strtolower($game)) {
        'cs2'   => '<span style="color:#4a9fd4;font-size:10px;font-weight:700;">CS2</span>',
        'dota2' => '<span style="color:#e05050;font-size:10px;font-weight:700;">DOTA2</span>',
        'rust'  => '<span style="color:#c18444;font-size:10px;font-weight:700;">RUST</span>',
        'tf2'   => '<span style="color:#cf6a32;font-size:10px;font-weight:700;">TF2</span>',
        default => ''
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records — Admin · <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .admin-page { padding: 32px 40px; max-width: 1300px; margin: 0 auto; }
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; flex-wrap: wrap; gap: 14px;
        }
        .page-title { font-family: var(--font-display); font-size: 26px; font-weight: 700; color: #fff; }
        .page-title span { color: var(--accent); }

        /* toolbar */
        .toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .toolbar input {
            background: var(--bg-card-2); border: 1px solid var(--border);
            border-radius: var(--radius); color: var(--text);
            font-family: var(--font-body); font-size: 14px;
            padding: 9px 14px; outline: none; width: 240px; transition: border-color .2s;
        }
        .toolbar input:focus { border-color: var(--accent); }
        .type-tabs { display: flex; gap: 6px; }
        .type-tab {
            padding: 7px 14px; border-radius: var(--radius); font-size: 12px; font-weight: 600;
            text-decoration: none; border: 1px solid var(--border);
            color: var(--text-dim); background: var(--bg-card-2); transition: all .2s;
        }
        .type-tab:hover  { border-color: var(--accent); color: var(--accent); }
        .type-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* table */
        .table-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
        .rec-table { width: 100%; border-collapse: collapse; }
        .rec-table th {
            text-align: left; padding: 12px 16px;
            font-family: var(--font-display); font-size: 13px; font-weight: 600;
            letter-spacing: .5px; color: var(--text-dim);
            border-bottom: 1px solid var(--border); background: var(--bg-card-2);
        }
        .rec-table td { padding: 0; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .rec-table tr:last-child td { border-bottom: none; }
        .rec-table tr:hover td { background: rgba(255,255,255,.02); }
        .td-p { padding: 12px 16px; }

        /* type chip */
        .type-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .chip-sale   { background: rgba(74,159,212,.15); color: var(--accent); border: 1px solid rgba(74,159,212,.3); }
        .chip-offer  { background: rgba(60,184,120,.15); color: var(--success); border: 1px solid rgba(60,184,120,.3); }
        .chip-credit { background: rgba(240,168,48,.15); color: var(--warn); border: 1px solid rgba(240,168,48,.3); }

        /* item thumb */
        .item-thumb { display: flex; align-items: center; gap: 10px; }
        .item-thumb img { width: 44px; height: 34px; object-fit: contain; }
        .item-thumb-info { }
        .item-thumb-name { font-family: var(--font-display); font-size: 14px; font-weight: 600; color: var(--text); }

        /* amount */
        .amount-val { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--accent); }

        /* pagination */
        .pager { display: flex; align-items: center; gap: 8px; padding: 18px 16px; justify-content: flex-end; }
        .pager-info { font-size: 13px; color: var(--text-muted); margin-right: 8px; }

        .empty-state {
            padding: 60px; text-align: center; color: var(--text-dim); font-size: 15px;
        }
        .empty-state svg { display: block; margin: 0 auto 14px; opacity: .3; }

        .notes-cell { font-size: 12px; color: var(--text-dim); max-width: 200px; }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="admin-page">
    <div class="page-header">
        <div class="page-title">Transaction <span>Records</span></div>
        <form method="GET" action="admin_records.php" class="toolbar">
            <input type="text" name="q" placeholder="Search user or item…" value="<?= htmlspecialchars($search) ?>">
            <div class="type-tabs">
                <a href="admin_records.php?q=<?= urlencode($search) ?>&type="       class="type-tab <?= $type_f === ''       ? 'active' : '' ?>">All</a>
                <a href="admin_records.php?q=<?= urlencode($search) ?>&type=sale"   class="type-tab <?= $type_f === 'sale'   ? 'active' : '' ?>">Sales</a>
                <a href="admin_records.php?q=<?= urlencode($search) ?>&type=offer"  class="type-tab <?= $type_f === 'offer'  ? 'active' : '' ?>">Offers</a>
                <a href="admin_records.php?q=<?= urlencode($search) ?>&type=credit" class="type-tab <?= $type_f === 'credit' ? 'active' : '' ?>">Credits</a>
            </div>
            <button type="submit" class="btn btn-accent btn-sm">Search</button>
            <?php if ($search || $type_f): ?><a href="admin_records.php" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
        </form>
    </div>

    <div class="table-card">
        <?php if (empty($records)): ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>
            No records found.
        </div>
        <?php else: ?>
        <table class="rec-table">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Buyer</th>
                    <th>Seller</th>
                    <th>Amount</th>
                    <th>Notes</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
                <td><div class="td-p" style="color:var(--text-muted);font-size:12px;">#<?= $r['id'] ?></div></td>
                <td>
                    <div class="td-p">
                        <?php
                        $chip_class = match($r['type']) {
                            'sale'   => 'chip-sale',
                            'offer'  => 'chip-offer',
                            'credit' => 'chip-credit',
                            default  => 'chip-credit'
                        };
                        ?>
                        <span class="type-chip <?= $chip_class ?>"><?= htmlspecialchars(ucfirst($r['type'])) ?></span>
                    </div>
                </td>
                <td>
                    <div class="td-p">
                        <?php if ($r['item_name']): ?>
                        <div class="item-thumb">
                            <?php if ($r['item_image']): ?>
                            <img src="<?= htmlspecialchars($r['item_image']) ?>" alt="" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <div class="item-thumb-info">
                                <div class="item-thumb-name"><?= htmlspecialchars($r['item_name']) ?></div>
                                <?= game_badge($r['game'] ?? '') ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:13px;">—</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="td-p" style="font-size:14px;color:var(--text);">
                        <?= $r['buyer_name'] ? htmlspecialchars($r['buyer_name']) : '<span style="color:var(--text-muted);">—</span>' ?>
                        <?php if ($r['buyer_id']): ?>
                        <div style="font-size:11px;color:var(--text-muted);">#<?= $r['buyer_id'] ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="td-p" style="font-size:14px;color:var(--text);">
                        <?= $r['seller_name'] ? htmlspecialchars($r['seller_name']) : '<span style="color:var(--text-muted);">—</span>' ?>
                        <?php if ($r['seller_id']): ?>
                        <div style="font-size:11px;color:var(--text-muted);">#<?= $r['seller_id'] ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="td-p">
                        <span class="amount-val">$<?= number_format(floatval($r['amount']), 2) ?></span>
                    </div>
                </td>
                <td>
                    <div class="td-p notes-cell">
                        <?= $r['notes'] ? htmlspecialchars($r['notes']) : '—' ?>
                    </div>
                </td>
                <td>
                    <div class="td-p" style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                        <?= date('M j, Y', strtotime($r['created_at'])) ?><br>
                        <?= date('g:i A', strtotime($r['created_at'])) ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pager">
            <span class="pager-info">
                Showing <?= count($records) ?> of <?= $total ?> records
            </span>
            <?php if ($page_num > 1): ?>
            <a href="admin_records.php?q=<?= urlencode($search) ?>&type=<?= urlencode($type_f) ?>&p=<?= $page_num - 1 ?>" class="btn btn-ghost btn-sm">← Prev</a>
            <?php endif; ?>
            <span style="font-size:13px;color:var(--text-muted);">Page <?= $page_num ?> / <?= $total_pages ?></span>
            <?php if ($page_num < $total_pages): ?>
            <a href="admin_records.php?q=<?= urlencode($search) ?>&type=<?= urlencode($type_f) ?>&p=<?= $page_num + 1 ?>" class="btn btn-ghost btn-sm">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>