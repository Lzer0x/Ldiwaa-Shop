<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/csrf.php';

// Verify CSRF for POST actions
if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        echo "<div class='alert alert-danger text-center'>❌ CSRF token ไม่ถูกต้อง</div>";
        exit;
    }
}
// ตรวจสอบว่ามีข้อมูลส่งมาจริงไหม
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['product_id'], $_POST['package_id'], $_POST['action'])) {
    header("Location: products.php");
    exit;
}

$product_id = intval($_POST['product_id']);
$package_id = intval($_POST['package_id']);
$action = $_POST['action'];

// ดึงข้อมูลสินค้าและแพ็กเกจจากฐานข้อมูล
$stmt = $conn->prepare("SELECT p.product_id, p.name, p.image_url, pp.id AS package_id, pp.title, pp.price_thb 
                        FROM products p
                        INNER JOIN product_prices pp ON p.product_id = pp.product_id
                        WHERE p.product_id = ? AND pp.id = ?");
$stmt->execute([$product_id, $package_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo "<div class='alert alert-danger text-center'>❌ ไม่พบสินค้า</div>";
    exit;
}

// ✅ สร้างตะกร้าถ้ายังไม่มี
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ✅ ตรวจสอบ user ที่ล็อกอินอยู่
$user_id = $_SESSION['user']['user_id'] ?? null;

// ฟังก์ชันสำหรับบันทึกลงฐานข้อมูล
function saveCartToDB($conn, $user_id, $product_id, $package_id, $quantity) {
    // ตรวจว่ามีอยู่แล้วไหม
    $stmt = $conn->prepare("SELECT cart_id FROM carts WHERE user_id=? AND product_id=? AND package_id=?");
    $stmt->execute([$user_id, $product_id, $package_id]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $upd = $conn->prepare("UPDATE carts SET quantity = quantity + ? WHERE cart_id=?");
        $upd->execute([$quantity, $exists]);
    } else {
        $ins = $conn->prepare("INSERT INTO carts (user_id, product_id, package_id, quantity) VALUES (?, ?, ?, ?)");
        $ins->execute([$user_id, $product_id, $package_id, $quantity]);
    }
}

// ✅ ถ้าเป็นการ “เพิ่มลงตะกร้า”
if ($action === "add") {

    $key = $product_id . "_" . $package_id; // คีย์เฉพาะของสินค้าแต่ละแพ็กเกจ

    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += 1;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id' => $item['product_id'],
            'package_id' => $item['package_id'],
            'name' => $item['name'],
            'title' => $item['title'],
            'price' => $item['price_thb'],
            'image_url' => $item['image_url'],
            'quantity' => 1
        ];
    }

    // ✅ เพิ่มในฐานข้อมูลถ้ามีล็อกอิน
    if ($user_id) {
        saveCartToDB($conn, $user_id, $product_id, $package_id, 1);
    }

    $_SESSION['flash_message'] = "เพิ่ม “{$item['name']} ({$item['title']})” ลงตะกร้าเรียบร้อยแล้ว!";
    header("Location: cart.php");
    exit;
}

// ✅ ถ้าเป็นการ “ซื้อเลย”
elseif ($action === "buy") {
    $_SESSION['cart'] = []; // เคลียร์ตะกร้าเดิม
    $_SESSION['cart'][$product_id . "_" . $package_id] = [
        'product_id' => $item['product_id'],
        'package_id' => $item['package_id'],
        'name' => $item['name'],
        'title' => $item['title'],
        'price' => $item['price_thb'],
        'image_url' => $item['image_url'],
        'quantity' => 1
    ];

    // ✅ บันทึกใน DB ด้วย(ในกรณีซื้อทันที)
    if ($user_id) {
        // ล้างของเก่าก่อน
        $conn->prepare("DELETE FROM carts WHERE user_id = ?")->execute([$user_id]);
        saveCartToDB($conn, $user_id, $product_id, $package_id, 1);
    }

    header("Location: checkout.php");
    exit;
} else {
    header("Location: products.php");
    exit;
}
?>
