<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION["id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $offer_id = (int)($_POST["offer_id"] ?? 0);

    if ($action === "make_offer" && !empty($_POST["receiver_id"])) {
        $stmt = mysqli_prepare($link, "INSERT INTO trade_offers (sender_id, receiver_id, sender_item_id, receiver_item_id) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiss", $user_id, $_POST["receiver_id"], $_POST["sender_item_id"], $_POST["receiver_item_id"]);
        mysqli_stmt_execute($stmt);
        header("Location: offers.php?tab=sent");
        exit;
    }

    if ($offer_id > 0) {
        $where = ($action === "cancel_offer") ? "sender_id" : "receiver_id";
        $status = match($action) { "accept_offer" => "accepted", "decline_offer" => "declined", "cancel_offer" => "cancelled", default => "" };
        
        if ($status !== "") {
            $stmt = mysqli_prepare($link, "UPDATE trade_offers SET status = ? WHERE id = ? AND $where = ?");
            mysqli_stmt_bind_param($stmt, "sii", $status, $offer_id, $user_id);
            mysqli_stmt_execute($stmt);
        }
    }
    header("Location: offers.php?tab=" . ($_POST["current_tab"] ?? "received"));
    exit;
}

function get_user_offers($link, $user_id, $type = 'received') {
    $col = ($type === 'received') ? "receiver_id" : "sender_id";
    $sql = "SELECT id AS offer_id, status, sender_item_id, receiver_item_id
            FROM trade_offers
            WHERE $col = ? AND status = 'pending'
            ORDER BY created_at DESC";
            
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $raw_offers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    
    $offers = [];
    foreach ($raw_offers as $off) {
        $offers[] = [
            'offer_id' => $off['offer_id'],
            'status' => $off['status'],
            'sender_items' => get_items_by_ids($link, $off['sender_item_id']),
            'receiver_items' => get_items_by_ids($link, $off['receiver_item_id'])
        ];
    }
    return $offers;
}

function get_items_by_ids($link, $ids_string) {
    if (empty($ids_string)) return [];
    
    $ids = array_filter(array_map('intval', explode(',', $ids_string)));
    if (empty($ids)) return [];
    
    $in_clause = implode(',', $ids);
    $res = mysqli_query($link, "SELECT id, name, image, wear_rating, rarity, float_value, game FROM items WHERE id IN ($in_clause)");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function offers_game_info(string $game): array {
    return match(strtolower($game)) {
        'cs2'   => ['name' => 'Counter-Strike 2', 'logo' => 'logos/logo_cs2.png'],
        'dota2' => ['name' => 'Dota 2',           'logo' => 'logos/logo_dota2.png'],
        'rust'  => ['name' => 'Rust',             'logo' => 'logos/logo_rust.webp'],
        'tf2'   => ['name' => 'Team Fortress 2',  'logo' => 'logos/logo_tf2.png'],
        default => ['name' => 'Unknown',          'logo' => ''],
    };
}
?>
