<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION["id"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'make_offer') {
        $receiver_id = (int)$_POST['receiver_id'];
        $receiver_item_id = $_POST['receiver_item_id'];
        $sender_item_id = $_POST['sender_item_id'];

        if ($receiver_id && $receiver_item_id && $sender_item_id) {
            $stmt = mysqli_prepare($link, "INSERT INTO trade_offers (sender_id, receiver_id, sender_item_id, receiver_item_id, status) VALUES (?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, "iiss", $user_id, $receiver_id, $sender_item_id, $receiver_item_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header("Location: offers.php?tab=sent");
        exit;
    }

    if (in_array($action, ['accept_offer', 'decline_offer', 'cancel_offer'])) {
        $offer_id = (int)$_POST['offer_id'];
        $current_tab = $_POST['current_tab'] ?? 'received';

        if ($action === 'cancel_offer') {
            $stmt = mysqli_prepare($link, "UPDATE trade_offers SET status = 'cancelled' WHERE id = ? AND sender_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $offer_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } elseif ($action === 'decline_offer') {
            $stmt = mysqli_prepare($link, "UPDATE trade_offers SET status = 'declined' WHERE id = ? AND receiver_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $offer_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } elseif ($action === 'accept_offer') {
            $stmt = mysqli_prepare($link, "SELECT sender_id, receiver_id, sender_item_id, receiver_item_id FROM trade_offers WHERE id = ? AND receiver_id = ? AND status = 'pending'");
            mysqli_stmt_bind_param($stmt, "ii", $offer_id, $user_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $offer = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($offer) {
                mysqli_begin_transaction($link);
                try {
                    $update_stmt = mysqli_prepare($link, "UPDATE trade_offers SET status = 'accepted' WHERE id = ?");
                    mysqli_stmt_bind_param($update_stmt, "i", $offer_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);

                    $sender_items = array_filter(array_map('intval', explode(',', $offer['sender_item_id'])));
                    $receiver_items = array_filter(array_map('intval', explode(',', $offer['receiver_item_id'])));

                    foreach ($sender_items as $item_id) {
                        $swap_stmt = mysqli_prepare($link, "UPDATE user_items SET user_id = ? WHERE item_id = ? AND user_id = ? LIMIT 1");
                        mysqli_stmt_bind_param($swap_stmt, "iii", $user_id, $item_id, $offer['sender_id']);
                        mysqli_stmt_execute($swap_stmt);
                        mysqli_stmt_close($swap_stmt);
                    }

                    foreach ($receiver_items as $item_id) {
                        $swap_stmt = mysqli_prepare($link, "UPDATE user_items SET user_id = ? WHERE item_id = ? AND user_id = ? LIMIT 1");
                        mysqli_stmt_bind_param($swap_stmt, "iii", $offer['sender_id'], $item_id, $user_id);
                        mysqli_stmt_execute($swap_stmt);
                        mysqli_stmt_close($swap_stmt);
                    }

                    $history_stmt = mysqli_prepare($link, "INSERT INTO trade_history (user1_id, user2_id, user1_items, user2_items) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($history_stmt, "iiss", $offer['sender_id'], $user_id, $offer['sender_item_id'], $offer['receiver_item_id']);
                    mysqli_stmt_execute($history_stmt);
                    mysqli_stmt_close($history_stmt);

                    $all_traded_items = array_merge($sender_items, $receiver_items);
                    foreach ($all_traded_items as $traded_item) {
                        mysqli_query($link, "UPDATE trade_offers SET status = 'cancelled' WHERE status = 'pending' AND (FIND_IN_SET('$traded_item', sender_item_id) OR FIND_IN_SET('$traded_item', receiver_item_id))");
                    }

                    mysqli_commit($link);
                } catch (Exception $e) {
                    mysqli_rollback($link);
                }
            }
        }
        header("Location: offers.php?tab=" . $current_tab);
        exit;
    }
}

function offers_game_info(string $game): array {
    return match(strtolower($game)) {
        'cs2'   => ['name' => 'Counter-Strike 2', 'logo' => 'logos/logo_cs2.png'],
        'dota2' => ['name' => 'Dota 2',           'logo' => 'logos/logo_dota2.png'],
        'rust'  => ['name' => 'Rust',             'logo' => 'logos/logo_rust.webp'],
        'tf2'   => ['name' => 'Team Fortress 2',  'logo' => 'logos/logo_tf2.png'],
        default => ['name' => 'Unknown Game',     'logo' => '']
    };
}

function get_user_offers($link, $user_id, $tab) {
    $offers = [];
    $condition = ($tab === 'sent') ? "sender_id = ?" : "receiver_id = ?";
    $sql = "SELECT id as offer_id, sender_id, receiver_id, sender_item_id, receiver_item_id, status 
            FROM trade_offers WHERE $condition AND status = 'pending' ORDER BY created_at DESC";
    
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($res)) {
        $row['sender_items'] = fetch_items_for_offer($link, $row['sender_item_id']);
        $row['receiver_items'] = fetch_items_for_offer($link, $row['receiver_item_id']);
        $offers[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $offers;
}

function fetch_items_for_offer($link, $item_ids_str) {
    if (empty($item_ids_str)) return [];
    
    $ids = array_filter(array_map('intval', explode(',', $item_ids_str)));
    if (empty($ids)) return [];
    
    $in_clause = implode(',', $ids);
    $sql = "SELECT id, name, image, game, wear_rating, rarity FROM items WHERE id IN ($in_clause)";
    $res = mysqli_query($link, $sql);
    
    $items = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
    }
    return $items;
}
?>
