<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';

// ตรวจว่ามี key ที่ส่งมาจากปุ่ม "ลบ" หรือไม่
if (isset($_GET['key'])) {
    $key = $_GET['key'];

    // ถ้ามีตะกร้าใน session และ key นั้นมีอยู่จริง
    if (isset($_SESSION['cart'][$key])) {
        // เก็บข้อมูลสินค้าก่อนลบ (ใช้สำหรับลบใน DB)
        $product_id = $_SESSION['cart'][$key]['product_id'];
        $package_id = $_SESSION['cart'][$key]['package_id'];

        // ลบออกจาก session
        unset($_SESSION['cart'][$key]);

        // ✅ ถ้าผู้ใช้ล็อกอินอยู่ → ลบออกจากฐานข้อมูล carts ด้วย
        if (isset($_SESSION['user']['user_id'])) {
            $user_id = $_SESSION['user']['user_id'];

            $stmt = $conn->prepare("DELETE FROM carts WHERE user_id = ? AND product_id = ? AND package_id = ?");
            $stmt->execute([$user_id, $product_id, $package_id]);
        }

        $_SESSION['flash_message'] = "🗑️ ลบสินค้าจากตะกร้าเรียบร้อยแล้ว!";
    }
}

// ถ้าไม่มีสินค้าเหลือในตะกร้า ให้เคลียร์ session ตะกร้า
if (empty($_SESSION['cart'])) {
    unset($_SESSION['cart']);
}

// กลับไปหน้าตะกร้า
header("Location: cart.php");
exit;
?>
