<?php
// recharge_flex4u.php
// NEW LOGIC: debit FIRST → insert PENDING → run API → update result → refund if fail

include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

function jsonResp($arr) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

header('X-Frame-Options: SAMEORIGIN');
if (!isset($ahk_conn) || !$ahk_conn) die("DB connection missing.");

if (!isset($flex_api_key) || empty($flex_api_key)) $flex_api_key = $flexsecure_key ?? $FALLBACK_FLEX_KEY;
if (!isset($flex_base_url) || empty($flex_base_url)) {
    if (!empty($flexsecureapiurl)) $flex_base_url = "https://{$flexsecureapiurl}/api/v1";
    else $flex_base_url = $FALLBACK_BASE;
}

// service check
if (empty($webdata) || ($webdata['mobile_recharge_active'] ?? 0) != 1) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <script>Swal.fire({icon:'error',title:'Service Disabled',text:'Mobile Recharge Service is Currently Disabled!'})
          .then(()=>{window.location='index.php';});</script>";
    include('../template/ahkweb/footer.php'); exit;
}

// ----------- Operators ----------
$_C = [];
$res = mysqli_query($ahk_conn, "SHOW TABLES LIKE 'operator_codes'");
if ($res && mysqli_num_rows($res) > 0) {
    $qr = mysqli_query($ahk_conn, "SELECT id, name, opcode, pct FROM operator_codes ORDER BY id ASC");
    while ($r = mysqli_fetch_assoc($qr)) {
        $_C[intval($r['id'])] = ["n" => $r['name'], "t" => "mobile", "p" => floatval($r['pct']), "opcode" => $r['opcode']];
    }
}
if (empty($_C)) {
    $_C = [
        1 => ["n" => "AIRTEL", "t" => "mobile", "p" => floatval($webdata['airtal'] ?? 0), "opcode" => "A"],
        2 => ["n" => "IDEA , VI", "t" => "mobile", "p" => floatval($webdata['vi'] ?? 0), "opcode" => "V"],
        3 => ["n" => "VODAFONE , VI", "t" => "mobile", "p" => floatval($webdata['vi'] ?? 0), "opcode" => "V"],
        4 => ["n" => "JIO", "t" => "mobile", "p" => floatval($webdata['jio'] ?? 0), "opcode" => "RC"],
        5 => ["n" => "BSNL SPECIAL", "t" => "mobile", "p" => floatval($webdata['bsnl'] ?? 0), "opcode" => "BR"],
        6 => ["n" => "BSNL TOPUP", "t" => "mobile", "p" => floatval($webdata['bsnl'] ?? 0), "opcode" => "BT"],
    ];
}

// user wallet
$userid = $udata['phone'];
$wallet = floatval($udata['balance']);

// AJAX plan fetch
if (!empty($_GET['action']) && $_GET['action'] === 'fetch_plan') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    require __DIR__ . "/fetch_plan.php";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mobile'], $_POST['operator'], $_POST['amount'])) {

    $mobile = preg_replace('/\D+/', '', $_POST['mobile']);
    $op = intval($_POST['operator']);
    $amount = floatval($_POST['amount']);

    if (strlen($mobile) !== 10) {
        echoError("Invalid Mobile Number");
    }
    if (!isset($_C[$op])) {
        echoError("Invalid Operator");
    }
    if ($amount <= 0) {
        echoError("Invalid Amount");
    }

    $d = $_C[$op];
    $opname = $d['n'];
    $opcode = $d['opcode'];
    $pct = floatval($d['p']);

    // commission
    $commission = round(($amount * $pct) / 100, 2);
    $netDebit = round($amount - $commission, 2);

    if ($wallet < $netDebit) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>Swal.fire({icon:'error',title:'Insufficient Balance',text:'Please add balance.'})
              .then(()=>{window.location='wallet.php'});</script>";
        include('../template/ahkweb/footer.php'); exit;
    }

    // ----------------------------------------------
    // STEP 1: Debit Wallet FIRST + Insert Pending Row
    // ----------------------------------------------
    mysqli_begin_transaction($ahk_conn);

    try {

        // deduct wallet
        $stmt = mysqli_prepare($ahk_conn, "UPDATE users SET balance = balance - ? WHERE phone = ?");
        mysqli_stmt_bind_param($stmt, 'ds', $netDebit, $userid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // insert PENDING recharge history
        $ins = "INSERT INTO recharge_history 
        (userid, mobile, operator, amount, status, debitamount, commission, msg, date)
        VALUES (?, ?, ?, ?, 'Pending', ?, ?, '', NOW())";

        $stmtIns = mysqli_prepare($ahk_conn, $ins);
        mysqli_stmt_bind_param($stmtIns, "sssddd", $userid, $mobile, $opname, $amount, $netDebit, $commission);
        mysqli_stmt_execute($stmtIns);

        $rechargeID = mysqli_insert_id($ahk_conn); // store ID to update later
        mysqli_stmt_close($stmtIns);

        // wallet log
        $newBalance = $wallet - $netDebit;
        $purpose = "Recharge $mobile Proccessed";
        $timestamp = date("Y-m-d H:i:s");

        $insW = "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type, date)
                 VALUES (?, ?, ?, ?, '1', 'Debit', ?)";
        $stmtW = mysqli_prepare($ahk_conn, $insW);
        mysqli_stmt_bind_param($stmtW, 'ssdss', $userid, $netDebit, $newBalance, $purpose, $timestamp);
        mysqli_stmt_execute($stmtW);
        mysqli_stmt_close($stmtW);

        mysqli_commit($ahk_conn);

    } catch (Exception $e) {
        mysqli_rollback($ahk_conn);
        echoError("DB Error: " . $e->getMessage());
    }

    // ----------------------------------------------
    // STEP 2: NOW call API after safe debit
    // ----------------------------------------------
    $apiUrl = rtrim($flex_base_url, '/') . "/mobileRecharge/recharge?apiKey=$flex_api_key&number=$mobile&amount=$amount&opcode=$opcode";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 900
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || !$response) {
        apiFailUpdate($rechargeID, "API Error: " . ($curlError ?: "Empty response"), $userid, $netDebit);
        exit;
    }

    $jr = json_decode($response, true);
    if (!$jr) {
        apiFailUpdate($rechargeID, "Invalid API Response", $userid, $netDebit);
        exit;
    }

    // success detection
    $success =
        (isset($jr['status']) && $jr['status'] == 100) ||
        (isset($jr['statusMessage']) && strtolower($jr['statusMessage']) === "success") ||
        (isset($jr['message']) && stripos($jr['message'], "success") !== false);

    $statusMsg = $jr['error'] ?? ($success ? "Proccess" : "Failed");
    $opid = $jr['opid'] ?? "";
    $orderid = $jr['orderid'] ?? "";
    $msg = $jr['message'] ?? "";

    // ----------------------------------------------
    // STEP 3: Update success OR rollback failure
    // ----------------------------------------------

    if ($success) {
        // update success
        mysqli_query($ahk_conn, "
            UPDATE recharge_history 
            SET status='Pending', opid='$opid', rbid='$orderid', msg='$msg'
            WHERE id=$rechargeID
        ");

        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            Swal.fire({
                icon:'success',
                title:'Recharge Successful',
                html:`<b>Mobile:</b> $mobile<br><b>Amount:</b> ₹$amount<br><b>Status:</b> $statusMsg`
            }).then(()=>{window.location='mobileRechargeHistory';});
        </script>";
        include('../template/ahkweb/footer.php');
        exit;

    } else {
        // rollback wallet + mark failed
        apiFailUpdate($rechargeID, $statusMsg, $userid, $netDebit);
        exit;
    }
}

function apiFailUpdate($rechargeID, $reason, $userid, $amount) {
    global $ahk_conn;

    mysqli_begin_transaction($ahk_conn);
    try {
        // mark failed
        mysqli_query($ahk_conn, "
            UPDATE recharge_history 
            SET status='Failed', msg='$reason'
            WHERE id=$rechargeID
        ");

        // refund wallet
        mysqli_query($ahk_conn, "
            UPDATE users SET balance = balance + $amount WHERE phone='$userid'
        ");

        mysqli_commit($ahk_conn);

    } catch (Exception $e) {
        mysqli_rollback($ahk_conn);
    }

    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        Swal.fire({icon:'error',title:'Recharge Failed',text:'$reason'})
        .then(()=>{window.location='mobileRecharge.php';});
    </script>";
    include('../template/ahkweb/footer.php');
    exit;
}

function echoError($msg) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <script>Swal.fire({icon:'error',title:'Error',text:'$msg'});</script>";
    include('../template/ahkweb/footer.php'); exit;
}

?>

<!-- Loader Overlay -->
<div id="loaderOverlay">
    <div class="loader">
        <div></div><div></div><div></div><div></div>
    </div>
    <div class="loader-text">Processing...</div>
</div>
<style>
    /* Full-page loader overlay */
#loaderOverlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: none; /* Hidden by default */
    align-items: center;
    justify-content: center;
    flex-direction: column;
    font-family: 'Poppins', sans-serif;
}

/* Spinner animation */
.loader {
    display: inline-block;
    position: relative;
    width: 80px; height: 80px;
}

.loader div {
    position: absolute;
    width: 16px; height: 16px;
    background: #007bff;
    border-radius: 50%;
    animation: loaderAnim 1.2s linear infinite;
}

.loader div:nth-child(1) { top: 0; left: 0; animation-delay: 0s; }
.loader div:nth-child(2) { top: 0; right: 0; animation-delay: 0.3s; }
.loader div:nth-child(3) { bottom: 0; right: 0; animation-delay: 0.6s; }
.loader div:nth-child(4) { bottom: 0; left: 0; animation-delay: 0.9s; }

@keyframes loaderAnim {
    0%, 100% { transform: scale(0); }
    50% { transform: scale(1); }
}

/* Loader text */
.loader-text {
    margin-top: 15px;
    color: #fff;
    font-size: 1.2rem;
    font-weight: 600;
}

</style>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Mobile Recharge</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="mobileRechargeHistory" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> All List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

<!-- ======= HTML: Left form + Right Plan Preview ======= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="" crossorigin="anonymous" />

<div class="container-fluid">
    <div class="row">
        <!-- Left: Form -->
        <div class="col-lg-6 col-md-8 mx-auto">
            <div class="card shadow mb-3">
                <div class="card-body">
                    <div class="alert alert-info fw-bold" role="alert">📱 Mobile Recharge (Instant Online Recharge)</div>
                    <form id="rf" action="" method="POST" class="row g-3">
                        <div class="col-12">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <input name="mobile" id="mobile" type="text" class="form-control" placeholder="Enter Mobile Number" maxlength="10" required>
                        </div>

                       <div class="col-12">
    <label for="operator" class="form-label">Select Operator</label>
    <select name="operator" id="operator" class="form-control" required>
        <option value="">-- Select Operator --</option>
        <?php foreach($_C as $i=>$o){ ?>
            <option value="<?php echo $i;?>">
                <?php echo htmlspecialchars($o['n']." ({$o['p']}%)");?>
            </option>
        <?php } ?>
    </select>
</div>


                        <div class="col-12">
                            <label for="circle" class="form-label">Circle</label>
                            <input list="circleList" id="circle" name="circle" class="form-control" placeholder="Select or type circle">
                            <datalist id="circleList">
                                <option value="" selected disabled>Select Circle</option>
                                <option value="Andhra Pradesh & Telangana">Andhra Pradesh & Telangana</option>
                                <option value="Assam">Assam</option>
                                <option value="Bihar & Jharkhand">Bihar & Jharkhand</option>
                                <option value="Delhi NCR">Delhi NCR</option>
                                <option value="Gujarat">Gujarat</option>
                                <option value="Haryana">Haryana</option>
                                <option value="Himanchal Pradesh">Himanchal Pradesh</option>
                                <option value="Jammu & Kashmir">Jammu & Kashmir</option>
                                <option value="Karnataka">Karnataka</option>
                                <option value="Kerala">Kerala</option>
                                <option value="Kolkata">Kolkata</option>
                                <option value="Madhya Pradesh & Chattisgarh">Madhya Pradesh & Chattisgarh</option>
                                <option value="Maharashtra & Goa">Maharashtra & Goa</option>
                                <option value="Mumbai">Mumbai</option>
                                <option value="North East">North East</option>
                                <option value="Odisha">Odisha</option>
                                <option value="Punjab">Punjab</option>
                                <option value="Rajasthan">Rajasthan</option>
                                <option value="Tamil Nadu">Tamil Nadu</option>
                                <option value="Uttar Pradesh (East)">Uttar Pradesh (East)</option>
                                <option value="Uttar Pradesh (West) & Uttarakhand">Uttar Pradesh (West) & Uttarakhand</option>
                                <option value="West Bengal">West Bengal</option>
                                <option value="Chennai">Chennai</option>
                                <option value="Other">Other</option>
                            </datalist>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Amount / Plans</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input name="amount" id="amount" type="number" class="form-control" style="flex:0 0 70%" placeholder="Enter Amount" required>
                                <button type="button" id="clearSelection" class="btn btn-outline-secondary" style="flex:0 0 30%">Clear</button>
                            </div>
                        </div>

                        <div class="col-12 text-center">
                            <button type="button" class="btn btn-primary" onclick="cR()"><i class="fa fa-check-circle"></i> Recharge Now</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Live Plan Preview -->
        <div class="col-lg-6 col-md-8 mx-auto">
            <div class="card shadow mb-3">
                <div class="card-body">
                    <div class="alert alert-secondary fw-bold" role="alert">🔎 Plan Preview</div>
                    <div id="planStatus" class="mb-2 text-muted">Enter 10-digit mobile number to auto-fetch operator & plans.</div>
                    <div id="plansContainer" style="max-height:520px; overflow:auto;">
                        <!-- Plans will be injected here -->
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- row -->
</div></div></div></div>


<script src="https://<?= htmlspecialchars($flexsecureapiurl) ?>/api/v1/mobileRecharge/planNotice.js"></script>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const mobileInput = document.getElementById('mobile');
const operatorSelect = document.getElementById('operator');
const circleInput = document.getElementById('circle');
const amountInput = document.getElementById('amount');
const plansContainer = document.getElementById('plansContainer');
const planStatus = document.getElementById('planStatus');
const clearBtn = document.getElementById('clearSelection');

let lastPlans = null;
let selectedPlan = null;
let debounceTimer = null;

// ============================
// SHOW / HIDE LOADER
// ============================
function showLoader(text = "Processing...") {
    const overlay = document.getElementById('loaderOverlay');
    if (overlay) {
        overlay.style.display = "flex";
        overlay.querySelector('.loader-text').innerText = text;
    }
}

function hideLoader() {
    const overlay = document.getElementById('loaderOverlay');
    if (overlay) overlay.style.display = "none";
}

// ============================
// CONFIRM RECHARGE
// ============================
function cR() {
    Swal.fire({
        title: 'Are you sure?',
        text: "Do you want to proceed with this recharge?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Recharge Now!'
    }).then(r => {
        if (r.isConfirmed) {
            showLoader("Processing Recharge...");
            document.getElementById('rf').submit();
        }
    });
}

// ============================
// MOBILE INPUT LISTENER
// ============================
mobileInput.addEventListener('input', function () {
    const mob = this.value.replace(/\D/g, '');
    this.value = mob;

    selectedPlan = null;
    highlightSelected(null);

    if (debounceTimer) clearTimeout(debounceTimer);

    if (mob.length === 10) {
        planStatus.innerHTML = "Fetching operator & plans...";
        debounceTimer = setTimeout(() => fetchPlans(mob), 300);
    } else {
        planStatus.innerHTML = "Enter 10-digit mobile number to auto-fetch operator & plans.";
        lastPlans = null;
        plansContainer.innerHTML = "";
    }
});

// ============================
// AUTO SELECT OPERATOR
// ============================
function autoSelectOperator(apiOperator) {
    if (!apiOperator) return;

    let select = operatorSelect;
    let opLower = apiOperator.toLowerCase();
    let matchedOption = null;

    for (let i = 0; i < select.options.length; i++) {
        let text = select.options[i].text.toLowerCase();

        if (
            text.includes(opLower) ||
            opLower.includes(text) ||
            text.replace(/\s+/g, '') === opLower.replace(/\s+/g, '')
        ) {
            matchedOption = select.options[i].value;
            break;
        }
    }

    if (matchedOption) select.value = matchedOption;
}

// ============================
// FETCH PLANS
// ============================
function fetchPlans(mobile) {
    showLoader("Fetching plans...");

    fetch('?action=fetch_plan&mobile=' + encodeURIComponent(mobile))
    .then(async res => {
        hideLoader(); // Hide loader when response arrives
        let text = await res.text();

        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            planStatus.innerHTML = "❌ Server returned invalid JSON";
            plansContainer.innerHTML = `<div class="text-danger"><b>Invalid response:</b><br><pre style="white-space:pre-wrap;background:#f8d7da;padding:10px;border-radius:6px;">${escapeHtml(text)}</pre></div>`;
            return;
        }

        if (data.Message !== "Success") {
            planStatus.innerHTML = "❌ " + (data.error || "Unknown error");
            plansContainer.innerHTML = `<div class="text-danger">${escapeHtml(data.error)}</div>`;
            return;
        }

        autoSelectOperator(data.operator);
        if (data.circle) circleInput.value = data.circle;

        planStatus.innerHTML = `✔ Operator: <b>${data.operator}</b> — Circle: <b>${data.circle}</b>`;

        if (Array.isArray(data.data) && data.data.length > 0) {
            lastPlans = data.data;
            renderPlans(lastPlans);
        } else {
            plansContainer.innerHTML = `<div class="text-warning">No plans available.</div>`;
            lastPlans = null;
        }
    })
    .catch(err => {
        hideLoader();
        planStatus.innerHTML = "❌ Network error.";
        plansContainer.innerHTML = `<div class="text-danger">Failed to fetch plans.</div>`;
        console.error(err);
    });
}

// ============================
// RENDER PLAN LIST
// ============================
function renderPlans(categories) {
    let html = '';

    categories.forEach((cat, ci) => {
        html += `<div class="mb-3">
            <h6 style="background:#343a40;color:#fff;padding:8px;border-radius:6px;margin-bottom:8px;font-size:15px">
                ${escapeHtml(cat.fullName)}
            </h6>
            <div class="list-group">`;

        cat.plans.forEach((p, pi) => {
            const planId = `plan-${ci}-${pi}`;
            html += `<a href="javascript:void(0)" class="list-group-item list-group-item-action plan-item"
                id="${planId}"
                data-amount="${escapeHtml(p.amount)}"
                data-benefit="${escapeHtml(p.benefit)}"
                onclick="selectPlan('${planId}')">

                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">₹${escapeHtml(p.amount)}</h6>
                    <small>${escapeHtml(p.validity)}</small>
                </div>

                <p class="mb-1" style="font-size:13px">${escapeHtml(p.benefit)}</p>
                <small class="text-muted">${escapeHtml(p.talktime)}</small>
            </a>`;
        });

        html += `</div></div>`;
    });

    plansContainer.innerHTML = html;
    plansContainer.scrollTop = 0;
}

// ============================
// SELECT PLAN
// ============================
function selectPlan(planId) {
    let el = document.getElementById(planId);
    if (!el) return;

    amountInput.value = el.getAttribute("data-amount");
    selectedPlan = planId;
    highlightSelected(planId);

    window.scrollTo({ top: 0, behavior: "smooth" });
}

// Highlight selected plan
function highlightSelected(planId) {
    document.querySelectorAll(".plan-item").forEach(it => {
        if (it.id === planId) {
            it.style.background = "#e6f7ff";
            it.style.borderLeft = "4px solid #007bff";
        } else {
            it.style.background = "";
            it.style.borderLeft = "";
        }
    });
}

// Clear selection
clearBtn.addEventListener('click', () => {
    amountInput.value = "";
    selectedPlan = null;
    highlightSelected(null);
});

// Escape HTML
function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>




<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<?php include('../template/ahkweb/footer.php');?>
