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

    if ($action === "remove_item" && !empty($_POST["user_item_id"])) {
        $uid = (int)$_POST["user_item_id"];
        $stmt = mysqli_prepare($link, "DELETE FROM user_items WHERE id = ? AND user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $uid, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header("Location: inventory.php");
        exit;
    }

    if ($action === "list_item" && !empty($_POST["user_item_id"]) && isset($_POST["price"])) {
        $uid   = (int)$_POST["user_item_id"];
        $price = (float)$_POST["price"];

        if ($price > 0) {
            $stmt = mysqli_prepare($link, "SELECT item_id FROM user_items WHERE id = ? AND user_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ii", $uid, $user_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);

                if ($row) {
                    $item_id = $row["item_id"];
                    $ins = mysqli_prepare($link, "INSERT INTO market_listings (user_id, item_id, price) VALUES (?, ?, ?)");
                    if ($ins) {
                        mysqli_stmt_bind_param($ins, "iid", $user_id, $item_id, $price);
                        mysqli_stmt_execute($ins);
                        mysqli_stmt_close($ins);
                    }
                    $del = mysqli_prepare($link, "DELETE FROM user_items WHERE id = ? AND user_id = ?");
                    if ($del) {
                        mysqli_stmt_bind_param($del, "ii", $uid, $user_id);
                        mysqli_stmt_execute($del);
                        mysqli_stmt_close($del);
                    }
                }
            }
            header("Location: inventory.php");
            exit;
        }
    }
}

function get_user_inventory(
    $link,
    int $user_id,
    int $page = 1,
    int $per_page = 8,
    string $game_filter = "",
    string $search = "",
    string $sort = "newest",
    $min_price = null,
    $max_price = null
): array {
    $offset = ($page - 1) * $per_page;

    $where = ["ui.user_id = ?"];
    $params = ["i"];
    $values = [$user_id];

    if ($game_filter !== "") {
        $where[] = "i.game = ?";
        $params[] = "s";
        $values[] = $game_filter;
    }

    if ($search !== "") {
        $where[] = "i.name LIKE ?";
        $params[] = "s";
        $values[] = "%" . $search . "%";
    }

    if ($min_price !== null && $min_price !== "") {
        $where[] = "(SELECT price FROM market_listings WHERE item_id = i.id ORDER BY created_at DESC LIMIT 1) >= ?";
        $params[] = "d";
        $values[] = (float)$min_price;
    }

    if ($max_price !== null && $max_price !== "") {
        $where[] = "(SELECT price FROM market_listings WHERE item_id = i.id ORDER BY created_at DESC LIMIT 1) <= ?";
        $params[] = "d";
        $values[] = (float)$max_price;
    }

    $where_sql = "WHERE " . implode(" AND ", $where);

    $order = match($sort) {
        "price_asc"  => "ml_price ASC",
        "price_desc" => "ml_price DESC",
        "name_asc"   => "i.name ASC",
        "name_desc"  => "i.name DESC",
        default      => "ui.id DESC",
    };

    $sql = "SELECT ui.id AS user_item_id, i.id AS item_id, i.name, i.image,
                   i.wear_rating, i.rarity, i.float_value, i.game,
                   (SELECT ml.price FROM market_listings ml WHERE ml.item_id = i.id ORDER BY ml.created_at DESC LIMIT 1) AS ml_price
            FROM user_items ui
            JOIN items i ON ui.item_id = i.id
            $where_sql
            ORDER BY $order
            LIMIT ? OFFSET ?";

    $type_str = implode("", $params) . "ii";
    $values[] = $per_page;
    $values[] = $offset;

    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) return ["items" => [], "total" => 0];

    mysqli_stmt_bind_param($stmt, $type_str, ...$values);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);

    $count_sql = "SELECT COUNT(*) FROM user_items ui JOIN items i ON ui.item_id = i.id $where_sql";
    $count_values = array_slice($values, 0, -2);
    $count_type   = implode("", $params);
    $cstmt = mysqli_prepare($link, $count_sql);
    if ($cstmt) {
        if (!empty($count_values)) {
            mysqli_stmt_bind_param($cstmt, $count_type, ...$count_values);
        }
        mysqli_stmt_execute($cstmt);
        mysqli_stmt_bind_result($cstmt, $total);
        mysqli_stmt_fetch($cstmt);
        mysqli_stmt_close($cstmt);
    } else {
        $total = count($items);
    }

    return ["items" => $items, "total" => (int)$total];
}

function count_user_listings($link, int $user_id): int {
    $stmt = mysqli_prepare($link, "SELECT COUNT(*) FROM market_listings WHERE user_id = ?");
    if (!$stmt) return 0;
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return (int)$count;
}

function inv_game_info(string $game): array {
    return match(strtolower($game)) {
        'cs2'   => ['name' => 'Counter-Strike 2', 'logo' => 'logos/logo_cs2.png'],
        'dota2' => ['name' => 'Dota 2',           'logo' => 'logos/logo_dota2.png'],
        'rust'  => ['name' => 'Rust',             'logo' => 'logos/logo_rust.webp'],
        'tf2'   => ['name' => 'Team Fortress 2',  'logo' => 'logos/logo_tf2.png'],
        default => ['name' => 'Unknown',           'logo' => ''],
    };
}

function rarity_class(string $rarity): string {
    return match(strtolower($rarity)) {
        'covert', 'contraband' => 'cov',
        'factory new'          => 'fn',
        default                => '',
    };
}
?>
