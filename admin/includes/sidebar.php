<?php
// ดึงค่าหน้าปัจจุบันจาก URL
$currentPage = $_GET['page'] ?? 'dashboard';
?>
<nav class="sidebar">
  <a href="admin.php?page=dashboard" class="sidebar-brand">Ldiwaa Shop</a>
  
  <div class="list-group list-group-flush sidebar-nav">
    <a class="list-group-item list-group-item-action <?= ($currentPage == 'dashboard') ? 'active' : '' ?>" 
       href="admin.php?page=dashboard">แดชบอร์ด</a>
       
    <a class="list-group-item list-group-item-action <?= ($currentPage == 'orders') ? 'active' : '' ?>" 
       href="admin.php?page=orders">คำสั่งซื้อ</a>
       
    <a class="list-group-item list-group-item-action <?= ($currentPage == 'products') ? 'active' : '' ?>" 
       href="admin.php?page=products">สินค้าทั้งหมด</a>
       
    <a class="list-group-item list-group-item-action <?= ($currentPage == 'add_product') ? 'active' : '' ?>" 
       href="admin.php?page=add_product">เพิ่มสินค้า</a>

    <a class="list-group-item list-group-item-action <?= ($currentPage == 'redeem_keys') ? 'active' : '' ?>" 
       href="admin.php?page=redeem_keys">จัดการคีย์</a>

    <a class="list-group-item list-group-item-action <?= ($currentPage == 'users') ? 'active' : '' ?>" 
       href="admin.php?page=users">จัดการผู้ใช้</a>
 
   <hr style="border-color: #555;">
   <a class="list-group-item list-group-item-action" href="../index.php">กลับหน้าหลัก</a>
  </div>
</nav>