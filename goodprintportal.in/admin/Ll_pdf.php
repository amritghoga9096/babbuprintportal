<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fetch fee from database
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='Driving_Licence_Pdf' "));
$fee = $price['price'];

$status = '';
$familyId = '';
$name = '';
$pdf = '';
$errore = '';

if ($_POST['hrfmid']) {
    $hrfmid = $_POST['hrfmid'];
    $appliedby = $udata['phone'];
    $debit_fee =  $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        $api_key = urlencode($flexsecure_key); // Replace with actual API key  

        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v1/LL_application_pdf.php?idNumber=$hrfmid&apiKey=$api_key";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $resdata = json_decode($response, true) ?? [];

        
        $pdf = $resdata['pdf'] ?? '';
        $status = $resdata['status'] ?? '';
        $errore = $resdata['error'] ?? '';

        if ($errore) {
            echo "<script>$(function(){ Swal.fire('$errore', 'Api Response Failed.', 'error') }); setTimeout(() => { window.location=''; }, 5000);</script>";
        } elseif ($status === "100") {
            // Deduct fee and insert history
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','LL Card PDF','1','Debit')");
        }
    } else {
        echo "<script>$(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    }
}
?>

<style>
/* GLOBAL */
body {
    background: #eef3f9;
    font-family: "Poppins", sans-serif;
}

/* GRID LAYOUT */
.service-container {
    display: grid;
    grid-template-columns: 37% 63%;
    gap: 25px;
    align-items: start; /* Prevent left box stretching */
}
@media(max-width:992px){
    .service-container { grid-template-columns: 1fr; }
}

/* LEFT BOX (card-box1) */
.card-box1 {
    background: linear-gradient(135deg, #e7f1ff, #ffffff);
    border-radius: 14px;
    padding: 25px;
    border: 2px solid #d7e5ff;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
}

.card-box1 .box-title {
    font-size: 21px;
    font-weight: 700;
    color: #0052cc;
    margin-bottom: 20px;
}

/* RIGHT BOX (card-box) */
.card-box {
    background: #ffffff;
    border-radius: 16px;
    padding: 25px;
    border: 2px solid #f5f5f5;
    box-shadow: 0 3px 14px rgba(0,0,0,0.07);
    display: flex;
    flex-direction: column;
}

/* Input Field */
.form-control {
    border-radius: 8px;
    padding: 11px;
    border: 1px solid #bfcbe3;
}

/* Submit Button */
.btn-submit {
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, #0066ff, #00c6ff);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    transition: .3s;
}

/* Fee Box */
.fee-box {
    background: #eaf2ff;
    padding: 10px;
    border-left: 5px solid #ff0505;
    border-radius: 6px;
    font-weight: 600;
    color: #1a8900;
}

/* Right Side Info-box */
.info-box {
    background: #fff8d8;
    padding: 18px;
    border-left: 5px solid #f1c40f;
    border-radius: 10px;
    margin-bottom: 20px;
}

/* PDF Scrollable Container */
.pdf-wrapper {
    max-height: 650px;
    overflow-y: auto;
    border-radius: 12px;
}

/* PDF iframe */
#pdfContainer iframe {
    width: 100%;
    height: 650px;
    border: none;
    border-radius: 10px;
}

/* PDF Placeholder */
.pdf-placeholder {
    text-align:center;
    padding: 60px 20px;
}
.pdf-placeholder img {
    width: 130px;
    opacity: .5;
}
</style>


<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">LL PDF</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="service-container">

            <div class="card-box1">
                <div class="box-title">Get LL PDF</div>
                <form method="POST" class="row g-3">
                    <div class="col-12">
                        <label>LL PDF Id Number</label>
                        <input name="hrfmid" minlength="0" maxlength="120" class="form-control" required placeholder="Enter ApplNo Id Number">
                    </div>
                    <div class="col-12">
                        <div class="fee-box">Fee: ₹ <?= $fee ?></div>
                    </div>
                    <div class="col-12">
                        <button class="btn-submit" name="submit">
                            <i class="bi bi-check-circle"></i> Submit PDF Generator
                        </button>
                    </div>
                </form>
            </div>

            <div class="card-box">

                <?php if($status === "100" && $pdf): ?>
                <div class="info-box">
                    <h5 class="fw-bold text-warning m-0">LL PDF Card Details</h5>
                    <table class="table table-bordered mt-2">
                         
                        <tr>
                            <th>Download</th>
                            <td>
                                <a id="downloadBtn" class="btn btn-primary btn-sm" download="<?= htmlspecialchars($familyId) ?>.pdf">
                                    <i class="bi bi-download"></i> Download PDF
                                </a>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="box-title mt-4">PDF Preview</div>
                <div class="pdf-wrapper">
                    <div id="pdfContainer"></div>
                </div>

                <script>
                function loadPDF(base64Data) {
                    if (base64Data.includes("base64,")) {
                        base64Data = base64Data.split("base64,")[1];
                    }
                    const binary = atob(base64Data);
                    const len = binary.length;
                    const bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i);
                    const blob = new Blob([bytes], { type:"application/pdf" });
                    const fileURL = URL.createObjectURL(blob);

                    document.getElementById("pdfContainer").innerHTML = `<iframe src="${fileURL}#zoom=100"></iframe>`;
                    document.getElementById("downloadBtn").href = fileURL;
                }
                loadPDF("<?= $pdf ?>");
                </script>

                <?php else: ?>
                <div class="pdf-placeholder">
                    <img src="https://cdn-icons-png.flaticon.com/512/747/747376.png">
                    <h6 class="text-muted mt-3">No PDF Loaded</h6>
                    <p class="text-muted">Submit ApplNo ID to preview PDF</p>
                </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</div>




<?php include('footer.php'); ?>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const languages = [
      { code: "hi", name: "Hindi" },
      { code: "bn", name: "Bengali" },
      { code: "ta", name: "Tamil" },
      { code: "te", name: "Telugu" },
      { code: "mr", name: "Marathi" },
      { code: "gu", name: "Gujarati" },
      { code: "kn", name: "Kannada" },
      { code: "ml", name: "Malayalam" },
      { code: "pa", name: "Punjabi" },
      { code: "ur", name: "Urdu" },
      { code: "as", name: "Assamese" },
      { code: "or", name: "Odia" }
    ];

    const stateSelect = document.getElementById("language_Code");

    languages.forEach(function(language) {
      const option = document.createElement("option");
      option.value = language.code;
      option.textContent = language.name;
      stateSelect.appendChild(option);
    });
  });
</script>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</html>
