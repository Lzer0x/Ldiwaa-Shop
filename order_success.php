<?php
session_start();
header("Content-Type: text/html; charset=utf-8");

include 'includes/db_connect.php';
include 'includes/header.php';
require_once 'includes/redeem_service.php';

// ✅ ตรวจว่ามีรหัสคำสั่งซื้อใน URL ไหม
if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger text-center mt-5'>❌ ไม่พบหมายเลขคำสั่งซื้อ</div>";
    include 'includes/footer.php';
    exit;
}

$order_id = intval($_GET['id']);

// ✅ ดึงข้อมูลคำสั่งซื้อ
$stmt = $conn->prepare("
    SELECT o.*, u.username 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.user_id 
    WHERE o.order_id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<div class='alert alert-danger text-center mt-5'>❌ ไม่พบข้อมูลคำสั่งซื้อ</div>";
    include 'includes/footer.php';
    exit;
}

// ✅ ตรวจสิทธิ์เข้าถึง
$current_user_id = $_SESSION['user']['user_id'] ?? null;
$current_role = $_SESSION['user']['role'] ?? 'user';

if ($current_role !== 'admin' && $order['user_id'] && $order['user_id'] != $current_user_id) {
    echo "<div class='alert alert-danger text-center mt-5'>🚫 คุณไม่มีสิทธิ์เข้าถึงคำสั่งซื้อนี้</div>";
    include 'includes/footer.php';
    exit;
}

// ✅ ดึงรายละเอียดสินค้า
$detailStmt = $conn->prepare("
    SELECT d.*, p.name, pp.title 
    FROM order_details d
    JOIN products p ON d.product_id = p.product_id
    JOIN product_prices pp ON d.package_id = pp.id
    WHERE d.order_id = ?
");
$detailStmt->execute([$order_id]);
$details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ ดึงสลิป (ถ้ามี)
$slip = $conn->prepare("SELECT slip_path FROM payments WHERE order_id = ?");
$slip->execute([$order_id]);
$slipPath = $slip->fetchColumn();

// ✅ ตรวจโค้ด Redeem (เหมือนเดิม)
$redeemCodes = [];
if ($order['payment_status'] === 'paid') {
    $res = assignRedeemKeys($conn, $order_id);
    if (!empty($res['success']) && empty($res['shortages']) && ($order['order_status'] ?? '') === 'processing') {
        $conn->prepare("UPDATE orders SET order_status='completed' WHERE order_id=?")->execute([$order_id]);
        $order['order_status'] = 'completed';
    }

    // ดึงโค้ดที่ Redeem แล้ว
    $fetchRedeem = $conn->prepare("
        SELECT r.product_id, r.codes, p.name, pp.title
        FROM order_redeems r
        JOIN products p ON r.product_id = p.product_id
        JOIN order_details d ON d.order_id = r.order_id AND d.product_id = r.product_id
        JOIN product_prices pp ON d.package_id = pp.id
        WHERE r.order_id = ?
    ");
    $fetchRedeem->execute([$order_id]);
    while ($r = $fetchRedeem->fetch(PDO::FETCH_ASSOC)) {
        $keyLabel = $r['name'] . ' (' . $r['title'] . ')';
        $redeemCodes[$keyLabel] = explode(',', $r['codes']);
    }
}
?>

<link rel="stylesheet" href="assets/css/order_success.css">

<div class="success-container">
  <div class="success-card">
    <div class="success-header">
      <h3>✅ คำสั่งซื้อสำเร็จ</h3>
      <p>หมายเลขคำสั่งซื้อ #<?= $order['order_id'] ?></p>
    </div>

    <div class="success-content">
      <div class="order-info">
        <p><strong>ลูกค้า:</strong> <?= htmlspecialchars($order['username'] ?? 'Guest') ?></p>
        <p><strong>ช่องทางชำระเงิน:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
        <p><strong>วันที่สั่งซื้อ:</strong> <?= htmlspecialchars($order['created_at'] ?? '-') ?></p>
        <p><strong>สถานะ:</strong>
          <?php if ($order['payment_status'] === 'paid'): ?>
            <span class="status paid">ชำระเงินแล้ว</span>
          <?php elseif ($order['payment_status'] === 'pending'): ?>
            <span class="status pending">รอตรวจสอบ</span>
          <?php else: ?>
            <span class="status waiting">รอชำระเงิน</span>
          <?php endif; ?>
        </p>
      </div>

      <div class="order-items">
        <table>
          <thead>
            <tr>
              <th>สินค้า</th>
              <th>แพ็กเกจ</th>
              <th>จำนวน</th>
              <th>ราคา</th>
              <th>รวมย่อย</th>
            </tr>
          </thead>
          <tbody>
            <?php $total = 0; foreach ($details as $d): 
              $subtotal = $d['price'] * $d['quantity']; $total += $subtotal; ?>
              <tr>
                <td><?= htmlspecialchars($d['name']) ?></td>
                <td><?= htmlspecialchars($d['title']) ?></td>
                <td><?= $d['quantity'] ?></td>
                <td><?= number_format($d['price'], 2) ?> ฿</td>
                <td><?= number_format($subtotal, 2) ?> ฿</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="order-total">💰 รวมทั้งหมด: <strong><?= number_format($total, 2) ?> ฿</strong></div>

        <?php if (!empty($details)): ?>
          <?php $hasUid = false; foreach ($details as $d) { if (!empty($d['uid'])) { $hasUid = true; break; } } ?>
          <?php if ($hasUid): ?>
            <div class="uid-summary" style="margin-top:12px;">
              <h5>UID ที่ระบุ</h5>
              <ul style="list-style:none;padding:0;margin:0;display:grid;gap:6px;">
                <?php foreach ($details as $d): if (empty($d['uid'])) continue; ?>
                  <li style="background:#1a1f2b;border:1px solid #2e3447;border-radius:10px;padding:8px 10px;">
                    <strong><?= htmlspecialchars($d['name']) ?></strong> (<?= htmlspecialchars($d['title']) ?>)
                    <div>UID: <?= htmlspecialchars($d['uid']) ?></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <?php if ($slipPath): ?>
        <div class="order-slip">
          <h4>🧾 หลักฐานการชำระเงิน</h4>
          <img src="<?= htmlspecialchars($slipPath) ?>" alt="Slip" class="slip-img">
        </div>
      <?php endif; ?>

      <div class="order-status-box">
        <?php if ($order['payment_status'] === 'pending'): ?>
          <div class="alert info">⌛ สลิปของคุณถูกส่งเรียบร้อยแล้ว<br>กรุณารอการตรวจสอบจากแอดมินภายใน 3–5 นาที</div>
        <?php elseif ($order['payment_status'] !== 'paid'): ?>
          <div class="alert warning">
            ⚠️ กรุณาชำระเงินก่อนเพื่อรับโค้ดรีดีม<br>
            <a href="payment_gateway.php?method=<?= urlencode($order['payment_method']) ?>" class="btn-dark">ไปหน้าชำระเงิน</a>
          </div>
        <?php elseif (!empty($redeemCodes)): ?>
          <div class="alert success">
            <h4>🎁 โค้ดรีดีมของคุณ</h4>
            <?php foreach ($redeemCodes as $productLabel => $codes): ?>
              <div class="redeem-block">
                <strong><?= htmlspecialchars($productLabel) ?></strong>
                <ul>
                  <?php foreach ($codes as $code): ?>
                    <li><code><?= htmlspecialchars($code) ?></code></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert info">⌛ ยังไม่มีโค้ดรีดีมในระบบตอนนี้<br>อาจรอตรวจสอบจากแอดมิน</div>
        <?php endif; ?>
      </div>

      <div class="text-center mt-4">
        <a href="index.php" class="btn-dark">กลับหน้าหลัก</a>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
