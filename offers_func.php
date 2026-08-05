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
        $sender_items = $_POST["sender_item_id"] ?? "";
        
        // Prevent making offer if any of the items are already in a pending offer
        if (!empty($sender_items)) {
            $s_ids = array_filter(array_map('intval', explode(',', $sender_items)));
            $conflict = false;
            if (!empty($s_ids)) {
                $check_sql = "SELECT sender_item_id FROM trade_offers WHERE sender_id = ? AND status = 'pending'";
                $check_stmt = mysqli_prepare($link, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "i", $user_id);
                mysqli_stmt_execute($check_stmt);
                $check_res = mysqli_stmt_get_result($check_stmt);
                while ($row = mysqli_fetch_assoc($check_res)) {
                    $pending_items = array_filter(array_map('intval', explode(',', $row['sender_item_id'])));
                    if (array_intersect($s_ids, $pending_items)) {
                        $conflict = true;
                        break;
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
            if ($conflict) {
                header("Location: market.php?error=item_in_use");
                exit;
            }
        }

        $stmt = mysqli_prepare($link, "INSERT INTO trade_offers (sender_id, receiver_id, sender_item_id, receiver_item_id) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiss", $user_id, $_POST["receiver_id"], $_POST["sender_item_id"], $_POST["receiver_item_id"]);
        mysqli_stmt_execute($stmt);
        header("Location: offers.php?tab=sent");
        exit;
    }

    if ($offer_id > 0) {
        $where = ($action === "cancel_offer") ? "sender_id" : "receiver_id";
        $status = match($action) { "accept_offer" => "accepted", "decline_offer" => "declined", "cancel_offer" => "cancelled", default => "" };
        
        if ($status === "accepted") {
            $sel = mysqli_prepare($link, "SELECT sender_id, receiver_id, sender_item_id, receiver_item_id FROM trade_offers WHERE id = ? AND receiver_id = ? AND status = 'pending'");
            mysqli_stmt_bind_param($sel, "ii", $offer_id, $user_id);
            mysqli_stmt_execute($sel);
            $res = mysqli_stmt_get_result($sel);
            $offer = mysqli_fetch_assoc($res);
            mysqli_stmt_close($sel);
            
            if ($offer) {
                mysqli_begin_transaction($link);
                try {
                    $upd = mysqli_prepare($link, "UPDATE trade_offers SET status = 'accepted' WHERE id = ?");
                    mysqli_stmt_bind_param($upd, "i", $offer_id);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                    
                    $s_ids = array_filter(array_map('intval', explode(',', $offer['sender_item_id'])));
                    if (!empty($s_ids)) {
                        // Move sender items to receiver; also remove from market if listed
                        $move_stmt = mysqli_prepare($link, "UPDATE user_items SET user_id = ? WHERE item_id = ? AND user_id = ? LIMIT 1");
                        $del_market = mysqli_prepare($link, "DELETE FROM market_listings WHERE user_id = ? AND item_id = ? LIMIT 1");
                        foreach ($s_ids as $s_item_id) {
                            // Remove any market listing for this sender item first
                            mysqli_stmt_bind_param($del_market, "ii", $offer['sender_id'], $s_item_id);
                            mysqli_stmt_execute($del_market);
                            
                            mysqli_stmt_bind_param($move_stmt, "iii", $offer['receiver_id'], $s_item_id, $offer['sender_id']);
                            mysqli_stmt_execute($move_stmt);
                        }
                        mysqli_stmt_close($move_stmt);
                        mysqli_stmt_close($del_market);
                    }
                    
                    $r_ids = array_filter(array_map('intval', explode(',', $offer['receiver_item_id'])));
                    if (!empty($r_ids)) {
                        $del_listing = mysqli_prepare($link, "DELETE FROM market_listings WHERE user_id = ? AND item_id = ? LIMIT 1");
                        $ins_item = mysqli_prepare($link, "INSERT INTO user_items (user_id, item_id) VALUES (?, ?)");
                        foreach ($r_ids as $r_item_id) {
                            mysqli_stmt_bind_param($del_listing, "ii", $offer['receiver_id'], $r_item_id);
                            mysqli_stmt_execute($del_listing);
                            
                            mysqli_stmt_bind_param($ins_item, "ii", $offer['sender_id'], $r_item_id);
                            mysqli_stmt_execute($ins_item);
                        }
                        mysqli_stmt_close($del_listing);
                        mysqli_stmt_close($ins_item);
                    }
                    
                    // Cancel other pending offers that involve any of these items
                    $all_items = array_merge($s_ids, $r_ids);
                    if (!empty($all_items)) {
                        $conditions = [];
                        foreach ($all_items as $itemId) {
                            $escaped = (int)$itemId;
                            $conditions[] = "FIND_IN_SET('$escaped', sender_item_id) > 0";
                            $conditions[] = "FIND_IN_SET('$escaped', receiver_item_id) > 0";
                        }
                        $cancel_sql = "UPDATE trade_offers SET status = 'cancelled' WHERE status = 'pending' AND id != ? AND (" . implode(" OR ", $conditions) . ")";
                        $cancel_stmt = mysqli_prepare($link, $cancel_sql);
                        mysqli_stmt_bind_param($cancel_stmt, "i", $offer_id);
                        mysqli_stmt_execute($cancel_stmt);
                        mysqli_stmt_close($cancel_stmt);
                    }
                    
                    $add_history = mysqli_prepare($link, "INSERT INTO trade_history (user1_id, user2_id, user1_items, user2_items) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($add_history, "iiss", $offer['sender_id'], $offer['receiver_id'], $offer['sender_item_id'], $offer['receiver_item_id']);
                    mysqli_stmt_execute($add_history);
                    mysqli_stmt_close($add_history);

                    mysqli_commit($link);
                } catch (Exception $e) {
                    mysqli_rollback($link);
                }
            }
        } elseif ($status !== "") {
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
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = mysqli_prepare($link, "SELECT id, name, image, wear_rating, rarity, float_value, game FROM items WHERE id IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, $types, ...$ids);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $items;
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
