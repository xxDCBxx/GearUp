<?php
require_once "profile_func.php";
$active_page = 'profile';

$profile = get_user_profile($link, $user_id);
$active_listings = get_active_listings($link, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .profile-layout {
            display: flex;
            gap: 40px;
            align-items: flex-start;
            max-width: 1300px;
            margin: 0 auto;
        }

        .profile-sidebar {
            width: 340px;
            background: transparent;
            border: 2px solid var(--accent);
            border-radius: 6px;
            padding: 32px 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-shrink: 0;
        }
        .profile-sidebar h2 {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 500;
            color: #fff;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 30px;
            line-height: 1.2;
        }
        .profile-avatar {
            width: 140px;
            height: 140px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .profile-avatar svg { 
            width: 120px; 
            height: 120px; 
            color: var(--bg-dark); 
            margin-top: 20px; 
        }
        .profile-username-display {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            margin-bottom: 30px;
            letter-spacing: 1px;
        }
        
        .edit-form { width: 100%; display: flex; flex-direction: column; gap: 16px; }
        .input-wrapper { position: relative; width: 100%; }
        .input-wrapper input {
            width: 100%;
            background: transparent;
            border: 1px solid var(--text-muted);
            border-radius: 4px;
            padding: 12px 40px 12px 16px;
            color: var(--text);
            font-family: var(--font-body);
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }
        .input-wrapper input:focus { border-color: var(--accent); }
        .input-wrapper input::placeholder { color: var(--text-muted); }
        .input-wrapper svg {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        
        .btn-save {
            background: #207bc1;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }
        .btn-save:hover { background: #2a91e0; }

        .profile-main { flex: 1; min-width: 0; }
        .listings-header {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 30px;
        }
        .listings-labels {
            display: flex;
            font-size: 18px;
            font-weight: 400;
            color: #fff;
            margin-bottom: 12px;
        }
        .label-item { flex: 1; }
        .label-price { width: 150px; }
        .label-action { width: 160px; }
        
        .listing-card {
            background: var(--bg-dark);
            border: 1px solid var(--text-muted);
            border-radius: 6px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            transition: border-color 0.2s;
        }
        .listing-card:hover { border-color: var(--accent); }
        
        .listing-img-box {
            width: 80px;
            height: 80px;
            background: var(--bg-card-2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            flex-shrink: 0;
            margin-right: 20px;
        }
        .listing-img-box img { width: 100%; height: 100%; object-fit: contain; }
        
        .listing-details { flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .listing-name {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }
        .listing-stats { font-size: 12px; color: var(--text-dim); line-height: 1.4; }
        
        .listing-price {
            width: 150px;
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 500;
            color: #fff;
        }
        
        .listing-action { width: 160px; text-align: right; }
        .btn-cancel {
            background: #f73636;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            transition: filter 0.2s;
        }
        .btn-cancel:hover { filter: brightness(1.15); }
        
        .empty-listings {
            padding: 40px;
            text-align: center;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-muted);
        }

        @media (max-width: 900px) {
            .profile-layout { flex-direction: column; align-items: center; }
            .profile-sidebar { width: 100%; max-width: 400px; }
            .listing-card { flex-wrap: wrap; gap: 15px; }
            .listing-price { width: auto; flex: 1; text-align: center; }
            .listing-action { width: 100%; text-align: center; }
            .listings-labels { display: none; }
        }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page">
    
    <?php if ($update_success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($update_success) ?></div>
    <?php endif; ?>
    <?php if ($update_error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($update_error) ?></div>
    <?php endif; ?>

    <div class="profile-layout">
        <aside class="profile-sidebar">
            <h2>Account<br>Information</h2>
            
            <div class="profile-avatar">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>
            
            <div class="profile-username-display"><?= htmlspecialchars($profile['name']) ?></div>
            
            <form class="edit-form" method="POST" action="">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="input-wrapper">
                    <input type="text" name="username" value="<?= htmlspecialchars($profile['name']) ?>" placeholder="Username" required>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                </div>
                
                <div class="input-wrapper">
                    <input type="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" placeholder="Email" required>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                </div>
                
                <div class="input-wrapper">
                    <input type="password" name="password" placeholder="New Password (Optional)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                </div>

                <div class="input-wrapper">
                    <input type="password" name="confirm_password" placeholder="Confirm Password">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                </div>
                
                <button type="submit" class="btn-save">Save Changes</button>
            </form>
        </aside>

        <main class="profile-main">
            <h1 class="listings-header">My Active Listings</h1>
            
            <?php if (!empty($active_listings)): ?>
                <div class="listings-labels">
                    <div class="label-item">Item</div>
                    <div class="label-price">Listed for:</div>
                    <div class="label-action"></div>
                </div>
                
                <?php foreach ($active_listings as $listing): ?>
                <div class="listing-card">
                    <div class="listing-img-box">
                        <img src="<?= htmlspecialchars($listing['image'] ?? 'item_images/placeholder.png') ?>" alt="">
                    </div>
                    
                    <div class="listing-details">
                        <div class="listing-name"><?= htmlspecialchars($listing['name']) ?></div>
                        <div class="listing-stats">
                            Wear Rating: <span style="<?= profile_wear_class($listing['wear_rating']) ?>"><?= htmlspecialchars($listing['wear_rating'] ?? 'N/A') ?></span><br>
                            Rarity: <span style="<?= profile_rarity_class($listing['rarity']) ?>"><?= htmlspecialchars($listing['rarity'] ?? 'N/A') ?></span><br>
                            Float: <span style="color: #e05050;"><?= htmlspecialchars($listing['float_value'] ?? '0.00000') ?></span>
                        </div>
                    </div>
                    
                    <div class="listing-price">
                        $<?= number_format($listing['price'], 2) ?>
                    </div>
                    
                    <div class="listing-action">
                        <button type="button" class="btn-cancel" onclick="openCancelModal(<?= $listing['listing_id'] ?>)">Cancel Listing</button>
                    </div>
                </div>
                <?php endforeach; ?>
                
            <?php else: ?>
                <div class="empty-listings">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.3; margin-bottom:15px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    <p>You currently have no active listings on the market.</p>
                </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>

<div class="modal-overlay" id="cancelModal">
    <div class="modal">
        <div class="modal-title">Cancel Listing</div>
        <p style="font-size:14px; color:var(--text-dim); margin-bottom:20px;">Are you sure you want to cancel this listing? The item will be returned to your inventory.</p>
        <form method="POST" id="cancelForm">
            <input type="hidden" name="action" value="cancel_listing">
            <input type="hidden" name="listing_id" id="modalCancelListingId">
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" style="border: 1px solid var(--text-muted); color: var(--text-dim); background: transparent; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-weight: 600;" onclick="closeCancelModal()">Keep Listing</button>
                <button type="submit" class="btn-cancel" style="font-size: 14px;">Confirm Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(listingId) {
    document.getElementById('modalCancelListingId').value = listingId;
    document.getElementById('cancelModal').classList.add('open');
}
function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('open');
}
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});
</script>
</body>
</html>
