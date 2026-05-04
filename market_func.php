<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

function get_market_listings($link, int $page = 1, int $per_page = 5, string $search = "", string $game = "", $min_p = null, $max_p = null, string $sort = "newest"): array {
    $offset = ($page - 1) * $per_page;
    $where = ["1=1"];
    $params = [];
    $types = "";

    if ($search !== "") {
        $where[] = "i.name LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }
    if ($game !== "") {
        $where[] = "i.game = ?";
        $params[] = $game;
        $types .= "s";
    }
    if ($min_p !== null && $min_p !== "") {
        $where[] = "ml.price >= ?";
        $params[] = (float)$min_p;
        $types .= "d";
    }
    if ($max_p !== null && $max_p !== "") {
        $where[] = "ml.price <= ?";
        $params[] = (float)$max_p;
        $types .= "d";
    }

    $order = match($sort) {
        "price_asc"  => "ml.price ASC",
        "price_desc" => "ml.price DESC",
        "name_asc"   => "i.name ASC",
        default      => "ml.created_at DESC"
    };

    $sql = "SELECT ml.id AS listing_id, ml.user_id AS owner_id, ml.price, 
                   i.id AS item_id, i.name, i.image, i.wear_rating, i.rarity, i.float_value, i.game, 
                   u.name AS seller_name
            FROM market_listings ml
            JOIN items i ON ml.item_id = i.id
            JOIN users u ON ml.user_id = u.id
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY $order 
            LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($link, $sql);
    $bind_types = $types . "ii";
    $bind_params = array_merge($params, [$per_page, $offset]);
    if ($bind_types !== "") {
        mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);
    }
    mysqli_stmt_execute($stmt);
    $listings = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $count_sql = "SELECT COUNT(*) FROM market_listings ml JOIN items i ON ml.item_id = i.id WHERE " . implode(" AND ", $where);
    $c_stmt = mysqli_prepare($link, $count_sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($c_stmt, $types, ...$params);
    }
    mysqli_stmt_execute($c_stmt);
    mysqli_stmt_bind_result($c_stmt, $total);
    mysqli_stmt_fetch($c_stmt);
    mysqli_stmt_close($c_stmt);

    return ["listings" => $listings, "total" => (int)$total];
}

function count_user_listings($link, int $user_id): int {
    $stmt = mysqli_prepare($link, "SELECT COUNT(*) FROM market_listings WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return (int)$count;
}

function get_my_trade_inventory($link, int $user_id): array {
    $sql = "SELECT ui.id AS user_item_id, i.id AS item_id, i.name, i.image, i.game, i.wear_rating, i.rarity 
            FROM user_items ui 
            JOIN items i ON ui.item_id = i.id 
            WHERE ui.user_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $inventory = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $inventory;
}

function process_market_purchase($link, $buyer_id, $listing_id) {
    mysqli_begin_transaction($link);
    try {
        $stmt = mysqli_prepare($link, "SELECT ml.item_id, ml.user_id as seller_id, ml.price, u.credits as buyer_credits 
                                       FROM market_listings ml 
                                       JOIN users u ON u.id = ? 
                                       WHERE ml.id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $listing_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $listing = mysqli_fetch_assoc($result);

        if (!$listing) { throw new Exception("Listing no longer available."); }
        if ($listing['seller_id'] == $buyer_id) { throw new Exception("You cannot buy your own item."); }
        if ($listing['buyer_credits'] < $listing['price']) { throw new Exception("Insufficient credits."); }

        $update_buyer = mysqli_prepare($link, "UPDATE users SET credits = credits - ? WHERE id = ?");
        mysqli_stmt_bind_param($update_buyer, "di", $listing['price'], $buyer_id);
        mysqli_stmt_execute($update_buyer);

        $update_seller = mysqli_prepare($link, "UPDATE users SET credits = credits + ? WHERE id = ?");
        mysqli_stmt_bind_param($update_seller, "di", $listing['price'], $listing['seller_id']);
        mysqli_stmt_execute($update_seller);

        $delete_listing = mysqli_prepare($link, "DELETE FROM market_listings WHERE id = ?");
        mysqli_stmt_bind_param($delete_listing, "i", $listing_id);
        mysqli_stmt_execute($delete_listing);

        $add_inventory = mysqli_prepare($link, "INSERT INTO user_items (user_id, item_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($add_inventory, "ii", $buyer_id, $listing['item_id']);
        mysqli_stmt_execute($add_inventory);

        mysqli_commit($link);
        return ["success" => true, "new_balance" => number_format($listing['buyer_credits'] - $listing['price'], 2)];
    } catch (Exception $e) {
        mysqli_rollback($link);
        return ["success" => false, "error" => $e->getMessage()];
    }
}

function market_game_info(string $game): array {
    return match(strtolower($game)) {
        'cs2'   => ['name' => 'Counter-Strike 2', 'logo' => 'logos/logo_cs2.png'],
        'dota2' => ['name' => 'Dota 2',           'logo' => 'logos/logo_dota2.png'],
        'rust'  => ['name' => 'Rust',             'logo' => 'logos/logo_rust.webp'],
        'tf2'   => ['name' => 'Team Fortress 2',  'logo' => 'logos/logo_tf2.png'],
        default => ['name' => 'All Games',        'logo' => '']
    };
}
?>
