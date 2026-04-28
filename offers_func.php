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
        mysqli_stmt_bind_param($stmt, "iiii", $user_id, $_POST["receiver_id"], $_POST["sender_item_id"], $_POST["receiver_item_id"]);
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
    $sql = "SELECT to.id AS offer_id, to.status,
                   si.name AS s_name, si.image AS s_image, si.wear_rating AS s_wear, si.rarity AS s_rarity, si.float_value AS s_float,
                   ri.name AS r_name, ri.image AS r_image, ri.wear_rating AS r_wear, ri.rarity AS r_rarity, ri.float_value AS r_float
            FROM trade_offers `to`
            JOIN items si ON to.sender_item_id = si.id
            JOIN items ri ON to.receiver_item_id = ri.id
            WHERE to.$col = ? AND to.status = 'pending'
            ORDER BY to.created_at DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}
?>
