<?php
require_once "connections.php";
start_safe_session();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION["id"];

$update_success = "";
$update_error = "";

if (isset($_SESSION['flash_profile_success'])) {
    $update_success = $_SESSION['flash_profile_success'];
    unset($_SESSION['flash_profile_success']);
}
if (isset($_SESSION['flash_profile_error'])) {
    $update_error = $_SESSION['flash_profile_error'];
    unset($_SESSION['flash_profile_error']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "update_profile") {
        $new_username = trim($_POST["username"] ?? "");
        $new_email    = trim($_POST["email"] ?? "");
        $new_password = $_POST["password"] ?? "";
        $confirm_pwd  = $_POST["confirm_password"] ?? "";
        
        $has_error = false;

        $stmt = mysqli_prepare($link, "SELECT id FROM users WHERE name = ? AND id != ?");
        mysqli_stmt_bind_param($stmt, "si", $new_username, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $_SESSION['flash_profile_error'] = "Username is already taken.";
            $has_error = true;
        }
        mysqli_stmt_close($stmt);

        if (!$has_error) {
            $stmt = mysqli_prepare($link, "SELECT id FROM users WHERE email = ? AND id != ?");
            mysqli_stmt_bind_param($stmt, "si", $new_email, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $_SESSION['flash_profile_error'] = "Email is already in use.";
                $has_error = true;
            }
            mysqli_stmt_close($stmt);
        }

        if (!$has_error && !empty($new_password)) {
            if (strlen($new_password) < 6) {
                $_SESSION['flash_profile_error'] = "Password must have at least 6 characters.";
                $has_error = true;
            } elseif ($new_password !== $confirm_pwd) {
                $_SESSION['flash_profile_error'] = "Passwords do not match.";
                $has_error = true;
            }
        }

        if (!$has_error) {
            $picture_sql = "";
            $picture_param = null;

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['profile_picture']['tmp_name'];
                $name = basename($_FILES['profile_picture']['name']);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($ext, $allowed)) {
                    $dir = "uploads/";
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
                    $dest = $dir . $new_name;
                    if (move_uploaded_file($tmp_name, $dest)) {
                        $picture_sql = ", picture = ?";
                        $picture_param = $dest;
                    } else {
                        $_SESSION['flash_profile_error'] = "Failed to upload profile picture.";
                        header("Location: profile.php");
                        exit;
                    }
                } else {
                    $_SESSION['flash_profile_error'] = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
                    header("Location: profile.php");
                    exit;
                }
            }

            if (!empty($new_password)) {
                $hashed_pwd = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name = ?, email = ?, password = ?$picture_sql WHERE id = ?";
                $stmt = mysqli_prepare($link, $sql);
                if ($picture_param) {
                    mysqli_stmt_bind_param($stmt, "ssssi", $new_username, $new_email, $hashed_pwd, $picture_param, $user_id);
                } else {
                    mysqli_stmt_bind_param($stmt, "sssi", $new_username, $new_email, $hashed_pwd, $user_id);
                }
            } else {
                $sql = "UPDATE users SET name = ?, email = ?$picture_sql WHERE id = ?";
                $stmt = mysqli_prepare($link, $sql);
                if ($picture_param) {
                    mysqli_stmt_bind_param($stmt, "sssi", $new_username, $new_email, $picture_param, $user_id);
                } else {
                    mysqli_stmt_bind_param($stmt, "ssi", $new_username, $new_email, $user_id);
                }
            }

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION["username"] = $new_username;
                $_SESSION['flash_profile_success'] = "Profile updated successfully.";
            } else {
                $_SESSION['flash_profile_error'] = "Something went wrong. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }        
        header("Location: profile.php");
        exit;
    }

    if ($action === "cancel_listing" && !empty($_POST["listing_id"])) {
        $listing_id = (int)$_POST["listing_id"];

        $sql = "SELECT item_id FROM market_listings WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $listing_id, $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $listing = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($listing) {
            $item_id = $listing["item_id"];
            
            $del_stmt = mysqli_prepare($link, "DELETE FROM market_listings WHERE id = ?");
            mysqli_stmt_bind_param($del_stmt, "i", $listing_id);
            mysqli_stmt_execute($del_stmt);
            mysqli_stmt_close($del_stmt);


            $ins_stmt = mysqli_prepare($link, "INSERT INTO user_items (user_id, item_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($ins_stmt, "ii", $user_id, $item_id);
            mysqli_stmt_execute($ins_stmt);
            mysqli_stmt_close($ins_stmt);

            $_SESSION['flash_profile_success'] = "Listing cancelled. Item returned to inventory.";
        }
        header("Location: profile.php");
        exit;
    }

    if ($action === "request_deletion" && !empty($_POST["reason"])) {
        $reason = trim($_POST["reason"]);
        
        $check_stmt = mysqli_prepare($link, "SELECT id FROM deletion_requests WHERE user_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $_SESSION['flash_profile_error'] = "You already have a pending account deletion request.";
        } else {
            $stmt = mysqli_prepare($link, "INSERT INTO deletion_requests (user_id, reason) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "is", $user_id, $reason);
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_profile_success'] = "Account deletion request submitted to admins.";
            } else {
                $_SESSION['flash_profile_error'] = "Failed to submit deletion request.";
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check_stmt);
        
        header("Location: profile.php");
        exit;
    }
}

function get_user_profile($link, $user_id) {
    $stmt = mysqli_prepare($link, "SELECT name, email, picture FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $profile;
}

function get_active_listings($link, $user_id) {
    $sql = "SELECT ml.id AS listing_id, ml.price, 
                   i.id AS item_id, i.name, i.image, i.wear_rating, i.rarity, i.float_value, i.game
            FROM market_listings ml
            JOIN items i ON ml.item_id = i.id
            WHERE ml.user_id = ?
            ORDER BY ml.created_at DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    $listings = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $listings[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $listings;
}

function profile_rarity_class($rarity) {
    $r = strtolower($rarity);
    if (strpos($r, 'covert') !== false) return 'color: #e05050;';
    if (strpos($r, 'classified') !== false) return 'color: #d32ce6;';
    if (strpos($r, 'restricted') !== false) return 'color: #8847ff;';
    if (strpos($r, 'milspec') !== false) return 'color: #4b69ff;';
    return 'color: var(--accent);';
}

function profile_wear_class($wear) {
    $w = strtolower($wear);
    if (strpos($w, 'factory new') !== false) return 'color: #4caf76;';
    if (strpos($w, 'minimal wear') !== false) return 'color: #8bc34a;';
    if (strpos($w, 'field-tested') !== false) return 'color: #ffeb3b;';
    if (strpos($w, 'well-worn') !== false) return 'color: #ff9800;';
    if (strpos($w, 'battle-scarred') !== false) return 'color: #f44336;';
    return 'color: var(--text-dim);';
}
?>
