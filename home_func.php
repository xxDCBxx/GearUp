<?php
// home_func.php — Backend logic for the Home page
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

function get_featured_items($link, int $limit = 12): array {
    $sql = "SELECT ml.id, ml.price, ml.item_id,
                   i.name AS item_name, i.image AS item_image,
                   i.wear_rating, i.rarity, i.float_value, i.game,
                   u.name AS seller_name
            FROM market_listings ml
            JOIN items i ON ml.item_id = i.id
            JOIN users u ON ml.user_id = u.id
            ORDER BY ml.created_at DESC
            LIMIT ?";
            
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $items;
}

function game_info(string $game): array {
    return match(strtolower($game)) {
        'cs2'   => ['name' => 'Counter-Strike 2', 'logo' => 'logos/logo_cs2.png'],
        'dota2' => ['name' => 'Dota 2',           'logo' => 'logos/logo_dota2.png'],
        'rust'  => ['name' => 'Rust',             'logo' => 'logos/logo_rust.webp'],
        'tf2'   => ['name' => 'Team Fortress 2',  'logo' => 'logos/logo_tf2.png'],
        default => ['name' => 'Unknown Game',     'logo' => '']
    };
}

// Fetch the items for use in home.php
$featured_items = get_featured_items($link, 12);
?>
