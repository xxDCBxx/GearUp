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
            $del_stmt = mysqli_prepare($link, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($del_stmt, "i", $target_id);
            mysqli_stmt_execute($del_stmt);
            mysqli_stmt_close($del_stmt);

            $upd_stmt = mysqli_prepare($link, "UPDATE deletion_requests SET status = 'approved' WHERE id = ?");
            mysqli_stmt_bind_param($upd_stmt, "i", $request_id);
            mysqli_stmt_execute($upd_stmt);
            mysqli_stmt_close($upd_stmt);

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
        $rej_stmt = mysqli_prepare($link, "UPDATE deletion_requests SET status = 'rejected' WHERE id = ?");
        mysqli_stmt_bind_param($rej_stmt, "i", $request_id);
        mysqli_stmt_execute($rej_stmt);
        mysqli_stmt_close($rej_stmt);
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
                $chk_stmt = mysqli_prepare($link, "SELECT id FROM user_items WHERE user_id = ? AND item_id = ? LIMIT 1");
                mysqli_stmt_bind_param($chk_stmt, "ii", $trade['buyer_id'], $trade['item_id']);
                mysqli_stmt_execute($chk_stmt);
                $chk_res = mysqli_stmt_get_result($chk_stmt);
                $ui_row = mysqli_fetch_assoc($chk_res);
                mysqli_stmt_close($chk_stmt);
                if (!$ui_row) throw new Exception("Buyer no longer has the item.");
                
                $cred_stmt = mysqli_prepare($link, "SELECT credits FROM users WHERE id = ?");
                mysqli_stmt_bind_param($cred_stmt, "i", $trade['seller_id']);
                mysqli_stmt_execute($cred_stmt);
                $cred_res = mysqli_stmt_get_result($cred_stmt);
                $s_cred = mysqli_fetch_assoc($cred_res)['credits'];
                mysqli_stmt_close($cred_stmt);
                if ($s_cred < $trade['price']) throw new Exception("Seller does not have enough credits to refund.");

                // Revert
                $upd1 = mysqli_prepare($link, "UPDATE users SET credits = credits + ? WHERE id = ?");
                mysqli_stmt_bind_param($upd1, "di", $trade['price'], $trade['buyer_id']);
                mysqli_stmt_execute($upd1);
                mysqli_stmt_close($upd1);

                $upd2 = mysqli_prepare($link, "UPDATE users SET credits = credits - ? WHERE id = ?");
                mysqli_stmt_bind_param($upd2, "di", $trade['price'], $trade['seller_id']);
                mysqli_stmt_execute($upd2);
                mysqli_stmt_close($upd2);

                $upd3 = mysqli_prepare($link, "UPDATE user_items SET user_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd3, "ii", $trade['seller_id'], $ui_row['id']);
                mysqli_stmt_execute($upd3);
                mysqli_stmt_close($upd3);
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
                $chk_stmt = mysqli_prepare($link, "SELECT id FROM user_items WHERE user_id = ? AND item_id = ? LIMIT 1");
                foreach($u1_items as $item_id) {
                    mysqli_stmt_bind_param($chk_stmt, "ii", $trade['user2_id'], $item_id);
                    mysqli_stmt_execute($chk_stmt);
                    $chk_res = mysqli_stmt_get_result($chk_stmt);
                    if(!mysqli_fetch_assoc($chk_res)) throw new Exception("User 2 no longer has the required items.");
                }
                // Check User 1 has User 2's items
                foreach($u2_items as $item_id) {
                    mysqli_stmt_bind_param($chk_stmt, "ii", $trade['user1_id'], $item_id);
                    mysqli_stmt_execute($chk_stmt);
                    $chk_res = mysqli_stmt_get_result($chk_stmt);
                    if(!mysqli_fetch_assoc($chk_res)) throw new Exception("User 1 no longer has the required items.");
                }
                mysqli_stmt_close($chk_stmt);

                // Revert Items
                $move_stmt = mysqli_prepare($link, "UPDATE user_items SET user_id = ? WHERE user_id = ? AND item_id = ? LIMIT 1");
                foreach($u1_items as $item_id) {
                    mysqli_stmt_bind_param($move_stmt, "iii", $trade['user1_id'], $trade['user2_id'], $item_id);
                    mysqli_stmt_execute($move_stmt);
                }
                foreach($u2_items as $item_id) {
                    mysqli_stmt_bind_param($move_stmt, "iii", $trade['user2_id'], $trade['user1_id'], $item_id);
                    mysqli_stmt_execute($move_stmt);
                }
                mysqli_stmt_close($move_stmt);
            }

            $upd_req = mysqli_prepare($link, "UPDATE revert_requests SET status = 'approved' WHERE id = ?");
            mysqli_stmt_bind_param($upd_req, "i", $request_id);
            mysqli_stmt_execute($upd_req);
            mysqli_stmt_close($upd_req);

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
        $rej_stmt = mysqli_prepare($link, "UPDATE revert_requests SET status = 'rejected' WHERE id = ?");
        mysqli_stmt_bind_param($rej_stmt, "i", $request_id);
        mysqli_stmt_execute($rej_stmt);
        mysqli_stmt_close($rej_stmt);
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
                
                $get_items = function($ids_str) use ($link) {
                    if(empty($ids_str)) return [];
                    $id_arr = array_filter(array_map('intval', explode(',', $ids_str)));
                    if(empty($id_arr)) return [];
                    $placeholders = implode(',', array_fill(0, count($id_arr), '?'));
                    $types = str_repeat('i', count($id_arr));
                    $stmt = mysqli_prepare($link, "SELECT name, image, game, wear_rating, rarity FROM items WHERE id IN ($placeholders)");
                    mysqli_stmt_bind_param($stmt, $types, ...$id_arr);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $arr = [];
                    while($row = mysqli_fetch_assoc($res)) {
                        $gi = admin_game_info($row['game']);
                        $row['game_logo'] = $gi['logo'];
                        $arr[] = $row;
                    }
                    mysqli_stmt_close($stmt);
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