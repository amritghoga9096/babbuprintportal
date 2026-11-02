<?php 
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// UTF-8 safe output
header('Content-Type: text/html; charset=UTF-8');

// ================== NOTIFICATION ==================
$query = "SELECT * FROM notification WHERE id='1'";
$res = mysqli_query($ahk_conn, $query);
$row = mysqli_fetch_assoc($res);
$message = $row ? $row['message'] : "No message found";
?>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Nunito:wght@500;700&display=swap" rel="stylesheet">

<style>
body {
  background: #f4f6f8;
  font-family: 'Inter', sans-serif;
  color: #222;
  margin: 0;
  padding: 0;
}
.page-wrapper { padding: 22px 28px; }

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
  box-shadow: 0 6px 18px rgba(168,109,223,0.18);
  margin-bottom: 18px;
  font-size: 15px;
}
.go-back .arrow { font-size: 18px; }

.header-row {
  display:flex;
  align-items:center;
  gap:18px;
  margin-bottom: 12px;
}

.services-wrap {
  width:100%;
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
  transition: box-shadow .3s ease;
}
.service-item:hover {
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
  border-radius: 12px;
}

.service-circle {
  width: 200px;
  height: 200px;
  border-radius: 25%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 12px 28px rgba(18,24,34,0.08);
  transition: transform .28s ease, box-shadow .28s ease;
  border: 6px solid rgba(255,255,255,0.55);
  overflow: hidden;
  position: relative;
}

.service-circle[data-bg="pink"]   { background: linear-gradient(180deg, #ffecec, #ffdfe0); }
.service-circle[data-bg="blue"]   { background: linear-gradient(180deg, #e7f7ff, #e6f2ff); }
.service-circle[data-bg="green"]  { background: linear-gradient(180deg, #eaffef, #e7ffe6); }
.service-circle[data-bg="lav"]    { background: linear-gradient(180deg, #f6ecff, #f3e7ff); }
.service-circle[data-bg="cream"]  { background: linear-gradient(180deg, #fff6ea, #fff4e6); }
.service-circle[data-bg="grey"]   { background: linear-gradient(180deg, #f0f4f7, #eef3f9); }

.service-circle img {
  width: 150px;
  height: 150px;
  object-fit: contain;
  display: block;
  pointer-events: none;
  user-select: none;
  transition: transform .3s ease;
}

.service-item:hover .service-circle {
  transform: translateY(-10px);
  box-shadow: 0 24px 48px rgba(0,0,0,0.14);
}
.service-item:hover .service-circle img {
  transform: scale(1.12);
}

.service-label {
  margin-top: 16px;
  font-size: 16px;
  font-weight: 600;
  color: #222;
  max-width: 180px;
  line-height: 1.3;
}

@media (max-width: 1200px) {
  .service-item { width: 180px; }
  .service-circle { width: 160px; height:160px; }
  .service-circle img { width:80px; height:80px; }
}

@media (max-width: 768px) {
  .services-grid { gap: 24px; justify-content: center; }
  .service-item { width: 46%; max-width: 220px; }
}

@media (max-width: 480px) {
  .service-item { width: 100%; max-width: none; }
  .service-circle { width: 160px; height:160px; }
  .service-circle img { width:80px; height:80px; }
}

.service-link:focus .service-circle {
  outline: 3px solid rgba(37,117,252,0.18);
  transform: translateY(-6px);
}
</style>

<!-- ================== PAGE CONTENT ================== -->
<div class="page-wrapper">
  <div class="header-row">
    <a class="go-back" href="javascript:void(0)" onclick="history.back();">
      <span class="arrow">&#8592;</span> <span>Go Back</span>
    </a>
    <div style="margin-left:8px; color:#555; font-weight:600;">Hi, <?php echo $udata['name']; ?></div>
  </div>

  <div class="services-wrap">
    <h2 style="font-family: 'Nunito', sans-serif; font-weight:800; color:#222; margin: 6px 0 4px;">Vehicle Services</h2>
      <p style="color: #e53935; margin-top: 0; margin-bottom: 18px;">
  All the essential Vehicle-related services in one place - fast, secure, and easy to access!
</p>

    <div class="services-grid">
      <?php
      $services = [
        //   ["name" => "Rc Pdf Server 3",         "link" => "rc_get",               "image" => "../admin/uploads/service_icom/vachile.png", "bg" => "pink"],
          ["name" => "Instant Rc Pdf",   "link" => "rc_get_s5",             "image" => "../admin/uploads/service_icom/vachile.png", "bg" => "blue"],
          ["name" => "Instant Rc Pdf S5",      "link" => "rc_get",                "image" => "../admin/uploads/service_icom/vachile.png", "bg" => "pink"],
           ["name" => "Instant RC PDF Without Chip",  "link" => "rc_get_nochip",         "image" => "../admin/uploads/service_icom/bike.png", "bg" => "lav"],
           ["name" => "Instant RC PDF Server 2",      "link" => "rc_get_server2",        "image" => "/s2.png", "bg" => "green"],
           ["name" => "Instant RC PDF Server 3",      "link" => "rc_get_server3",        "image" => "/s3.png", "bg" => "pink"],
           ["name" => "Instant RC PDF Server 4",      "link" => "rc_get_server4",        "image" => "/s4.png", "bg" => "lav"],
        //   ["name" => "Rc Find By Mobile No",      "link" => "rcMobile_details",                "image" => "../admin/uploads/service_icom/dlfind022.png", "bg" => "grey"],
        //   ["name" => "Chassis To Rc Find",      "link" => "chassis_to_Rc",                "image" => "../admin/uploads/Services/best-credit-card-2.png", "bg" => "pink"],
          ["name" => "Chassis To Rc Find",      "link" => "chassis_to_rc",                "image" => "../admin/uploads/service_icom/llltest0231.png", "bg" => "green"],
          ["name" => "Vehical To Mobile Find", "link" => "vehicle_to_mobile_find",  "image" => "https://goodprintportal.in/vtm.png", "bg" => "cream"],
          ["name" => "Instant Challan Details",  "link" => "instant_challan_details",      "image" => "../admin/uploads/service_icom/challan01.png", "bg" => "cream"],
          ["name" => "DL Details Instant",    "link" => "DL_Instant_flx",                  "image" => "../admin/uploads/service_icom/dlfind022.png", "bg" => "lav"],
          ["name" => "Instant DL Pdf",      "link" => "dl_pdf_s3",                     "image" => "../admin/uploads/service_icom/dravingll.png", "bg" => "green"],
          ["name" => "Instant DL Pdf S2",   "link" => "DL_Instant_flx",                "image" => "../admin/uploads/service_icom/driving-licence.png", "bg" => "pink"],
          ["name" => "Instant DL Pdf S3",   "link" => "blue_dl_pdf",                   "image" => "../admin/uploads/service_icom/driving-licence.png", "bg" => "blue"],
        //  ["name" => "DL Pdf Blue Server 2",   "link" => "DL_Instant_flx",               "image" => "../admin/uploads/service_icom/driving-licence.png", "bg" => "grey"],
        //   ["name" => "DL Find Instant",    "link" => "DL_Find",                  "image" => "../admin/uploads/service_icom/dlfind022.png", "bg" => "lav"],
        // ["name" => "LL Pdf Request",    "link" => "Ll_pdf",                  "image" => "../admin/uploads/service_icom/dlfind022.png", "bg" => "lav"],
          ["name" => "Instant LL Pdf",    "link" => "instant_ll_pdf",                "image" => "../admin/uploads/service_icom/driving-licence.png", "bg" => "blue"],
          ["name" => "LL Request Exam",    "link" => "ll_request",                "image" => "../admin/uploads/service_icom/llltest0231.png", "bg" => "grey"],
           ["name" => " RC Information",    "link" => "rc_get",                "image" => "../admin/uploads/service_icom/llltest0231.png", "bg" => "grey"],
        //   ["name" => "Puc Certificate Apply",   "link" => "puc_apply",                "image" => "../admin/uploads/service_icom/puclive.png", "bg" => "green"],
      ];

      $prices = [];
      $res = mysqli_query($ahk_conn, "SELECT service_name, price FROM pricing");
      while ($r = mysqli_fetch_assoc($res)) { $prices[$r['service_name']] = $r['price']; }

      foreach ($services as $svc):
        $name  = htmlspecialchars($svc['name']);
        $link  = htmlspecialchars($svc['link']);
        $img   = htmlspecialchars($svc['image']);
        $bg    = htmlspecialchars($svc['bg'] ?? 'grey');
      ?>
      <div class="service-item">
        <a class="service-link" href="<?php echo (strpos($link, 'http') === 0) ? $link : $link; ?>" <?php echo (strpos($link, 'http') === 0) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> aria-label="<?php echo $name; ?>" style="text-decoration:none;">
          <div class="service-circle" data-bg="<?php echo $bg; ?>">
            <img src="<?php echo $img; ?>" alt="<?php echo $name; ?> icon" />
          </div>
          <div class="service-label"><?php echo $name; ?></div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include('footers.php'); ?>



<!-- Scripts -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
