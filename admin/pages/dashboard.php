<?php
// $conn ถูกส่งมาจาก admin.php

// --- Data Fetching ---
try {
  // 1. Total Sales (Paid)
  $totalSalesStmt = $conn->prepare("SELECT COALESCE(SUM(total_price),0) AS total_sales FROM orders WHERE payment_status = 'paid'");
  $totalSalesStmt->execute();
  $totalSales = $totalSalesStmt->fetchColumn();

  // 2. Total Orders
  $totalOrdersStmt = $conn->prepare("SELECT COUNT(*) FROM orders");
  $totalOrdersStmt->execute();
  $totalOrders = $totalOrdersStmt->fetchColumn();

  // 3. Order Status Counts
  $statusStmt = $conn->query("SELECT order_status, COUNT(*) AS cnt FROM orders GROUP BY order_status");
  $statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    // ตั้งค่า default ให้ครบทุก status
    $orderStatus = [
        'processing' => $statusCounts['processing'] ?? 0,
        'completed' => $statusCounts['completed'] ?? 0,
        'cancelled' => $statusCounts['cancelled'] ?? 0,
    ];


  // 4. Recent Orders
  $recentStmt = $conn->query("SELECT o.order_id, o.total_price, o.payment_status, o.order_status, o.created_at, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.user_id ORDER BY o.created_at DESC LIMIT 5");
  $recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

  // 5. [ฟีเจอร์ใหม่] Monthly Sales for Chart
  $chartStmt = $conn->query("
      SELECT 
          DATE_FORMAT(created_at, '%Y-%m') AS month, 
          SUM(total_price) AS monthly_sales 
      FROM orders 
      WHERE payment_status = 'paid' 
      GROUP BY month 
      ORDER BY month ASC 
      LIMIT 12
  ");
  $chartDataRaw = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

  $chartLabels = [];
  $chartData = [];
  foreach ($chartDataRaw as $row) {
      $chartLabels[] = $row['month'];
      $chartData[] = $row['monthly_sales'];
  }
  // แปลงเป็น JSON เพื่อให้ JavaScript อ่านได้
  $chartLabelsJSON = json_encode($chartLabels);
  $chartDataJSON = json_encode($chartData);


} catch (Exception $e) {
  // Error state
  $totalSales = 0; $totalOrders = 0; $recentOrders = [];
  $orderStatus = ['processing' => 0, 'completed' => 0, 'cancelled' => 0];
  $chartLabelsJSON = '[]'; $chartDataJSON = '[]';
}
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">


<div class="main-header">
  <h1>แดชบอร์ดแอดมิน</h1>
  <a href="admin.php?page=orders" class="btn btn-primary">ดูคำสั่งซื้อทั้งหมด</a>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-3 col-md-6">
    <div class="stat-card border-left-primary">
      <div class="stat-title text-primary">ยอดขายรวม (ชำระแล้ว)</div>
      <div class="stat-value">฿<?= number_format($totalSales, 2) ?></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="stat-card border-left-success">
      <div class="stat-title text-success">คำสั่งซื้อทั้งหมด</div>
      <div class="stat-value"><?= number_format($totalOrders) ?></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="stat-card border-left-warning">
      <div class="stat-title text-warning">กำลังดำเนินการ</div>
      <div class="stat-value"><?= number_format($orderStatus['processing']) ?></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="stat-card border-left-success">
      <div class="stat-title text-success">สำเร็จแล้ว</div>
      <div class="stat-value"><?= number_format($orderStatus['completed']) ?></div>
    </div>
  </div>
</div>

<div class="row">
  
  <div class="col-lg-12">
    <div class="content-card">
      <div class="content-card-header">
        ภาพรวมยอดขายรายเดือน (12 เดือนล่าสุด)
      </div>
      <div class="content-card-body">
        <canvas id="salesChart"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-12">
    <div class="content-card">
      <div class="content-card-header">
        รายการล่าสุด (5 รายการ)
      </div>
      <div class="content-card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>ผู้สั่งซื้อ</th>
                <th>ยอดรวม</th>
                <th>ชำระ</th>
                <th>สถานะ</th>
                <th>วันที่</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentOrders)): ?>
                <tr><td colspan="7" class="text-center">ไม่มีข้อมูล</td></tr>
              <?php else: ?>
                <?php foreach ($recentOrders as $o): ?>
                  <tr>
                    <td>#<?= $o['order_id'] ?></td>
                    <td><?= htmlspecialchars($o['username'] ?? 'Guest') ?></td>
                    <td>฿<?= number_format($o['total_price'], 2) ?></td>
                    <td>
                        <span class="badge bg-<?= $o['payment_status'] == 'paid' ? 'success' : 'warning' ?>">
                            <?= htmlspecialchars($o['payment_status']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-secondary">
                            <?= htmlspecialchars($o['order_status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($o['created_at']))) ?></td>
                    <td><a href="../order_detail.php?order_id=<?= $o['order_id'] ?>" class="btn btn-sm btn-outline-primary">ดู</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const ctx = document.getElementById('salesChart').getContext('2d');
  
  // รับข้อมูลจาก PHP
  const chartLabels = <?= $chartLabelsJSON ?>;
  const chartData = <?= $chartDataJSON ?>;

  const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartLabels,
      datasets: [{
        label: 'ยอดขาย (บาท)',
        data: chartData,
        backgroundColor: 'rgba(78, 115, 223, 0.05)',
        borderColor: 'rgba(78, 115, 223, 1)',
        borderWidth: 2,
        tension: 0.3,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value, index, values) {
              return '฿' + new Intl.NumberFormat().format(value);
            }
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                label += ': ';
              }
              if (context.parsed.y !== null) {
                label += '฿' + new Intl.NumberFormat().format(context.parsed.y);
              }
              return label;
            }
          }
        }
      }
    }
  });
});
</script>