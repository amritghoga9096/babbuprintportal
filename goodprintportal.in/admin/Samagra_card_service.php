<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

header('Content-Type: text/html; charset=UTF-8');
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Nunito:wght@500;700;800&display=swap" rel="stylesheet">

<style>
body {
  background: #f4f6f8;
  font-family: 'Inter', sans-serif;
  color: #222;
  margin: 0;
  padding: 0;
}

.page-wrapper {
  padding: 22px 28px;
}

.header-row {
  display: flex;
  align-items: center;
  gap: 18px;
  margin-bottom: 12px;
}

.go-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #a86ddf;
  color: #fff;
  padding: 10px 16px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  box-shadow: 0 6px 18px rgba(168, 109, 223, 0.18);
  font-size: 15px;
}

.go-back:hover {
  color: #fff;
}

.services-wrap {
  width: 100%;
  box-sizing: border-box;
  padding: 12px 8px;
}

.services-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 50px;
  justify-content: flex-start;
  align-items: flex-start;
  padding-top: 12px;
}

.service-item {
  width: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.service-circle {
  width: 200px;
  height: 200px;
  border-radius: 25%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, #f6ecff, #f3e7ff);
  box-shadow: 0 12px 28px rgba(18, 24, 34, 0.08);
  border: 6px solid rgba(255, 255, 255, 0.55);
  overflow: hidden;
  transition: transform .28s ease, box-shadow .28s ease;
}

.service-circle img {
  width: 150px;
  height: 150px;
  object-fit: contain;
  display: block;
  pointer-events: none;
  user-select: none;
  transition: transform .3s ease;
}

.service-link:hover .service-circle {
  transform: translateY(-10px);
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.14);
}

.service-link:hover .service-circle img {
  transform: scale(1.08);
}

.service-label {
  margin-top: 16px;
  font-size: 16px;
  font-weight: 600;
  color: #222;
  max-width: 190px;
  line-height: 1.3;
}

.service-link:focus .service-circle {
  outline: 3px solid rgba(37, 117, 252, 0.18);
  transform: translateY(-6px);
}

@media (max-width: 1200px) {
  .service-item {
    width: 180px;
  }

  .service-circle {
    width: 160px;
    height: 160px;
  }

  .service-circle img {
    width: 120px;
    height: 120px;
  }
}

@media (max-width: 768px) {
  .page-wrapper {
    padding: 18px 12px;
  }

  .services-grid {
    gap: 24px;
    justify-content: center;
  }

  .service-item {
    width: 46%;
    max-width: 220px;
  }
}

@media (max-width: 480px) {
  .header-row {
    flex-wrap: wrap;
  }

  .service-item {
    width: 100%;
    max-width: none;
  }

  .service-circle {
    width: 160px;
    height: 160px;
  }
}
</style>

<div class="page-wrapper">
  <div class="header-row">
    <a class="go-back" href="javascript:void(0)" onclick="history.back();">
      <span>&#8592;</span>
      <span>Go Back</span>
    </a>
    <div style="margin-left:8px; color:#555; font-weight:600;">
      Hi, <?php echo htmlspecialchars($udata['name']); ?>
    </div>
  </div>

  <div class="services-wrap">
    <h2 style="font-family:'Nunito', sans-serif; font-weight:800; color:#222; margin:6px 0 4px;">
      Samagra Portal Services
    </h2>
    <p style="color:#e53935; margin-top:0; margin-bottom:18px;">
      All Samagra Portal services in one place - fast, secure, and easy to access!
    </p>

    <div class="services-grid">
      <?php
      $logo = 'https://goodprintportal.in/smg.png';
      $services = [
        ['name' => 'Samagra PDF Without OTP', 'link' => 'samagra_pdf_without_otp.php'],
        ['name' => 'New Apply', 'link' => 'samagra_new_apply.php'],
        ['name' => 'Member Delete', 'link' => 'samagra_member_delete.php'],
        ['name' => 'Member Transfer', 'link' => 'samagra_member_transfer.php'],
        ['name' => 'Married to Divorce', 'link' => 'samagra_married_divorce.php'],
        ['name' => 'Samagra KYC', 'link' => 'samagra_kyc.php'],
        ['name' => 'New Family ID', 'link' => 'samagra_new_family_id.php'],
        ['name' => 'Samagra Pravasan', 'link' => 'samagra_pravasan.php'],
        ['name' => 'Merge Family ID', 'link' => 'samagra_merge_family_id.php'],
        ['name' => 'Recover Member', 'link' => 'samagra_recover_member.php'],
      ];

      foreach ($services as $service):
        $name = htmlspecialchars($service['name']);
        $link = htmlspecialchars($service['link']);
      ?>
        <div class="service-item">
          <a class="service-link" href="<?php echo $link; ?>" aria-label="<?php echo $name; ?>" style="text-decoration:none;">
            <div class="service-circle">
              <img src="<?php echo $logo; ?>" alt="<?php echo $name; ?> icon">
            </div>
            <div class="service-label"><?php echo $name; ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include('footers.php'); ?>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
