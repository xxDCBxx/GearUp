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
        .offer-split { display: flex; gap: 20px; margin-bottom: 24px; }
        .trade-side { flex: 1; }
        .side-label { text-align: center; font-size: 14px; font-weight: 500; margin-bottom: 12px; }
        .side-give { color: var(--danger); }
        .side-receive { color: var(--success); }
        .item-subcard { background: rgba(6, 20, 34, 0.5); border: 1px solid var(--border-light); border-radius: var(--radius); padding: 16px; height: 100%; }
        .item-subcard img { width: 100%; height: 120px; object-fit: contain; margin-bottom: 16px; }
        .item-subcard .name { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 4px; }
        .item-subcard .meta { font-size: 12px; color: var(--text-dim); }
        .offer-actions { display: flex; gap: 12px; }
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
            $give = ($tab === 'received') ? ['name'=>$off['r_name'], 'img'=>$off['r_image']] : ['name'=>$off['s_name'], 'img'=>$off['s_image']];
            $receive = ($tab === 'received') ? ['name'=>$off['s_name'], 'img'=>$off['s_image']] : ['name'=>$off['r_name'], 'img'=>$off['r_image']];
        ?>
        <div class="offer-card">
            <div class="offer-split">
                <div class="trade-side"><div class="side-label side-give">You Give</div><div class="item-subcard"><img src="<?= $give['img'] ?>"><div class="name"><?= $give['name'] ?></div><div class="meta">Wear: Factory New</div><div class="meta">Rarity: Covert</div></div></div>
                <div class="trade-side"><div class="side-label side-receive">You Receive</div><div class="item-subcard"><img src="<?= $receive['img'] ?>"><div class="name"><?= $receive['name'] ?></div><div class="meta">Wear: Factory New</div><div class="meta">Rarity: Covert</div></div></div>
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
