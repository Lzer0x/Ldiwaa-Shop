<?php
session_start();
if (!isset($_SESSION['cart']) || empty($_GET['key']) || empty($_GET['action'])) {
    header("Location: cart.php");
    exit;
}

$key = $_GET['key'];
$action = $_GET['action'];

if (!isset($_SESSION['cart'][$key])) {
    header("Location: cart.php");
    exit;
}

// ✅ เพิ่มหรือลดจำนวน
if ($action === 'increase') {
    $_SESSION['cart'][$key]['quantity']++;
} elseif ($action === 'decrease') {
    if ($_SESSION['cart'][$key]['quantity'] > 1) {
        $_SESSION['cart'][$key]['quantity']--;
    } else {
        unset($_SESSION['cart'][$key]); // ถ้าเหลือ 0 ให้ลบออกเลย
    }
}

header("Location: cart.php");
exit;
?>
