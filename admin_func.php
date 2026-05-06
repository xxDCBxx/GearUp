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

    // ── Edit user (username, email, credits, admin flag) ──────────────────
    if ($action === "edit_user") {
        $target_id     = (int)$_POST['target_id'];
        $username      = trim($_POST['username']);
        $email         = trim($_POST['email']);
        $credits       = (float)$_POST['credits'];
        $is_admin_flag = isset($_POST['is_admin']) ? 1 : 0;

        $stmt = mysqli_prepare($link,
            "UPDATE users SET name = ?, email = ?, credits = ?, is_admin = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssdii", $username, $email, $credits, $is_admin_flag, $target_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_admin_success'] = "User updated successfully.";
        } else {
            $_SESSION['flash_admin_error'] = "Failed to update user.";
        }
        mysqli_stmt_close($stmt);
    }

    // ── Soft-delete a user (manual, from Users tab) ───────────────────────
    elseif ($action === "delete_user") {
        $target_id = (int)$_POST['target_id'];
        if ($target_id == $user_id) {
            $_SESSION['flash_admin_error'] = "You cannot delete yourself.";
        } else {
            // Pause their active market listings so items stay in inventory
            mysqli_query($link,
                "UPDATE market_listings SET status = 'cancelled'
                 WHERE user_id = $target_id AND status = 'active'");

            $stmt = mysqli_prepare($link,
                "UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            mysqli_stmt_bind_param($stmt, "i", $target_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_admin_success'] =
                    "User soft-deleted. Their data is fully preserved and can be restored.";
            } else {
                $_SESSION['flash_admin_error'] = "Failed to delete user.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // ── Approve deletion request → soft-delete ────────────────────────────
    elseif ($action === "approve_deletion") {
        $request_id = (int)$_POST['request_id'];
        $target_id  = (int)$_POST['target_id'];

        mysqli_begin_transaction($link);
        try {
            // Pause active listings; preserve everything else
            mysqli_query($link,
                "UPDATE market_listings SET status = 'cancelled'
                 WHERE user_id = $target_id AND status = 'active'");

            $stmt = mysqli_prepare($link,
                "UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            mysqli_stmt_bind_param($stmt, "i", $target_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_query($link,
                "UPDATE deletion_requests SET status = 'approved',
                 reviewed_by = $user_id, reviewed_at = NOW()
                 WHERE id = $request_id");

            mysqli_commit($link);
            $_SESSION['flash_admin_success'] =
                "Account soft-deleted. Data preserved — restorable from Undo Deleted.";
        } catch (Exception $e) {
            mysqli_rollback($link);
            $_SESSION['flash_admin_error'] = "Failed to delete user: " . $e->getMessage();
        }
        $redirect_tab = 'deletions';
    }

    // ── Reject deletion request ───────────────────────────────────────────
    elseif ($action === "reject_deletion") {
        $request_id = (int)$_POST['request_id'];
        mysqli_query($link,
            "UPDATE deletion_requests SET status = 'rejected',
             reviewed_by = $user_id, reviewed_at = NOW()
             WHERE id = $request_id");
        $_SESSION['flash_admin_success'] = "Deletion request rejected. Account kept active.";
        $redirect_tab = 'deletions';
    }

    // ── Approve revert request ────────────────────────────────────────────
    elseif ($action === "approve_revert") {
        $request_id = (int)$_POST['request_id'];
        $type       = $_POST['type'];
        $ref_id     = (int)$_POST['reference_id'];

        mysqli_begin_transaction($link);
        try {
            if ($type === 'market') {
                // Fetch original trade record
                $stmt = mysqli_prepare($link,
                    "SELECT buyer_id, seller_id, item_id, price
                     FROM market_history WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $ref_id);
                mysqli_stmt_execute($stmt);
                $trade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);

                if (!$trade) throw new Exception("Market history record not found.");

                // ONLY check: does the buyer still hold the item?
                $check = mysqli_query($link,
                    "SELECT id FROM user_items
                     WHERE user_id = {$trade['buyer_id']}
                       AND item_id = {$trade['item_id']}
                     LIMIT 1");
                if (mysqli_num_rows($check) == 0) {
                    throw new Exception(
                        "Revert blocked: the buyer no longer has the received item. " .
                        "It may have been traded or sold.");
                }
                $ui_row = mysqli_fetch_assoc($check);

                // Revert: move item back to seller, credit buyer, debit seller
                // (seller may go negative — allowed by design)
                mysqli_query($link,
                    "UPDATE user_items
                     SET user_id = {$trade['seller_id']}
                     WHERE id = {$ui_row['id']}");
                mysqli_query($link,
                    "UPDATE users SET credits = credits + {$trade['price']}
                     WHERE id = {$trade['buyer_id']}");
                mysqli_query($link,
                    "UPDATE users SET credits = credits - {$trade['price']}
                     WHERE id = {$trade['seller_id']}");

            } elseif ($type === 'trade') {
                // Fetch original trade record
                $stmt = mysqli_prepare($link,
                    "SELECT user1_id, user2_id, user1_items, user2_items
                     FROM trade_history WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $ref_id);
                mysqli_stmt_execute($stmt);
                $trade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);

                if (!$trade) throw new Exception("Trade history record not found.");

                $u1_items = array_filter(array_map('intval', explode(',', $trade['user1_items'])));
                $u2_items = array_filter(array_map('intval', explode(',', $trade['user2_items'])));

                // ONLY check: does each recipient still have what they received?
                // user2 received user1's items → check user2 still has them
                foreach ($u1_items as $item_id) {
                    $chk = mysqli_query($link,
                        "SELECT id FROM user_items
                         WHERE user_id = {$trade['user2_id']} AND item_id = $item_id
                         LIMIT 1");
                    if (mysqli_num_rows($chk) == 0) {
                        throw new Exception(
                            "Revert blocked: {$trade['user2_id']} no longer has item #$item_id " .
                            "(originally sent by user1). It may have been traded or sold.");
                    }
                }
                // user1 received user2's items → check user1 still has them
                foreach ($u2_items as $item_id) {
                    $chk = mysqli_query($link,
                        "SELECT id FROM user_items
                         WHERE user_id = {$trade['user1_id']} AND item_id = $item_id
                         LIMIT 1");
                    if (mysqli_num_rows($chk) == 0) {
                        throw new Exception(
                            "Revert blocked: {$trade['user1_id']} no longer has item #$item_id " .
                            "(originally sent by user2). It may have been traded or sold.");
                    }
                }

                // Revert items — swap them back
                foreach ($u1_items as $item_id) {
                    mysqli_query($link,
                        "UPDATE user_items
                         SET user_id = {$trade['user1_id']}
                         WHERE user_id = {$trade['user2_id']} AND item_id = $item_id
                         LIMIT 1");
                }
                foreach ($u2_items as $item_id) {
                    mysqli_query($link,
                        "UPDATE user_items
                         SET user_id = {$trade['user2_id']}
                         WHERE user_id = {$trade['user1_id']} AND item_id = $item_id
                         LIMIT 1");
                }
            }

            mysqli_query($link,
                "UPDATE revert_requests SET status = 'approved',
                 reviewed_by = $user_id, reviewed_at = NOW()
                 WHERE id = $request_id");

            mysqli_commit($link);
            $_SESSION['flash_admin_success'] = "Trade reverted successfully.";

        } catch (Exception $e) {
            mysqli_rollback($link);
            $_SESSION['flash_admin_error'] = "Revert failed: " . $e->getMessage();
        }
        $redirect_tab = 'reverts';
    }

    // ── Reject revert request ─────────────────────────────────────────────
    elseif ($action === "reject_revert") {
        $request_id = (int)$_POST['request_id'];
        mysqli_query($link,
            "UPDATE revert_requests SET status = 'rejected',
             reviewed_by = $user_id, reviewed_at = NOW()
             WHERE id = $request_id");
        $_SESSION['flash_admin_success'] = "Revert request rejected.";
        $redirect_tab = 'reverts';
    }

    // ── Restore a soft-deleted user ───────────────────────────────────────
    elseif ($action === "restore_user") {
        $target_id = (int)$_POST['target_id'];
        $stmt = mysqli_prepare($link,
            "UPDATE users SET deleted_at = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $target_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_admin_success'] = "Account restored successfully.";
        } else {
            $_SESSION['flash_admin_error'] = "Failed to restore account.";
        }
        mysqli_stmt_close($stmt);
        $redirect_tab = 'deleted';
    }

    // ── Update credits only ───────────────────────────────────────────────
    elseif ($action === "update_credits") {
        $target_id   = (int)$_POST['target_id'];
        $new_credits = (float)$_POST['credits'];
        $stmt = mysqli_prepare($link,
            "UPDATE users SET credits = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "di", $new_credits, $target_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_admin_success'] = "Credits updated.";
        } else {
            $_SESSION['flash_admin_error'] = "Failed to update credits.";
        }
        mysqli_stmt_close($stmt);
    }

    header("Location: admin.php?tab=" . $redirect_tab);
    exit;
}

// ── Data fetch helpers ────────────────────────────────────────────────────

function get_all_users($link) {
    $res = mysqli_query($link,
        "SELECT id, name, email, credits, is_admin, picture, deleted_at,
                (SELECT COUNT(*) FROM items WHERE owner_id = users.id) AS item_count
         FROM users
         WHERE deleted_at IS NULL
         ORDER BY is_admin DESC, name ASC");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function get_deleted_users($link) {
    $res = mysqli_query($link,
        "SELECT id, name, email, credits, is_admin, picture, deleted_at,
                (SELECT COUNT(*) FROM items WHERE owner_id = users.id)           AS item_count,
                (SELECT COUNT(*) FROM market_listings WHERE user_id = users.id)  AS listing_count,
                (SELECT COUNT(*) FROM offers WHERE sender_id = users.id
                                               OR receiver_id = users.id)        AS offer_count
         FROM users
         WHERE deleted_at IS NOT NULL
         ORDER BY deleted_at DESC");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function admin_game_info($game) {
    return match($game) {
        'cs2'   => ['name' => 'CS2',             'logo' => 'logos/logo_cs2.png'],
        'dota2' => ['name' => 'Dota 2',          'logo' => 'logos/logo_dota2.png'],
        'rust'  => ['name' => 'Rust',            'logo' => 'logos/logo_rust.webp'],
        'tf2'   => ['name' => 'Team Fortress 2', 'logo' => 'logos/logo_tf2.png'],
        default => ['name' => 'Unknown',         'logo' => '']
    };
}

function get_revert_requests($link) {
    $res = mysqli_query($link,
        "SELECT r.*, u.name AS username
         FROM revert_requests r
         JOIN users u ON r.user_id = u.id
         WHERE r.status = 'pending'
         ORDER BY r.created_at ASC");
    $requests = mysqli_fetch_all($res, MYSQLI_ASSOC);

    foreach ($requests as &$req) {
        $tdata = ['type' => $req['type']];

        if ($req['type'] === 'market') {
            $stmt = mysqli_prepare($link,
                "SELECT mh.*, i.name AS item_name, i.image, i.game, i.wear_rating, i.rarity,
                        b.name AS buyer_name, s.name AS seller_name
                 FROM market_history mh
                 JOIN items i  ON mh.item_id   = i.id
                 JOIN users b  ON mh.buyer_id  = b.id
                 JOIN users s  ON mh.seller_id = s.id
                 WHERE mh.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $req['reference_id']);
            mysqli_stmt_execute($stmt);
            $trade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($trade) {
                $gi = admin_game_info($trade['game']);
                $tdata['buyer_name']  = $trade['buyer_name'];
                $tdata['seller_name'] = $trade['seller_name'];
                $tdata['price']       = $trade['price'];
                $tdata['item']        = [
                    'name'      => $trade['item_name'],
                    'image'     => $trade['image'],
                    'wear'      => $trade['wear_rating'],
                    'rarity'    => $trade['rarity'],
                    'game_logo' => $gi['logo'],
                ];

                // Warn if buyer no longer has the item (revert would fail)
                $chk = mysqli_query($link,
                    "SELECT id FROM user_items
                     WHERE user_id = {$trade['buyer_id']}
                       AND item_id = {$trade['item_id']}
                     LIMIT 1");
                $tdata['buyer_has_item'] = (mysqli_num_rows($chk) > 0);
            }

        } elseif ($req['type'] === 'trade') {
            $stmt = mysqli_prepare($link,
                "SELECT th.*, u1.name AS user1_name, u2.name AS user2_name
                 FROM trade_history th
                 JOIN users u1 ON th.user1_id = u1.id
                 JOIN users u2 ON th.user2_id = u2.id
                 WHERE th.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $req['reference_id']);
            mysqli_stmt_execute($stmt);
            $trade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($trade) {
                $tdata['user1_name'] = $trade['user1_name'];
                $tdata['user2_name'] = $trade['user2_name'];

                $get_items = function ($ids) use ($link) {
                    if (empty($ids)) return [];
                    $res = mysqli_query($link,
                        "SELECT id AS item_id, name, image, game, wear_rating, rarity
                         FROM items WHERE id IN ($ids)");
                    $arr = [];
                    while ($row = mysqli_fetch_assoc($res)) {
                        $gi            = admin_game_info($row['game']);
                        $row['game_logo'] = $gi['logo'];
                        $arr[] = $row;
                    }
                    return $arr;
                };

                $tdata['user1_items'] = $get_items($trade['user1_items']);
                $tdata['user2_items'] = $get_items($trade['user2_items']);

                // Per-item revertibility: check each recipient still has each item
                $u1_ids = array_filter(array_map('intval', explode(',', $trade['user1_items'])));
                $u2_ids = array_filter(array_map('intval', explode(',', $trade['user2_items'])));

                $missing = [];
                foreach ($u1_ids as $iid) {
                    $c = mysqli_query($link,
                        "SELECT id FROM user_items
                         WHERE user_id = {$trade['user2_id']} AND item_id = $iid LIMIT 1");
                    if (mysqli_num_rows($c) == 0) $missing[] = $iid;
                }
                foreach ($u2_ids as $iid) {
                    $c = mysqli_query($link,
                        "SELECT id FROM user_items
                         WHERE user_id = {$trade['user1_id']} AND item_id = $iid LIMIT 1");
                    if (mysqli_num_rows($c) == 0) $missing[] = $iid;
                }
                $tdata['missing_items']  = $missing;
                $tdata['can_revert']     = empty($missing);
            }
        }
        $req['transaction_data'] = $tdata;
    }
    return $requests;
}

function get_deletion_requests($link) {
    $res = mysqli_query($link,
        "SELECT d.*, u.name AS username, u.email, u.picture
         FROM deletion_requests d
         JOIN users u ON d.user_id = u.id
         WHERE d.status = 'pending'
         ORDER BY d.created_at ASC");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}
?>