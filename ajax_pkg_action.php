<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  exit("Unauthorized");
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
  $product_id = intval($_POST['product_id']);
  $title = trim($_POST['title']);
  $price = floatval($_POST['price']);
  $discount = floatval($_POST['discount']);

  if ($title === '' || $price <= 0) exit("กรอกข้อมูลไม่ครบ");

  $stmt = $conn->prepare("INSERT INTO product_prices (product_id, title, price_thb, discount_percent) VALUES (?, ?, ?, ?)");
  $stmt->execute([$product_id, $title, $price, $discount]);
  exit("OK:" . $conn->lastInsertId());
}

if ($action === 'delete') {
  $id = intval($_POST['id']);
  $conn->prepare("DELETE FROM product_prices WHERE id = ?")->execute([$id]);
  exit("OK");
}

exit("Invalid action");
