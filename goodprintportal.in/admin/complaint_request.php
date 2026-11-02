<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

function cmpEnsureComplaintIdSchema($conn) {
    if (!$conn) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM complaints");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];

    if (!in_array('complaint_ref', $cols, true)) {
        @mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN complaint_ref VARCHAR(64) NULL AFTER complaint_id");
    }

    @mysqli_query($conn, "DELETE FROM complaints WHERE complaint_id = 0");
    @mysqli_query($conn, "ALTER TABLE complaints MODIFY complaint_id BIGINT UNSIGNED NOT NULL");

    $pk = @mysqli_query($conn, "SHOW INDEX FROM complaints WHERE Key_name = 'PRIMARY'");
    $hasPk = false;
    if ($pk) {
        while ($row = mysqli_fetch_assoc($pk)) {
            if (($row['Key_name'] ?? '') === 'PRIMARY') { $hasPk = true; }
        }
    }
    if (!$hasPk) {
        @mysqli_query($conn, "ALTER TABLE complaints ADD PRIMARY KEY (complaint_id)");
    }

    @mysqli_query($conn, "CREATE UNIQUE INDEX idx_complaints_ref ON complaints(complaint_ref)");

    $missing = @mysqli_query($conn, "SELECT complaint_id FROM complaints WHERE complaint_ref IS NULL OR TRIM(complaint_ref) = '' ORDER BY complaint_id");
    if ($missing) {
        while ($row = mysqli_fetch_assoc($missing)) {
            $id = (int)($row['complaint_id'] ?? 0);
            if ($id <= 0) continue;
            do {
                $ref = strtoupper(bin2hex(random_bytes(12)));
                $exists = @mysqli_query($conn, "SELECT 1 FROM complaints WHERE complaint_ref = '{$ref}' LIMIT 1");
                $hasRow = $exists && mysqli_num_rows($exists) > 0;
            } while ($hasRow);
            @mysqli_query($conn, "UPDATE complaints SET complaint_ref = '{$ref}' WHERE complaint_id = {$id} LIMIT 1");
        }
    }
}

function cmpGenerateComplaintId($conn) {
    do {
        $id = random_int(100000000000, 999999999999);
        $exists = @mysqli_query($conn, "SELECT 1 FROM complaints WHERE complaint_id = {$id} LIMIT 1");
    } while ($exists && mysqli_num_rows($exists) > 0);
    return (string)$id;
}

function cmpGenerateComplaintRef($conn) {
    do {
        $ref = strtoupper(bin2hex(random_bytes(12)));
        $exists = @mysqli_query($conn, "SELECT 1 FROM complaints WHERE complaint_ref = '{$ref}' LIMIT 1");
    } while ($exists && mysqli_num_rows($exists) > 0);
    return $ref;
}

cmpEnsureComplaintIdSchema($ahk_conn);

// Admin WhatsApp number (settings table id=1, editable via admin/settings.php)
$whatsappSupport = '';
$waSettings = @mysqli_query($ahk_conn, "SELECT whatsapp_number FROM settings WHERE id=1 LIMIT 1");
if ($waSettings && ($waRow = mysqli_fetch_assoc($waSettings))) {
    $whatsappSupport = trim((string)($waRow['whatsapp_number'] ?? ''));
}
// Sanitize: allow only digits
$whatsappSupport = preg_replace('/[^0-9]/', '', $whatsappSupport);
if ($whatsappSupport === '') $whatsappSupport = '91';
$waPrefill = rawurlencode("Hello Good Print Support! I need help regarding a complaint. Please assist me.");
$waLink = "https://wa.me/{$whatsappSupport}?text={$waPrefill}";
$waDisplay = '';
if (strlen($whatsappSupport) >= 12) {
    $waDisplay = '+' . substr($whatsappSupport, 0, 2) . ' ' . substr($whatsappSupport, 2, 5) . ' ' . substr($whatsappSupport, 7);
} elseif (strlen($whatsappSupport) === 12) {
    $waDisplay = '+' . $whatsappSupport;
} else {
    $waDisplay = '+' . $whatsappSupport;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complaint_type']) && !empty(trim($_POST['message']))) {
    $complaint_type = mysqli_real_escape_string($ahk_conn, $_POST['complaint_type']);
    $message = mysqli_real_escape_string($ahk_conn, $_POST['message']);
    $utrnumber = isset($_POST['utrnumber']) && trim($_POST['utrnumber']) !== '' ? mysqli_real_escape_string($ahk_conn, $_POST['utrnumber']) : null;

    $site_id = 1;
    $user_id = $udata['id'];
    $username = $udata['name'];
    $remark = '';
    $status = 'Open';
    $date_time = date('Y-m-d H:i:s');
    $complaint_id = cmpGenerateComplaintId($ahk_conn);
    $complaint_ref = cmpGenerateComplaintRef($ahk_conn);

    $insert = mysqli_query($ahk_conn, "INSERT INTO complaints (complaint_id, complaint_ref, site_id, user_id, username, utrnumber, complaint_type, message, remark, status, date_time) VALUES ('$complaint_id', '$complaint_ref', '$site_id', '$user_id', '$username', '$utrnumber', '$complaint_type', '$message', '$remark', '$status', '$date_time')");

    if ($insert) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Complaint Submitted Successfully',
                showConfirmButton: false,
                timer: 1500
            }).then(() => { window.location = 'complaint_request_list'; });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: 'Please try again later.',
                confirmButtonText: 'OK'
            });
        </script>";
    }
}
?>


<!-- Created By FlexSecure {flex4upoint} --><div class="page-wrapper bg-light py-5" style="min-height: 85vh;">
    <div class="container">
        <h2 class="mb-4 text-primary fw-bold">Raise a Complaint</h2>

        <div class="card shadow-sm rounded-4 border-0">
            <div class="card-body p-5">
                <form id="complaintForm" method="POST" novalidate>
                    <!-- Complaint Type -->
                    <div class="form-floating mb-4">
                        <select class="form-select" id="complaint_type" name="complaint_type" required>
                            <option value="" selected disabled>Choose complaint type</option>
                            <?php
                            $complaint_types = [
                                "ADD MONEY",
                                "AADHAAR FINGERPRINT",
                                "AADHAAR NUMBER VERIFY",
                                "UID TO FULL DETAILS",
                                "AYUSHMAN CARD",
                                "e-SHRAM CARD",
                                "ENROLLMENT STATUS CHECK",
                                "CHALLAN DETAIL",
                                "DL PDF SERVER 1",
                                "DL PDF SERVER 2",
                                "LL EXAM",
                                "RATION PDF",
                                "RATION TO UID FIND",
                                "AADHAAR TO RATION PDF",
                                "AADHAAR TO PAN FIND",
                                "PAN DETAILS",
                                "PAN AADHAR LINK STATUS",
                                "PAN TO MASK AADHAR",
                                "IFSC CODE",
                                "MOBILE NUMBER VERIFY",
                                "PINCODE VERIFY",
                                "RC PDF SERVER 1",
                                "RC PDF SERVER 2",
                                "VOTER ADVANCE",
                                "VOTER MOBILE LINK",
                                "VOTER PDF",
                                "OTHER PROBLEM",
                                "I WANT REFUND MY AMOUNT"
                            ];
                            foreach ($complaint_types as $type) {
                                echo '<option value="'.htmlspecialchars($type).'">'.htmlspecialchars($type).'</option>';
                            }
                            ?>
                        </select>
                        <label for="complaint_type">Complaint Type <span class="text-danger">*</span></label>
                        <div class="invalid-feedback">Please select a complaint type.</div>
                    </div>

                    <!-- UTR Number (hidden by default) -->
                    <div class="form-floating mb-4 d-none" id="utr_container">
                        <input type="text" class="form-control" id="utrnumber" name="utrnumber" placeholder="UTR/Reference Number">
                        <label for="utrnumber">UTR / Reference Number (Optional)</label>
                    </div>

                    <!-- Message -->
                    <div class="form-floating mb-4">
                        <textarea class="form-control" placeholder="Describe your issue here..." id="message" name="message" style="height: 120px" required></textarea>
                        <label for="message">Complaint Message <span class="text-danger">*</span></label>
                        <div class="invalid-feedback">Please enter your complaint message.</div>
                    </div>

                    <button type="submit" class="btn btn-primary px-5 fw-semibold" id="submitBtn">Submit Complaint</button>
                </form>

                <!-- ===== PREMIUM WHATSAPP SUPPORT CTA ===== -->
                <div class="wa-cta-wrap">
                    <div class="wa-cta-divider"><span>OR</span></div>
                    <p class="wa-cta-text">For quick and better support contact Via WhatsApp</p>
                    <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener noreferrer" class="wa-btn">
                        <span class="wa-icon">
                            <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M19.11 17.205c-.372 0-1.088 1.39-1.518 1.39a.63.63 0 0 1-.315-.1c-.802-.402-1.504-.817-2.163-1.447-.545-.516-1.146-1.29-1.46-1.963a.426.426 0 0 1-.073-.215c0-.33.99-.945.99-1.49 0-.143-.73-2.09-.832-2.335-.143-.372-.214-.487-.6-.487-.187 0-.36-.043-.53-.043-.302 0-.53.115-.746.315-.688.645-1.032 1.318-1.06 2.264v.114c-.015.99.472 1.977 1.017 2.78 1.23 1.82 2.506 3.41 4.554 4.34.616.287 2.035.888 2.722.888.817 0 2.15-.515 2.478-1.318.13-.33.244-.7.257-1.055c.015-.43-.143-.7-.3-1.072-.372-.787-1.23-1.245-1.587-1.245zm1.319-11.947C17.565 3.94 14.388 3.062 11.16 3.71 6.36 4.665 2.584 8.44 1.63 13.242c-1.295 6.398 3.41 11.72 9.174 13.155a13.07 13.07 0 0 0 4.188.692 13.1 13.1 0 0 0 3.05-.36c.83-.185 4.188-.693 6.155-2.656 1.97-1.963 2.645-4.446 2.75-4.78.238-.773.375-1.333.375-1.988 0-.3-.135-.673-.375-1.015-.6-.772-1.765-1.246-2.46-.742-.3.143-.538.413-.615.585-.195.412-.39.937-.585 1.056-.26.14-.452.067-.644-.11l-2.028-1.395c-1.05-.7-1.757-1.085-2.413-1.108a7.43 7.43 0 0 1-.69-.068c-.31-.06-.6-.15-.68-.41-.084-.26-.064-.524.013-.78l.86-3.114c.213-.878-.05-1.67-.687-2.24-.637-.573-1.526-.79-2.37-.612z" fill="currentColor"/>
                            </svg>
                        </span>
                        <span class="wa-label">
                            <span class="wa-title">Chat on WhatsApp</span>
                            <span class="wa-num"><?= htmlspecialchars($waDisplay) ?></span>
                        </span>
                        <span class="wa-arrow">→</span>
                    </a>
                    <p class="wa-note">⚡ Instant Response • 24×7 Support</p>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 CDN (if not already included) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const complaintType = document.getElementById('complaint_type');
    const utrContainer = document.getElementById('utr_container');
    const utrInput = document.getElementById('utrnumber');
    const form = document.getElementById('complaintForm');

    function toggleUTR() {
        if (complaintType.value === 'ADD MONEY') {
            utrContainer.classList.remove('d-none');
            utrInput.setAttribute('required', 'required');
        } else {
            utrContainer.classList.add('d-none');
            utrInput.removeAttribute('required');
            utrInput.value = '';
        }
    }

    complaintType.addEventListener('change', toggleUTR);

    // Initialize visibility on page load
    toggleUTR();

    // Bootstrap form validation
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        form.classList.add('was-validated');
    }, false);
});
</script>

<style>
    body {
        background: #f0f4f8;
    }

    .page-wrapper {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    h2 {
        letter-spacing: 0.04em;
    }

    .form-floating > label {
        font-weight: 500;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4a90e2, #357ABD);
        border: none;
        transition: background 0.3s ease;
        box-shadow: 0 4px 12px rgb(53 122 189 / 0.4);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #357ABD, #2a5c95);
        box-shadow: 0 6px 15px rgb(42 92 149 / 0.6);
    }

    /* ===== PREMIUM WHATSAPP CTA ===== */
    .wa-cta-wrap {
        margin-top: 36px;
        text-align: center;
    }

    .wa-cta-divider {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }

    .wa-cta-divider::before,
    .wa-cta-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, transparent, #cbd5e1, transparent);
    }

    .wa-cta-divider span {
        padding: 2px 18px;
        margin: 0 12px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.12em;
        color: #64748b;
        background: #f1f5f9;
        border-radius: 999px;
    }

    .wa-cta-text {
        font-size: 16px;
        font-weight: 600;
        color: #334155;
        margin: 0 0 18px 0;
        letter-spacing: 0.01em;
    }

    .wa-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        padding: 14px 28px;
        min-width: 340px;
        border-radius: 14px;
        text-decoration: none !important;
        color: #fff !important;
        font-weight: 700;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #25D366 0%, #128C7E 50%, #075E54 100%);
        box-shadow:
            0 8px 22px rgba(37, 211, 102, 0.35),
            0 0 0 1px rgba(255, 255, 255, 0.08) inset,
            0 2px 4px rgba(7, 94, 84, 0.15);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateY(0);
    }

    /* Shine sweep animation */
    .wa-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 60%;
        height: 100%;
        background: linear-gradient(
            120deg,
            transparent 0%,
            rgba(255, 255, 255, 0.35) 50%,
            transparent 100%
        );
        transform: skewX(-25deg);
        animation: waShine 2.8s ease-in-out infinite;
    }

    @keyframes waShine {
        0%   { left: -100%; }
        55%  { left: 120%; }
        100% { left: 120%; }
    }

    /* Pulse glow */
    .wa-btn::after {
        content: '';
        position: absolute;
        inset: -3px;
        border-radius: 16px;
        background: linear-gradient(135deg, #25D366, #075E54);
        filter: blur(10px);
        opacity: 0.45;
        z-index: -1;
        animation: waPulse 2.2s ease-in-out infinite;
    }

    @keyframes waPulse {
        0%, 100% { opacity: 0.3; transform: scale(1); }
        50%      { opacity: 0.65; transform: scale(1.02); }
    }

    .wa-btn:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow:
            0 14px 30px rgba(37, 211, 102, 0.5),
            0 0 0 1px rgba(255, 255, 255, 0.12) inset,
            0 4px 8px rgba(7, 94, 84, 0.2);
    }

    .wa-btn:active {
        transform: translateY(-1px) scale(1.005);
    }

    .wa-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        color: #25D366;
        background: #fff;
        box-shadow: 0 3px 8px rgba(7, 94, 84, 0.25);
        flex-shrink: 0;
        z-index: 2;
    }

    .wa-icon svg {
        width: 26px;
        height: 26px;
    }

    .wa-label {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        line-height: 1.1;
        z-index: 2;
        text-align: left;
        flex: 1;
    }

    .wa-title {
        font-size: 16px;
        font-weight: 800;
        letter-spacing: 0.01em;
    }

    .wa-num {
        font-size: 12px;
        font-weight: 600;
        opacity: 0.9;
        margin-top: 3px;
        letter-spacing: 0.04em;
    }

    .wa-arrow {
        font-size: 22px;
        font-weight: 800;
        z-index: 2;
        color: #fff;
        transition: transform 0.3s ease;
    }

    .wa-btn:hover .wa-arrow {
        transform: translateX(4px);
    }

    .wa-note {
        margin-top: 14px;
        font-size: 12px;
        font-weight: 600;
        color: #25D366;
        letter-spacing: 0.06em;
    }

    @media (max-width: 480px) {
        .wa-btn { min-width: 100%; padding: 13px 20px; }
        .wa-cta-text { font-size: 14px; }
    }
</style>

										 		 		 <!--<button class="btn btn-danger font-weight-bold" type="button">Service Down</button>-->
								<!--start list page -->
 
<!--end page wrapper -->
<?php 
include('footer.php');
?>
<!-- Bootstrap JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery.easy-pie-chart/jquery.easypiechart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/sparkline-charts/jquery.sparkline.min.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/excanvas.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
<script>
$(function() {
    $(".knob").knob();
});
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<!--app JS-->
<script src="../template/ahkweb/assets/js/app.js"></script>
<!-- datatable -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
		$(document).ready(function() {
			$('#example').DataTable();
		  } );
	</script>
	
	<script>
		$(document).ready(function() {
			var table = $('#example2').DataTable( {
				lengthChange: false,
				buttons: [ 'copy', 'excel', 'pdf', 'print']
			} );
		 
			table.buttons().container()
				.appendTo( '#example2_wrapper .col-md-6:eq(0)' );
		} );
	</script>
	
</body>



</html>