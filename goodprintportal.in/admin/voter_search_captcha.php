<?php
// 1. INCLUDE CONFIG & SESSION FIRST
include('../includes/session.php');
include('../includes/config.php');
date_default_timezone_set('Asia/Kolkata');

// --- API CONFIGURATION (Dynamic) ---
// Ensure these variables are defined in your config or header file.
// $auth_url   = "https://apiadda.in/"; 
// $api_key    = "...";
// $secret_key = "...";

// ---------------------------------------------------------
// 2. HANDLE AJAX REQUEST (MUST BE BEFORE HEADER.PHP)
// This ensures no HTML is sent before the JSON response
// ---------------------------------------------------------
if (isset($_GET['ajax_captcha'])) {
    $ch = curl_init();
    // Dynamic URL Construction
    $url = $auth_url . "VOTER_PDF/aptcha_generate.php?" . http_build_query([
        'api_key'    => $api_key,
        'secret_key' => $secret_key
    ]);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Clean any accidental whitespace/output
    ob_clean();
    header('Content-Type: application/json');
    echo $response;
    exit();
}

// 3. INCLUDE HEADER (Now it's safe to load HTML)
include('../template/ahkweb/header.php');

// Pricing Logic
 $price_q = mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='voter_manual_flx' ");
 $price = mysqli_fetch_assoc($price_q);
 $fee = $price['price'];
 $balance = $udata['balance'];

// Initial Captcha Load for Page Render
 $captchaImg = "";
 $captchaId  = "";
 
 // Check if variables exist to prevent errors
 if(isset($auth_url) && isset($api_key)) {
    $ch = curl_init();
    // Dynamic URL Construction
    $url = $auth_url . "VOTER_PDF/aptcha_generate.php?" . http_build_query([
        'api_key'    => $api_key,
        'secret_key' => $secret_key
    ]);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if ($data && isset($data['status']) && $data['status'] === 'success') {
        $captchaImg = $data['captchaImage'];
        if (strpos($captchaImg, 'data:image') === false) {
            $captchaImg = 'data:image/jpeg;base64,' . $captchaImg;
        }
        $captchaId  = $data['captchaId'];
    }
}

// Balance Check Alert
if ($balance < $fee) {
?>
    <script>
        $(function(){
            Swal.fire('Insufficient Balance', 'Wallet amount is low. Please maintain wallet above ₹<?= $fee ?>.', 'error')
        });
        setTimeout(() => { window.location='wallet'; }, 2000);
    </script>
<?php } ?>

<!-- Custom Styles for Enhanced UI -->
<style>
    body { background-color: #f4f7f6; }
    .service-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        transition: transform 0.3s ease;
    }
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    }
    .card-header-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-bottom: none;
    }
    .card-header-custom h4 { margin-bottom: 0; font-weight: 600; letter-spacing: 0.5px; }
    .form-label { font-weight: 500; color: #555; margin-bottom: 0.5rem; }
    .form-control {
        border-radius: 8px;
        padding: 12px 15px;
        height: auto;
        border: 1px solid #e0e0e0;
        transition: border-color 0.3s;
    }
    .form-control:focus {
        border-color: #764ba2;
        box-shadow: 0 0 0 0.2rem rgba(118, 75, 162, 0.25);
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        padding: 12px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        transition: all 0.3s ease;
    }
    .btn-primary-custom:hover {
        transform: scale(1.02);
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    
    /* Upload Area Styles */
    .upload-area {
        border: 2px dashed #cbd5e0;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background-color: #f8f9fa;
    }
    .upload-area:hover { border-color: #764ba2; background-color: #f0f0ff; }

    /* Captcha Box Styles */
    .captcha-container {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f8f9fa;
        padding: 10px;
        border-radius: 10px;
        border: 1px solid #e9ecef;
    }
    .captcha-img {
        height: 40px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
        background-color: #fff;
    }
    /* The Mast Button */
    #btnCaptcha {
        background: linear-gradient(to right, #ff6b6b, #ee5a24);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(238, 90, 36, 0.3);
        transition: all 0.3s;
    }
    #btnCaptcha:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(238, 90, 36, 0.4);
    }
    #btnCaptcha i { font-size: 14px; }

    .price-badge {
        background-color: #fff3cd;
        color: #856404;
        padding: 10px 20px;
        border-radius: 50px;
        font-weight: 600;
        display: inline-block;
        border: 1px solid #ffeeba;
    }
    .powered-by {
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
        color: #888;
    }
    .powered-by span { color: #764ba2; font-weight: 700; }
    .btn-link-custom {
        background-color: #343a40;
        color: white;
        border-radius: 8px;
        padding: 10px 15px;
        text-decoration: none;
        transition: 0.3s;
    }
    .btn-link-custom:hover { background-color: #23272b; color: white; }
</style>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Advance / Manual</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="voter_adv_flx_list" class="btn btn-success btn-sm">
                    <i class="bi bi-list-ul me-1"></i> Print List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="card service-card">
                        <div class="card-header-custom text-center">
                            <h4><i class="bi bi-person-vcard-fill me-2"></i>Voter Advance & Manual</h4>
                            <small>Download or Print Voter Details</small>
                        </div>
                        <div class="card-body p-4">
                            
                            <!-- Instant PDF Link -->
                            <div class="mb-4">
                                <a href="vot_org_instant" class="btn btn-link-custom w-100 text-decoration-none">
                                    <i class="bi bi-file-earmark-pdf me-2"></i> Voter Original PDF Download Here
                                </a>
                            </div>

                            <form method="post" name="form" autocomplete="off" enctype="multipart/form-data" action="Voter_advance_print_captcha_info">
                                
                                <!-- Epic Number -->
                                <div class="mb-4">
                                    <label class="form-label">Epic Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-card-text text-muted"></i></span>
                                        <input class="form-control border-start-0 ps-0" name="epicno" type="text" placeholder="Enter Your Epic Number" required>
                                    </div>
                                </div>

                                <!-- Photo Upload -->
                                <div class="mb-4">
                                    <label class="form-label">Upload Photo <span class="text-danger">*</span></label>
                                    <div class="upload-area" onclick="document.getElementById('filedata').click()">
                                        <input type='file' id="filedata" name="" onchange="readURL(this);" style="display: none;" accept="image/*">
                                        <i class="bi bi-cloud-upload fs-2 text-muted d-block mb-2"></i>
                                        <span class="text-muted">Click to upload or drag & drop</span>
                                        <img id="preview-image" src="#" alt="" style="display:none; max-width: 100px; max-height: 100px; margin-top: 10px; border-radius: 8px;">
                                        <input class="form-control" id="blah1" name="filedata" type="hidden" value="" require>
                                    </div>
                                </div>

                                <!-- Captcha Section -->
                                <div class="mb-4">
                                    <label class="form-label">Verify Captcha <span class="text-danger">*</span></label>
                                    
                                    <!-- Captcha Container -->
                                    <div class="captcha-container mb-2">
                                        <!-- Initial Image from PHP -->
                                        <?php if ($captchaImg): ?>
                                            <img src="<?= htmlspecialchars($captchaImg) ?>" id="captcha-img" alt="Captcha" class="captcha-img">
                                        <?php else: ?>
                                            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" id="captcha-img" alt="Captcha" class="captcha-img">
                                        <?php endif; ?>
                                        
                                        <!-- Refresh Button -->
                                        <button type="button" id="btnCaptcha" onclick="generateCaptcha()">
                                            <i class="bi bi-arrow-clockwise"></i> Refresh
                                        </button>
                                    </div>

                                    <!-- Hidden ID for Form Submission -->
                                    <input type="hidden" name="captchaId" id="captchaIdInput" value="<?= htmlspecialchars($captchaId) ?>">
                                    <input type="text" name="captcha" class="form-control" id="captchaText" placeholder="Enter captcha code here" required>
                                </div>

                                <!-- Fee Display -->
                                <div class="text-center mb-4">
                                    <div class="price-badge">
                                        Application Fee: ₹<?= htmlspecialchars($price['price']) ?>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-grid">
                                    <input type="submit" id="submit" name="submit" class="btn btn-primary-custom" value="Proceed to Print">
                                </div>
                            </form>
                            
                            <!-- Footer Credit -->
                            <div class="powered-by mt-4">
                                Powered By <span>LALA WEB DEVELOPER</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<!-- Custom JavaScript -->
<script type="text/javascript">
    // Image Preview Logic
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('blah1').value = e.target.result;
                var preview = document.getElementById('preview-image');
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Safe JSON Fetch Helper
    async function safeJsonFetch(url) {
        const res = await fetch(url);
        const text = await res.text();
        try {
            return { ok: res.ok, status: res.status, data: JSON.parse(text), raw: text };
        } catch (e) {
            return { ok: false, status: res.status, data: null, raw: text, parseError: e.toString() };
        }
    }

    // Generate Captcha Function
    async function generateCaptcha() {
        const btn = document.getElementById("btnCaptcha");
        const img = document.getElementById("captcha-img");
        
        // UI Loading State
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading...';

        try {
            // Call the SAME page with ajax_captcha=1 parameter
            const url = '?ajax_captcha=1';
            const result = await safeJsonFetch(url);

            if (!result.data) {
                console.error("API returned HTML or non-JSON", result.raw);
                alert("Captcha API Error. Check console.");
                return;
            }

            if (result.data.status === "success" && result.data.captchaId) {
                // Update Hidden Input for Form
                document.getElementById("captchaIdInput").value = result.data.captchaId;

                // Update Image
                let imgSrc = result.data.captchaImage || "";
                if (imgSrc.startsWith("data:image")) {
                    img.src = imgSrc;
                } else {
                    img.src = "data:image/jpeg;base64," + imgSrc;
                }

                // Clear old text
                document.getElementById("captchaText").value = "";
            } else {
                alert("Captcha Generate Failed: " + (result.data.message || "Unknown Error"));
            }
        } catch (e) {
            console.error(e);
            alert("Error generating captcha.");
        }

        // Reset Button
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh';
    }
</script>
</body>
</html>