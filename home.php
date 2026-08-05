<?php
require_once "home_func.php";
$active_page = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home — <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="nav_styles.css">
    <style>
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            padding: 56px 0 48px;
        }
        .hero-text { flex: 1; }
        .hero-eyebrow {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 14px;
        }
        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(26px, 3vw, 42px);
            font-weight: 700;
            line-height: 1.15;
            color: #fff;
            margin-bottom: 16px;
            max-width: 560px;
        }
        .hero-title em { font-style: normal; color: var(--accent); }
        .hero-sub {
            font-size: 15px;
            color: var(--text-dim);
            max-width: 440px;
            margin-bottom: 28px;
        }
        .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .hero-logos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            flex-shrink: 0;
        }
        .hero-logo-box {
            width: 100px;
            height: 100px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.2s, transform 0.2s;
        }
        .hero-logo-box:hover { border-color: var(--border-light); transform: scale(1.05); }
        .hero-logo-box img { width: 56px; height: 56px; object-fit: contain; }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }
        .section-title {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .carousel-outer {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 40px;
            margin: 0 auto;
            width: 100%;
        }
        .carousel-track-wrap {
            width: 1282px; 
            overflow: hidden;
            margin: 0 auto;
            flex-shrink: 0;
        }
        .carousel-track {
            display: flex;
            gap: 16px;
            transition: transform 0.6s cubic-bezier(0.23, 1, 0.32, 1);
            padding: 12px 1px;
            margin: 0;
        }
        .carousel-card {
            flex: 0 0 200px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            cursor: default;
            transition: transform 0.2s, border-color 0.2s;
            box-sizing: border-box;
        }
        .carousel-card:hover {
            transform: translateY(-4px);
            border-color: var(--border-light);
        }
        .carousel-card-game {
            font-size: 11px;
            color: var(--text-muted);
            padding: 10px 12px 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .carousel-card-game img { width: 14px; height: 14px; object-fit: contain; }
        .carousel-card-img {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            min-height: 110px;
            background: var(--bg-card-2);
        }
        .carousel-card-img img { width: 100px; height: 72px; object-fit: contain; }
        .carousel-card-body { padding: 12px; }
        .carousel-card-name {
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .carousel-card-price {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 700;
            color: var(--accent);
            margin-top: 4px;
        }
        .carousel-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            background: var(--bg-card-2);
            border: 1px solid var(--border-light);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
            z-index: 10;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .carousel-arrow:hover { 
            background: var(--accent); 
            color: #fff; 
            border-color: var(--accent);
            transform: translateY(-50%) scale(1.1);
        }
        .carousel-arrow.left { left: -15px; }
        .carousel-arrow.right { right: -15px; }

        .games-strip {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 60px;
            padding: 24px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: 48px;
            background: linear-gradient(90deg, transparent, rgba(74, 159, 212, 0.03), transparent);
        }
        .games-strip-label {
            font-family: var(--font-display);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-muted);
            white-space: nowrap;
        }
        .games-strip-logos {
            display: flex;
            align-items: center;
            gap: 40px;
        }
        .games-strip-logos img {
            width: auto;
            filter: grayscale(1) brightness(1.5) opacity(0.8);
            transition: all 0.3s ease;
            object-fit: contain;
        }
        .games-strip-logos img:hover {
            filter: grayscale(0) brightness(1.1) opacity(1) drop-shadow(0 0 10px rgba(74,159,212,0.4));
            transform: translateY(-4px) scale(1.05);
        }
        .games-strip-logos img[alt*="Counter-Strike"] { 
            height: 15px; 
            filter: brightness(0) invert(1) opacity(0.8);
        }
        .games-strip-logos img[alt*="Counter-Strike"]:hover {
            filter: invert(62%) sepia(8%) saturate(3085%) hue-rotate(167deg) brightness(91%) contrast(89%) drop-shadow(0 0 12px var(--accent));
        }
        .games-strip-logos img[alt*="Dota"] { height: 36px; }
        .games-strip-logos img[alt*="Rust"] { height: 42px; }
        .games-strip-logos img[alt*="Team Fortress"] { height: 72px; }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page">
    <div class="hero">
        <div class="hero-text">
            <div class="hero-eyebrow">The Cross-Game Trading Platform</div>
            <h1 class="hero-title">Trade Items for <em>Counter-Strike 2</em>, Dota 2, Rust &amp; TF2</h1>
            <p class="hero-sub">Buy, sell, and trade in-game items across your favourite games — all in one place.</p>
            <div class="hero-actions">
                <a href="market.php" class="btn btn-accent">Browse Market</a>
                <a href="inventory.php" class="btn btn-ghost">My Inventory</a>
            </div>
        </div>
        <div class="hero-logos">
            <div class="hero-logo-box"><img src="logos/logo_cs2.png" alt="CS2"></div>
            <div class="hero-logo-box"><img src="logos/logo_dota2.png" alt="Dota 2"></div>
            <div class="hero-logo-box"><img src="logos/logo_rust.webp" alt="Rust"></div>
            <div class="hero-logo-box"><img src="logos/logo_tf2.png" alt="TF2"></div>
        </div>
    </div>

    <div class="games-strip">
        <span class="games-strip-label">Supported Titles</span>
        <div class="games-strip-logos">
            <img src="supported_games/sg_logo_cs2.png" alt="Counter-Strike 2">
            <img src="supported_games/sg_logo_dota2.png" alt="Dota 2">
            <img src="supported_games/sg_logo_rust.png" alt="Rust">
            <img src="supported_games/sg_logo_tf2.png" alt="Team Fortress 2">
        </div>
    </div>

    <div class="section-header">
        <div class="section-title">Featured Items</div>
    </div>

    <div class="carousel-outer">
        <button class="carousel-arrow left" id="carouselPrev" aria-label="Previous">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <div class="carousel-track-wrap">
            <div class="carousel-track" id="carouselTrack">
                <?php if (empty($featured_items)): ?>
                    <div style="padding: 40px; text-align: center; width: 100%; color: var(--text-dim);">
                        No active market listings found.
                    </div>
                <?php else: ?>
                    <?php foreach ($featured_items as $item): $gi = game_info($item['game']); ?>
                    <div class="carousel-card">
                        <div class="carousel-card-game">
                            <?php if($gi['logo']): ?><img src="<?= htmlspecialchars($gi['logo']) ?>" alt=""><?php endif; ?>
                            <?= htmlspecialchars($gi['name']) ?>
                        </div>
                        <div class="carousel-card-img">
                            <img src="<?= htmlspecialchars($item['item_image'] ?? 'item_images/placeholder.png') ?>"
                                 alt="<?= htmlspecialchars($item['item_name']) ?>"
                                 onerror="this.src='item_images/placeholder.png'">
                        </div>
                        <div class="carousel-card-body">
                            <div class="carousel-card-name"><?= htmlspecialchars($item['item_name']) ?></div>
                            <div class="carousel-card-price">$<?= number_format($item['price'], 2) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <button class="carousel-arrow right" id="carouselNext" aria-label="Next">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </div>
</div>

<script>
const track = document.getElementById('carouselTrack');
const prevBtn = document.getElementById('carouselPrev');
const nextBtn = document.getElementById('carouselNext');

let currentPx = 0;
let autoSlideTimer;

function slide(dir) {
    const cards = document.querySelectorAll('.carousel-card');
    if (cards.length <= 6) return;

    const cardWidth = 200;
    const gap = 16;
    const step = cardWidth + gap;
    const maxPx = (cards.length - 6) * step;
    
    currentPx += dir * step;
    
    if (currentPx > maxPx) {
        currentPx = 0;
    } else if (currentPx < 0) {
        currentPx = maxPx;
    }
    
    track.style.transform = `translateX(-${currentPx}px)`;
}

function startAutoSlide() {
    autoSlideTimer = setInterval(() => {
        slide(1);
    }, 4000);
}

function resetTimer() {
    clearInterval(autoSlideTimer);
    startAutoSlide();
}

prevBtn.addEventListener('click', () => {
    slide(-1);
    resetTimer();
});

nextBtn.addEventListener('click', () => {
    slide(1);
    resetTimer();
});

window.addEventListener('load', () => {
    currentPx = 0;
    track.style.transform = 'translateX(0)';
    startAutoSlide();
});

window.addEventListener('resize', () => {
    currentPx = 0;
    track.style.transform = 'translateX(0)';
});
</script>
</body>
</html>
