<?php
require_once "offers_func.php";
$active_page = 'offers';
$tab = $_GET['tab'] ?? 'received';
$offers = get_user_offers($link, $user_id, $tab);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trade Offers — <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .offers-tabs { display: flex; justify-content: center; gap: 80px; margin-bottom: 40px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .tab-link { font-family: var(--font-display); font-size: 32px; color: var(--text-muted); text-decoration: none; position: relative; transition: 0.3s; }
        .tab-link.active { color: var(--text); }
        .tab-link.active::after { content: ''; position: absolute; bottom: -21px; left: 0; width: 100%; height: 3px; background: var(--accent); }
        .offers-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .offer-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; }
        .offer-split { display: flex; flex-direction: column; gap: 20px; margin-bottom: 24px; }
        .trade-side { width: 100%; }
        .steam-side-title { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }
        .steam-side-title img { width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--border-light); }
        .steam-side-desc { font-size: 12px; color: var(--text-dim); margin-bottom: 12px; }
        .steam-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; background: var(--bg-dark); padding: 12px; border: 1px solid var(--border); border-radius: var(--radius); min-height: 104px; }
        .steam-box { width: 100%; background: var(--bg-card-2); border: 1px dashed var(--border-light); border-radius: var(--radius); display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding: 8px; position: relative; gap: 4px; text-align: center; }
        .steam-box.filled { border-style: solid; border-color: var(--border-light); background: var(--bg-card); }
        .steam-box img { width: 100%; height: 60px; object-fit: contain; margin-bottom: 4px; }
        .steam-box-name { font-family: var(--font-display); font-size: 11px; font-weight: 700; color: #fff; line-height: 1.1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .steam-box-meta { font-size: 9px; color: var(--text-dim); line-height: 1.1; }
        .offer-actions { display: flex; gap: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); }
        .btn-large { flex: 1; padding: 14px; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
    <div class="offers-tabs">
        <a href="?tab=received" class="tab-link <?= $tab==='received'?'active':'' ?>">Received Offers</a>
        <a href="?tab=sent" class="tab-link <?= $tab==='sent'?'active':'' ?>">Sent Offers</a>
    </div>
    <div class="offers-grid">
        <?php foreach ($offers as $off): 
            $give_items = ($tab === 'received') ? $off['receiver_items'] : $off['sender_items'];
            $receive_items = ($tab === 'received') ? $off['sender_items'] : $off['receiver_items'];
        ?>
        <div class="offer-card">
            <div class="offer-split">
                <div class="trade-side">
                    <div class="steam-side-title">
                        Your items:
                    </div>
                    <div class="steam-side-desc">These are the items you will lose in the trade.</div>
                    <div class="steam-grid">
                        <?php 
                        $boxes = max(4, count($give_items));
                        for($i=0; $i<$boxes; $i++): 
                            if(isset($give_items[$i])): 
                                $gi = offers_game_info($give_items[$i]['game'] ?? '');
                        ?>
                                <div class="steam-box filled" title="<?= htmlspecialchars($give_items[$i]['name']) ?>">
                                    <img src="<?= htmlspecialchars($give_items[$i]['image']) ?>">
                                    <div class="steam-box-name">
                                        <?php if($gi['logo']): ?><img src="<?= $gi['logo'] ?>" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;display:inline-block;margin-bottom:0;"><?php endif; ?>
                                        <?= htmlspecialchars($give_items[$i]['name']) ?>
                                    </div>
                                    <div class="steam-box-meta">Wear: <?= htmlspecialchars($give_items[$i]['wear_rating'] ?? 'N/A') ?></div>
                                    <div class="steam-box-meta">Rarity: <?= htmlspecialchars($give_items[$i]['rarity'] ?? 'N/A') ?></div>
                                </div>
                            <?php else: ?>
                                <div class="steam-box empty"></div>
                            <?php endif; 
                        endfor; ?>
                    </div>
                </div>
                
                <div class="trade-side">
                    <div class="steam-side-title">
                        Their items:
                    </div>
                    <div class="steam-side-desc">These are the items you will receive in the trade.</div>
                    <div class="steam-grid">
                        <?php 
                        $boxes = max(4, count($receive_items));
                        for($i=0; $i<$boxes; $i++): 
                            if(isset($receive_items[$i])): 
                                $gi = offers_game_info($receive_items[$i]['game'] ?? '');
                        ?>
                                <div class="steam-box filled" title="<?= htmlspecialchars($receive_items[$i]['name']) ?>">
                                    <img src="<?= htmlspecialchars($receive_items[$i]['image']) ?>">
                                    <div class="steam-box-name">
                                        <?php if($gi['logo']): ?><img src="<?= $gi['logo'] ?>" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;display:inline-block;margin-bottom:0;"><?php endif; ?>
                                        <?= htmlspecialchars($receive_items[$i]['name']) ?>
                                    </div>
                                    <div class="steam-box-meta">Wear: <?= htmlspecialchars($receive_items[$i]['wear_rating'] ?? 'N/A') ?></div>
                                    <div class="steam-box-meta">Rarity: <?= htmlspecialchars($receive_items[$i]['rarity'] ?? 'N/A') ?></div>
                                </div>
                            <?php else: ?>
                                <div class="steam-box empty"></div>
                            <?php endif; 
                        endfor; ?>
                    </div>
                </div>
            </div>

            <form method="POST" class="offer-actions">
                <input type="hidden" name="offer_id" value="<?= $off['offer_id'] ?>">
                <input type="hidden" name="current_tab" value="<?= $tab ?>">
                <?php if ($tab==='received'): ?>
                    <button type="submit" name="action" value="accept_offer" class="btn btn-accent btn-large">Accept</button>
                    <button type="submit" name="action" value="decline_offer" class="btn btn-danger btn-large">Decline</button>
                <?php else: ?>
                    <button type="submit" name="action" value="cancel_offer" class="btn btn-danger btn-large">Cancel Offer</button>
                <?php endif; ?>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
