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
                    // 1. Mark offer as accepted
                    $update_stmt = mysqli_prepare($link, "UPDATE trade_offers SET status = 'accepted' WHERE id = ?");
                    mysqli_stmt_bind_param($update_stmt, "i", $offer_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);

                    $sender_items = array_filter(array_map('intval', explode(',', $offer['sender_item_id'])));
                    $receiver_items = array_filter(array_map('intval', explode(',', $offer['receiver_item_id'])));

                    // 2. Transfer SENDER items to RECEIVER
                    foreach ($sender_items as $item_id) {
                        $del = mysqli_prepare($link, "DELETE FROM market_listings WHERE item_id = ? AND user_id = ?");
                        mysqli_stmt_bind_param($del, "ii", $item_id, $offer['sender_id']);
                        mysqli_stmt_execute($del);
                        $was_market = mysqli_stmt_affected_rows($del) > 0;
                        mysqli_stmt_close($del);

                        if ($was_market) {
                            $ins = mysqli_prepare($link, "INSERT INTO user_items (user_id, item_id) VALUES (?, ?)");
                            mysqli_stmt_bind_param($ins, "ii", $user_id, $item_id);
                            mysqli_stmt_execute($ins);
                            mysqli_stmt_close($ins);
                        } else {
                            $swap = mysqli_prepare($link, "UPDATE user_items SET user_id = ? WHERE item_id = ? AND user_id = ? LIMIT 1");
                            mysqli_stmt_bind_param($swap, "iii", $user_id, $item_id, $offer['sender_id']);
                            mysqli_stmt_execute($swap);
                            mysqli_stmt_close($swap);
                        }
                    }

                    // 3. Transfer RECEIVER items to SENDER
                    foreach ($receiver_items as $item_id) {
                        $del = mysqli_prepare($link, "DELETE FROM market_listings WHERE item_id = ? AND user_id = ?");
                        mysqli_stmt_bind_param($del, "ii", $item_id, $user_id);
                        mysqli_stmt_execute($del);
                        $was_market = mysqli_stmt_affected_rows($del) > 0;
                        mysqli_stmt_close($del);

                        if ($was_market) {
                            $ins = mysqli_prepare($link, "INSERT INTO user_items (user_id, item_id) VALUES (?, ?)");
                            mysqli_stmt_bind_param($ins, "ii", $offer['sender_id'], $item_id);
                            mysqli_stmt_execute($ins);
                            mysqli_stmt_close($ins);
                        } else {
                            $swap = mysqli_prepare($link, "UPDATE user_items SET user_id = ? WHERE item_id = ? AND user_id = ? LIMIT 1");
                            mysqli_stmt_bind_param($swap, "iii", $offer['sender_id'], $item_id, $user_id);
                            mysqli_stmt_execute($swap);
                            mysqli_stmt_close($swap);
                        }
                    }

                    // 4. Record the trade in history
                    $history_stmt = mysqli_prepare($link, "INSERT INTO trade_history (user1_id, user2_id, user1_items, user2_items) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($history_stmt, "iiss", $offer['sender_id'], $user_id, $offer['sender_item_id'], $offer['receiver_item_id']);
                    mysqli_stmt_execute($history_stmt);
                    $history_id = mysqli_insert_id($link);
                    mysqli_stmt_close($history_stmt);

                    // 5. Cancel any other pending offers that involve the traded items
                    $all_traded_items = array_merge($sender_items, $receiver_items);
                    if (!empty($all_traded_items)) {
                        $conditions = [];
                        foreach ($all_traded_items as $traded_item) {
                            $escaped = (int)$traded_item;
                            $conditions[] = "FIND_IN_SET('$escaped', sender_item_id) > 0";
                            $conditions[] = "FIND_IN_SET('$escaped', receiver_item_id) > 0";
                        }
                        $cancel_sql = "UPDATE trade_offers SET status = 'cancelled' WHERE status = 'pending' AND id != ? AND (" . implode(" OR ", $conditions) . ")";
                        $cancel_stmt = mysqli_prepare($link, $cancel_sql);
                        mysqli_stmt_bind_param($cancel_stmt, "i", $offer_id);
                        mysqli_stmt_execute($cancel_stmt);
                        mysqli_stmt_close($cancel_stmt);
                    }

                    // 6. Fetch user details and item names for the email receipt
                    $user_info_sql = "SELECT id, email, credits FROM users WHERE id IN (?, ?)";
                    $ui_stmt = mysqli_prepare($link, $user_info_sql);
                    mysqli_stmt_bind_param($ui_stmt, "ii", $offer['sender_id'], $user_id);
                    mysqli_stmt_execute($ui_stmt);
                    $ui_res = mysqli_stmt_get_result($ui_stmt);
                    
                    $trade_users = [];
                    while ($u_row = mysqli_fetch_assoc($ui_res)) {
                        $trade_users[$u_row['id']] = $u_row;
                    }
                    mysqli_stmt_close($ui_stmt);

                    $sender_names = [];
                    if (!empty($sender_items)) {
                        $placeholders = implode(',', array_fill(0, count($sender_items), '?'));
                        $types = str_repeat('i', count($sender_items));
                        $name_stmt = mysqli_prepare($link, "SELECT name FROM items WHERE id IN ($placeholders)");
                        mysqli_stmt_bind_param($name_stmt, $types, ...$sender_items);
                        mysqli_stmt_execute($name_stmt);
                        $name_res = mysqli_stmt_get_result($name_stmt);
                        while ($row = mysqli_fetch_assoc($name_res)) { $sender_names[] = $row['name']; }
                        mysqli_stmt_close($name_stmt);
                    }

                    $receiver_names = [];
                    if (!empty($receiver_items)) {
                        $placeholders = implode(',', array_fill(0, count($receiver_items), '?'));
                        $types = str_repeat('i', count($receiver_items));
                        $name_stmt = mysqli_prepare($link, "SELECT name FROM items WHERE id IN ($placeholders)");
                        mysqli_stmt_bind_param($name_stmt, $types, ...$receiver_items);
                        mysqli_stmt_execute($name_stmt);
                        $name_res = mysqli_stmt_get_result($name_stmt);
                        while ($row = mysqli_fetch_assoc($name_res)) { $receiver_names[] = $row['name']; }
                        mysqli_stmt_close($name_stmt);
                    }

                    // Build and send Sender Email
                    if (function_exists('generate_receipt_email_body')) {
                        $sender_item_rows = [];
                        foreach ($sender_names as $n) $sender_item_rows[] = ['name' => 'Gave Away - ' . $n, 'price' => '--'];
                        foreach ($receiver_names as $n) $sender_item_rows[] = ['name' => 'Received - ' . $n, 'price' => '--'];
                        
                        $sender_credits = number_format($trade_users[$offer['sender_id']]['credits'] ?? 0, 2);
                        $sender_html = generate_receipt_email_body(
                            "Trade Completed",
                            $sender_item_rows,
                            ['Initial Balance' => '$' . $sender_credits, 'New Balance' => '$' . $sender_credits],
                            ['Confirmation ID' => $history_id, 'Date Confirmed' => date('r')]
                        );

                        // Build and send Receiver Email
                        $receiver_item_rows = [];
                        foreach ($receiver_names as $n) $receiver_item_rows[] = ['name' => 'Gave Away - ' . $n, 'price' => '--'];
                        foreach ($sender_names as $n) $receiver_item_rows[] = ['name' => 'Received - ' . $n, 'price' => '--'];

                        $receiver_credits = number_format($trade_users[$user_id]['credits'] ?? 0, 2);
                        $receiver_html = generate_receipt_email_body(
                            "Trade Completed",
                            $receiver_item_rows,
                            ['Initial Balance' => '$' . $receiver_credits, 'New Balance' => '$' . $receiver_credits],
                            ['Confirmation ID' => $history_id, 'Date Confirmed' => date('r')]
                        );

                        $subject = "Trade Completed - GEARUP!";
                        if (!empty($trade_users[$offer['sender_id']]['email'])) {
                            send_smtp_email($trade_users[$offer['sender_id']]['email'], $subject, $sender_html, EMAIL_SMTP_FROM, EMAIL_SMTP_FROM_NAME, true);
                        }
                        if (!empty($trade_users[$user_id]['email'])) {
                            send_smtp_email($trade_users[$user_id]['email'], $subject, $receiver_html, EMAIL_SMTP_FROM, EMAIL_SMTP_FROM_NAME, true);
                        }
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
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = mysqli_prepare($link, "SELECT id, name, image, game, wear_rating, rarity FROM items WHERE id IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, $types, ...$ids);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $items;
}
?>
