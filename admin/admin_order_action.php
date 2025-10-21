<?php
session_start();
require_once '../includes/auth_admin.php';
include '../includes/db_connect.php';
include '../includes/csrf.php';

function flash($type, $msg) {
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
  header("Location: orders.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  flash('danger', 'Method not allowed');
}

if (!csrf_verify($_POST['csrf'] ?? '')) {
  flash('danger', 'CSRF token invalid');
}

$order_id = (int)($_POST['order_id'] ?? 0);
$action   = $_POST['action'] ?? '';

if ($order_id <= 0 || !$action) {
  flash('danger', 'ข้อมูลไม่ครบถ้วน');
}

try {
  $conn->beginTransaction();

  // ดึง order + สลิปล่าสุด
  $stmt = $conn->prepare("
    SELECT o.*, p.payment_id, p.status AS slip_status
    FROM orders o
    LEFT JOIN (
      SELECT t1.* FROM payments t1
      INNER JOIN (
        SELECT order_id, MAX(payment_id) AS max_pid
        FROM payments GROUP BY order_id
      ) t2 ON t1.order_id = t2.order_id AND t1.payment_id = t2.max_pid
    ) p ON p.order_id = o.order_id
    WHERE o.order_id = ?
    FOR UPDATE
  ");
  $stmt->execute([$order_id]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    $conn->rollBack();
    flash('danger', 'ไม่พบคำสั่งซื้อ');
  }

  // Action logic
  if ($action === 'verify_slip') {
    // ต้องมีสลิปล่าสุด
    if (empty($order['payment_id'])) {
      $conn->rollBack();
      flash('warning', 'ยังไม่มีสลิปในคำสั่งซื้อนี้');
    }

    // 1) payments.status = verified
    $up1 = $conn->prepare("UPDATE payments SET status='verified' WHERE payment_id=?");
    $up1->execute([$order['payment_id']]);

    // 2) orders.payment_status = paid
    $up2 = $conn->prepare("UPDATE orders SET payment_status='paid' WHERE order_id=?");
    $up2->execute([$order_id]);

    // (ออปชัน) 3) แจกโค้ดอัตโนมัติทันที (ถ้าต้องการ auto)
    // *** ถ้าต้องการ auto-redeem ที่นี่ คัดลอก logic จาก order_success.php มาใส่ตรงนี้ได้ ***

    $conn->commit();
    flash('success', "ยืนยันสลิปและตั้งสถานะชำระเงินเป็น 'paid' แล้ว (#$order_id)");

  } elseif ($action === 'reject_slip') {
    if (empty($order['payment_id'])) {
      $conn->rollBack();
      flash('warning', 'ไม่มีสลิปให้ปฏิเสธ');
    }

    // 1) payments.status = rejected
    $up1 = $conn->prepare("UPDATE payments SET status='rejected' WHERE payment_id=?");
    $up1->execute([$order['payment_id']]);

    // 2) ถ้ายังไม่ paid ให้เป็น unpaid (หรือ failed ตามกรณี)
    if ($order['payment_status'] !== 'paid') {
      $up2 = $conn->prepare("UPDATE orders SET payment_status='unpaid' WHERE order_id=?");
      $up2->execute([$order_id]);
    }

    $conn->commit();
    flash('success', "ปฏิเสธสลิปเรียบร้อย (#$order_id)");

  } elseif ($action === 'mark_completed') {
    // อนุญาตให้ completed ได้ก็ต่อเมื่อจ่ายแล้ว (ตามนโยบายทั่วไป)
    if ($order['payment_status'] !== 'paid') {
      $conn->rollBack();
      flash('warning', 'ต้องชำระเงินก่อนจึงจะปิดงานได้');
    }

    $up = $conn->prepare("UPDATE orders SET order_status='completed' WHERE order_id=?");
    $up->execute([$order_id]);

    $conn->commit();
    flash('success', "ตั้งสถานะคำสั่งซื้อเป็น 'completed' เรียบร้อย (#$order_id)");

  } else {
    $conn->rollBack();
    flash('danger', 'Action ไม่ถูกต้อง');
  }

} catch (Exception $e) {
  if ($conn->inTransaction()) $conn->rollBack();
  flash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
}

