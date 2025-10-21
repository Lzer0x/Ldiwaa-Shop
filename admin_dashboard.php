<?php
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';
// Summary stats
try {
  $totalSalesStmt = $conn->prepare("SELECT COALESCE(SUM(total_price),0) AS total_sales FROM orders WHERE payment_status = 'paid'");
  $totalSalesStmt->execute();
  $totalSales = $totalSalesStmt->fetchColumn();

  $totalOrdersStmt = $conn->prepare("SELECT COUNT(*) FROM orders");
  $totalOrdersStmt->execute();
  $totalOrders = $totalOrdersStmt->fetchColumn();

  $statusStmt = $conn->query("SELECT order_status, COUNT(*) AS cnt FROM orders GROUP BY order_status");
  $statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

  // recent orders
  $recentStmt = $conn->query("SELECT o.order_id, o.total_price, o.payment_status, o.order_status, o.created_at, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.user_id ORDER BY o.created_at DESC LIMIT 10");
  $recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
  $totalSales = 0; $totalOrders = 0; $statusCounts = []; $recentOrders = [];
}
?>

<link rel="stylesheet" href="/assets/css/admin_orders.css">

<div class="container mt-5 mb-5" style="max-width:1100px;">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4 class="mb-0">📊 แดชบอร์ดผู้ดูแลระบบ</h4>
      <div>
        <a href="admin_orders.php" class="btn btn-outline-light btn-sm me-2">📦 จัดการคำสั่งซื้อ</a>
        <a href="index.php" class="btn btn-outline-light btn-sm">🏠 กลับหน้าหลัก</a>
      </div>
    </div>

    <div class="card-body">
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="stat-card p-3 border rounded text-center">
            <h6>ยอดขายทั้งหมด (ชำระแล้ว)</h6>
            <div class="h3">฿<?= number_format($totalSales,2) ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-card p-3 border rounded text-center">
            <h6>จำนวนคำสั่งซื้อทั้งหมด</h6>
            <div class="h3"><?= number_format($totalOrders) ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-card p-3 border rounded text-center">
            <h6>สถานะคำสั่งซื้อ</h6>
            <?php foreach (['completed','processing','cancelled'] as $s): ?>
              <div><?= htmlspecialchars($s) ?>: <?= number_format($statusCounts[$s] ?? 0) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <h5>คำสั่งซื้อล่าสุด</h5>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle text-center">
          <thead class="table-dark"><tr><th>#</th><th>ลูกค้า</th><th>ยอดรวม</th><th>สถานะชำระเงิน</th><th>สถานะคำสั่งซื้อ</th><th>วันที่</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($recentOrders as $o): ?>
              <tr>
                <td>#<?= $o['order_id'] ?></td>
                <td><?= htmlspecialchars($o['username'] ?? 'Guest') ?></td>
                <td>฿<?= number_format($o['total_price'],2) ?></td>
                <td><?= htmlspecialchars($o['payment_status']) ?></td>
                <td><?= htmlspecialchars($o['order_status']) ?></td>
                <td><?= htmlspecialchars($o['created_at']) ?></td>
                <td><a href="/gamekeyplus/order_detail.php?order_id=<?= $o['order_id'] ?>" class="btn btn-sm btn-outline-primary">🔍</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
