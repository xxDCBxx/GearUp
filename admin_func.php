<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION["id"];

// Verify admin status
$admin_sql = "SELECT is_admin FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $admin_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $is_admin);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if ($is_admin != 1) {
    header("location: home.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    $redirect_tab = 'users';

    if ($action === "edit_user") {
        $target_id = (int)$_POST['target_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $credits = (float)$_POST['credits'];
        $is_admin_flag = isset($_POST['is_admin']) ? 1 : 0;

        $stmt = mysqli_prepare($link, "UPDATE users SET name = ?, email = ?, credits = ?, is_admin = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssdii", $username, $email, $credits, $is_admin_flag, $target_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_admin_success'] = "User updated successfully.";
        } else {
            $_SESSION['flash_admin_error'] = "Failed to update user.";
        }
        mysqli_stmt_close($stmt);
    }

    elseif ($action === "delete_user") {
        $target_id = (int)$_POST['target_id'];
        if ($target_id == $user_id) {
            $_SESSION['flash_admin_error'] = "You cannot delete yourself.";
        } else {
            $stmt = mysqli_prepare($link, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $target_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_admin_success'] = "User deleted successfully.";
            } else {
                $_SESSION['flash_admin_error'] = "Failed to delete user.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    elseif ($action === "approve_deletion") {
        $request_id = (int)$_POST['request_id'];
        $target_id = (int)$_POST['target_id'];
        
        mysqli_begin_transaction($link);
        try {
            mysqli_query($link, "DELETE FROM users WHERE id = $target_id");
            mysqli_query($link, "UPDATE deletion_requests SET status = 'approved' WHERE id = $request_id");
            mysqli_commit($link);
            $_SESSION['flash_admin_success'] = "User account permanently deleted.";
        } catch(Exception $e) {
            mysqli_rollback($link);
            $_SESSION['flash_admin_error'] = "Failed to delete user.";
        }
        $redirect_tab = 'deletions';
    }

    elseif ($action === "reject_deletion") {
        $request_id = (int)$_POST['request_id'];
        mysqli_query($link, "UPDATE deletion_requests SET status = 'rejected' WHERE id = $request_id");
        $_SESSION['flash_admin_success'] = "Deletion request rejected.";
        $redirect_tab = 'deletions';
    }

    elseif ($action === "approve_revert") {
        $request_id = (int)$_POST['request_id'];
        $type = $_POST['type'];
        $ref_id = (int)$_POST['reference_id'];

        mysqli_begin_transaction($link);
        try {
            if ($type === 'market') {
                $stmt = mysqli_prepare($link, "SELECT buyer_id, seller_id, item_id, price FROM market_history WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $ref_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $trade = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);

                if (!$trade) throw new Exception("Market history not found.");
                
                // Strict Check: Buyer must have the item, Seller must have the money
                $check_item = mysqli_query($link, "SELECT id FROM user_items WHERE user_id = {$trade['buyer_id']} AND item_id = {$trade['item_id']} LIMIT 1");
                if (mysqli_num_rows($check_item) == 0) throw new Exception("Buyer no longer has the item.");
                $ui_row = mysqli_fetch_assoc($check_item);
                
                $check_credits = mysqli_query($link, "SELECT credits FROM users WHERE id = {$trade['seller_id']}");
                $s_cred = mysqli_fetch_assoc($check_credits)['credits'];
                if ($s_cred < $trade['price']) throw new Exception("Seller does not have enough credits to refund.");

                // Revert
                mysqli_query($link, "UPDATE users SET credits = credits + {$trade['price']} WHERE id = {$trade['buyer_id']}");
                mysqli_query($link, "UPDATE users SET credits = credits - {$trade['price']} WHERE id = {$trade['seller_id']}");
                mysqli_query($link, "UPDATE user_items SET user_id = {$trade['seller_id']} WHERE id = {$ui_row['id']}");
            } 
            elseif ($type === 'trade') {
                $stmt = mysqli_prepare($link, "SELECT user1_id, user2_id, user1_items, user2_items FROM trade_history WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $ref_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $trade = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);

                if (!$trade) throw new Exception("Trade history not found.");

                $u1_items = array_filter(array_map('intval', explode(',', $trade['user1_items'])));
                $u2_items = array_filter(array_map('intval', explode(',', $trade['user2_items'])));

                // Check User 2 has User 1's items
                foreach($u1_items as $item_id) {
                    $chk = mysqli_query($link, "SELECT id FROM user_items WHERE user_id = {$trade['user2_id']} AND item_id = $item_id LIMIT 1");
                    if(mysqli_num_rows($chk) == 0) throw new Exception("User 2 no longer has the required items.");
                }
                // Check User 1 has User 2's items
                foreach($u2_items as $item_id) {
                    $chk = mysqli_query($link, "SELECT id FROM user_items WHERE user_id = {$trade['user1_id']} AND item_id = $item_id LIMIT 1");
                    if(mysqli_num_rows($chk) == 0) throw new Exception("User 1 no longer has the required items.");
                }

                // Revert Items
                foreach($u1_items as $item_id) {
                    mysqli_query($link, "UPDATE user_items SET user_id = {$trade['user1_id']} WHERE user_id = {$trade['user2_id']} AND item_id = $item_id LIMIT 1");
                }
                foreach($u2_items as $item_id) {
                    mysqli_query($link, "UPDATE user_items SET user_id = {$trade['user2_id']} WHERE user_id = {$trade['user1_id']} AND item_id = $item_id LIMIT 1");
                }
            }

            mysqli_query($link, "UPDATE revert_requests SET status = 'approved' WHERE id = $request_id");
            mysqli_commit($link);
            $_SESSION['flash_admin_success'] = "Trade reverted successfully.";
        } catch(Exception $e) {
            mysqli_rollback($link);
            $_SESSION['flash_admin_error'] = "Revert Failed (Strict Check): " . $e->getMessage();
        }
        $redirect_tab = 'reverts';
    }

    elseif ($action === "reject_revert") {
        $request_id = (int)$_POST['request_id'];
        mysqli_query($link, "UPDATE revert_requests SET status = 'rejected' WHERE id = $request_id");
        $_SESSION['flash_admin_success'] = "Revert request rejected.";
        $redirect_tab = 'reverts';
    }

    header("Location: admin.php?tab=" . $redirect_tab);
    exit;
}

function get_all_users($link) {
    $res = mysqli_query($link, "SELECT id, name, email, credits, is_admin FROM users ORDER BY id ASC");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function admin_game_info($game) {
    return match($game) {
        'cs2'   => ['name' => 'CS2',              'logo' => 'logos/logo_cs2.png'],
        'dota2' => ['name' => 'Dota 2',           'logo' => 'logos/logo_dota2.png'],
        'rust'  => ['name' => 'Rust',             'logo' => 'logos/logo_rust.webp'],
        'tf2'   => ['name' => 'Team Fortress 2',  'logo' => 'logos/logo_tf2.png'],
        default => ['name' => 'Unknown',          'logo' => '']
    };
}

function get_revert_requests($link) {
    $res = mysqli_query($link, "SELECT r.*, u.name as username FROM revert_requests r JOIN users u ON r.user_id = u.id WHERE r.status = 'pending' ORDER BY r.created_at ASC");
    $requests = mysqli_fetch_all($res, MYSQLI_ASSOC);
    
    foreach ($requests as &$req) {
        $tdata = ['type' => $req['type']];
        if ($req['type'] === 'market') {
            $stmt = mysqli_prepare($link, "SELECT mh.*, i.name as item_name, i.image, i.game, i.wear_rating, i.rarity, b.name as buyer_name, s.name as seller_name FROM market_history mh JOIN items i ON mh.item_id = i.id JOIN users b ON mh.buyer_id = b.id JOIN users s ON mh.seller_id = s.id WHERE mh.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $req['reference_id']);
            mysqli_stmt_execute($stmt);
            $trade_res = mysqli_stmt_get_result($stmt);
            $trade = mysqli_fetch_assoc($trade_res);
            mysqli_stmt_close($stmt);
            
            if ($trade) {
                $g_info = admin_game_info($trade['game']);
                $tdata['buyer_name'] = $trade['buyer_name'];
                $tdata['seller_name'] = $trade['seller_name'];
                $tdata['price'] = $trade['price'];
                $tdata['item'] = [
                    'name' => $trade['item_name'],
                    'image' => $trade['image'],
                    'wear' => $trade['wear_rating'],
                    'rarity' => $trade['rarity'],
                    'game_logo' => $g_info['logo']
                ];
            }
        } elseif ($req['type'] === 'trade') {
            $stmt = mysqli_prepare($link, "SELECT th.*, u1.name as user1_name, u2.name as user2_name FROM trade_history th JOIN users u1 ON th.user1_id = u1.id JOIN users u2 ON th.user2_id = u2.id WHERE th.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $req['reference_id']);
            mysqli_stmt_execute($stmt);
            $trade_res = mysqli_stmt_get_result($stmt);
            $trade = mysqli_fetch_assoc($trade_res);
            mysqli_stmt_close($stmt);
            
            if ($trade) {
                $tdata['user1_name'] = $trade['user1_name'];
                $tdata['user2_name'] = $trade['user2_name'];
                
                $get_items = function($ids) use ($link) {
                    if(empty($ids)) return [];
                    $res = mysqli_query($link, "SELECT name, image, game, wear_rating, rarity FROM items WHERE id IN ($ids)");
                    $arr = [];
                    while($row = mysqli_fetch_assoc($res)) {
                        $gi = admin_game_info($row['game']);
                        $row['game_logo'] = $gi['logo'];
                        $arr[] = $row;
                    }
                    return $arr;
                };
                
                $tdata['user1_items'] = $get_items($trade['user1_items']);
                $tdata['user2_items'] = $get_items($trade['user2_items']);
            }
        }
        $req['transaction_data'] = $tdata;
    }
    return $requests;
}

function get_deletion_requests($link) {
    $res = mysqli_query($link, "SELECT d.*, u.name as username FROM deletion_requests d JOIN users u ON d.user_id = u.id WHERE d.status = 'pending' ORDER BY d.created_at ASC");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}
?>