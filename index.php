<?php
session_start();
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ ดึงข้อเสนอพิเศษ
$specials = [];
try {
  $sql = "SELECT p.product_id, p.name, p.image_url, p.region, 
                 MIN(pp.price_thb) AS min_price, MAX(pp.discount_percent) AS max_discount
          FROM products p
          LEFT JOIN product_prices pp ON p.product_id = pp.product_id
          WHERE p.status = 'active'
          GROUP BY p.product_id
          ORDER BY max_discount DESC, p.created_at DESC
          LIMIT 10";
  $specials = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  echo '<div class="container-wide">DB Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// ✅ ดึงหมวดหมู่และสินค้าในแต่ละหมวด
$cats = $conn->query("SELECT * FROM categories ORDER BY sort_order ASC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
$catItems = [];
foreach ($cats as $c) {
  $stmt = $conn->prepare("SELECT p.product_id, p.name, p.image_url, p.region
                          FROM products p
                          WHERE p.category_id = ? AND p.status='active'
                          ORDER BY p.created_at DESC
                          LIMIT 9");
  $stmt->execute([$c['category_id']]);
  $catItems[$c['category_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ ดึงข่าว & โปรโมชัน
$news = $conn->query("SELECT title, image_url, content FROM promotions ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- 🟢 ส่วน Hero Banner -->
<section class="hero">
  <div id="heroCarousel" class="carousel slide carousel-fade container-wide" data-bs-ride="carousel" data-bs-interval="5000">
    <div class="carousel-inner carousel-wrapper">
      <div class="carousel-item active">
        <img src="images/banner1.png" alt="banner1">
      </div>
      <div class="carousel-item">
        <img src="images/banner2.png" alt="banner2">
      </div>
      <div class="carousel-item">
        <img src="images/banner3.png" alt="banner3">
      </div>
      <div class="carousel-dots">
        <span class="dot active"></span><span class="dot"></span><span class="dot"></span>
      </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>
</section>

<!-- 🟢 ส่วนข้อเสนอพิเศษ -->
<section class="section">
  <h3>ข้อเสนอพิเศษ</h3>
  <p class="muted">อย่าพลาดโอกาสที่จะได้รับข้อเสนอพิเศษตลอดการ! ค้นหาดีลที่คุณชื่นชอบวันนี้!</p>

  <div class="specials">
    <?php foreach ($specials as $s): 
      $img = $s['image_url'] ?: 'images/sample_product.jpg';
      $disc = is_null($s['max_discount']) ? 0 : (float)$s['max_discount'];
    ?>
    <a href="product_detail.php?id=<?= $s['product_id'] ?>" class="card-offer-link">
      <div class="card-offer">
        <div class="thumb">
          <img class="logo" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($s['name']) ?>">
          <div>
            <div class="title"><?= htmlspecialchars($s['name']) ?></div>
            <div class="region sub" style="font-size:.8rem;opacity:.8">
              <?= htmlspecialchars($s['region'] ?: 'Global') ?>
            </div>
          </div>
        </div>
        <div class="meta">
          <span class="pill">โปรโมชั่น</span>
          <span class="discount"><?= $disc ? ('-' . number_format($disc,1) . '%') : '' ?></span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- 🟢 ส่วนหมวดหมู่ -->
<section class="section">
  <div class="cat-grid">
    <?php foreach ($cats as $c): ?>
      <div class="cat-box">
        <div class="cat-head">
          <h4><?= htmlspecialchars($c['name_th']) ?></h4>
          <a href="products.php?cat=<?= $c['category_id'] ?>" class="link-more">ดูเพิ่มเติม</a>
        </div>
        <div class="cat-items">
          <?php foreach (($catItems[$c['category_id']] ?? []) as $it): 
            $img = $it['image_url'] ?: 'images/sample_card.jpg'; ?>
            <a href="product_detail.php?id=<?= $it['product_id'] ?>" class="cat-item-link">
              <div class="cat-item">
                <img class="icon" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($it['name']) ?>">
                <div>
                  <div class="name"><?= htmlspecialchars($it['name']) ?></div>
                  <div class="region sub"><?= htmlspecialchars($it['region'] ?: 'Global') ?></div>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- 🟢 ส่วนข่าว & โปรโมชัน -->
<section class="section">
  <h3>ข่าว & การโปรโมชัน</h3>
  <div class="news-grid">
    <?php foreach ($news as $n): 
      $img = $n['image_url'] ?: 'images/sample_news.jpg'; ?>
      <div class="news-card" 
           data-bs-toggle="modal" 
           data-bs-target="#newsModal" 
           data-title="<?= htmlspecialchars($n['title']) ?>" 
           data-img="<?= htmlspecialchars($img) ?>" 
           data-content="<?= htmlspecialchars($n['content'] ?? 'ยังไม่มีรายละเอียดเพิ่มเติม') ?>">
        <img src="<?= htmlspecialchars($img) ?>" alt="news">
        <div class="body">
          <div class="title"><?= htmlspecialchars($n['title']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- 🟣 Modal แสดงข่าว -->
<div class="modal fade" id="newsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="newsTitle"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <img id="newsImage" src="" alt="news" class="img-fluid rounded mb-3">
        <p id="newsContent" class="text-light"></p>
      </div>
    </div>
  </div>
</div>

<script>
// 🧠 เมื่อกดข่าว จะโหลดข้อมูลลงใน modal ทันที
const newsModal = document.getElementById('newsModal');
newsModal.addEventListener('show.bs.modal', function (event) {
  const card = event.relatedTarget;
  const title = card.getAttribute('data-title');
  const img = card.getAttribute('data-img');
  const content = card.getAttribute('data-content');

  this.querySelector('#newsTitle').textContent = title;
  this.querySelector('#newsImage').src = img;
  this.querySelector('#newsContent').textContent = content;
});
</script>


<?php include 'includes/footer.php'; ?>
