<?php
// ==========================================
// CONFIGURATION & ERROR HANDLING
// ==========================================
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start(); // Capture any accidental output

session_start();

// API KEYS (Use your working keys)
define('API_KEY', '197a45-da10fa-09915a-bf4df6-3e6754');
define('SECRET_KEY', '1c3ce12454f5309');
define('API_URL', 'https://apiadda.in/api_service_v5/epic_mob_link.php');

// ==========================================
// API CALL FUNCTION
// ==========================================
function callApiAdda($params) {
    $url = API_URL . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['status' => 'error', 'message' => 'Connection Error: ' . $curlError];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => 'error', 'message' => 'Invalid API Response. Raw: ' . substr($response, 0, 100)];
    }
    return $data;
}

// ==========================================
// HANDLE AJAX REQUESTS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ob_clean(); // Clear any accidental output before JSON
    
    $action = $_POST['action'] ?? '';

    if ($action === 'initiate') {
        $epic   = trim($_POST['epic'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $aadhar = trim($_POST['aadhar'] ?? '');
        $name   = trim($_POST['name'] ?? '');

        if (empty($epic) || empty($mobile) || empty($aadhar) || empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Sabhi fields zaroori hain.']);
            exit;
        }
        if (!preg_match('/^\d{10}$/', $mobile)) {
            echo json_encode(['status' => 'error', 'message' => 'Mobile number must be 10 digits.']);
            exit;
        }
        if (!preg_match('/^\d{12}$/', $aadhar)) {
            echo json_encode(['status' => 'error', 'message' => 'Aadhaar number must be 12 digits.']);
            exit;
        }

        $params = [
            'action'       => 'initiate',
            'api_key'      => API_KEY,
            'secret_key'   => SECRET_KEY,
            'epic'         => $epic,
            'mobile'       => $mobile,
            'aadhar'       => $aadhar,
            'aadhaar_name' => $name
        ];

        $result = callApiAdda($params);

        if (isset($result['status_code']) && $result['status_code'] == 100) {
            $_SESSION['uniq_id'] = $result['uniq_apiadda_id'] ?? '';
            echo json_encode([
                'status' => 'success',
                'message' => $result['message'] ?? 'OTP sent successfully.',
                'next' => 'otp'
            ]);
        } else {
            $errorMsg = $result['message'] ?? 'Linking failed. Please check details.';
            echo json_encode(['status' => 'error', 'message' => $errorMsg]);
        }
        exit;
    }

    if ($action === 'verify') {
        $otp = trim($_POST['otp'] ?? '');

        if (empty($otp)) {
            echo json_encode(['status' => 'error', 'message' => 'OTP zaroori hai.']);
            exit;
        }
        if (empty($_SESSION['uniq_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Session expire ho gaya. Dobara start karein.']);
            exit;
        }

        $params = [
            'action'          => 'verify',
            'api_key'         => API_KEY,
            'secret_key'      => SECRET_KEY,
            'uniq_apiadda_id' => $_SESSION['uniq_id'],
            'otp'             => $otp
        ];

        $result = callApiAdda($params);

        if (isset($result['status_code']) && $result['status_code'] == 1000) {
            $finalMsg = $result['note'] ?? $result['message'] ?? 'Link Successful!';
            unset($_SESSION['uniq_id']);
            echo json_encode([
                'status' => 'success',
                'message' => $finalMsg,
                'final' => true
            ]);
        } else {
            $errorMsg = $result['message'] ?? 'OTP galat hai ya expire ho gaya.';
            echo json_encode(['status' => 'error', 'message' => $errorMsg]);
        }
        exit;
    }
}

// Reset session if requested
if (isset($_GET['reset'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Mobile Link | One Click API Live</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: linear-gradient(to right, #0f2027, #203a43, #2c5364);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .main-card {
            background: rgba(255,255,255,0.98);
            width: 100%;
            max-width: 480px;
            border-radius: 25px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            padding: 40px 20px 30px;
            text-align: center;
            color: white;
        }
        .card-header h1 {
            font-size: 1.6rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .card-header .subtitle {
            font-size: 0.9rem;
            opacity: 0.95;
        }
        .card-body {
            padding: 30px 25px;
        }
        .promo-text {
            text-align: center;
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 25px;
            line-height: 1.5;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 15px;
            border: 1px dashed #ccc;
        }
        .warning-box {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .alert-box {
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: none;
            align-items: center;
            gap: 10px;
        }
        .alert-box.show {
            display: flex;
        }
        .alert-box.success {
            background: #e6fffa;
            color: #00b894;
            border-left: 5px solid #00b894;
        }
        .alert-box.error {
            background: #ffeaea;
            color: #d63031;
            border-left: 5px solid #d63031;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #444;
            margin-bottom: 8px;
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 38px;
            color: #aaa;
            font-size: 1.1rem;
        }
        input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #fdfdfd;
        }
        input:focus {
            outline: none;
            border-color: #11998e;
            box-shadow: 0 0 0 4px rgba(17,153,142,0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        .btn-primary {
            background: linear-gradient(to right, #11998e, #38ef7d);
            color: white;
            box-shadow: 0 4px 15px rgba(17,153,142,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #dfe6e9;
            color: #636e72;
            margin-top: 15px;
        }
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .loader {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            display: none;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .footer {
            background: #f1f2f6;
            text-align: center;
            padding: 15px;
            font-size: 0.75rem;
            color: #7f8c8d;
            border-top: 1px solid #ddd;
        }
        #otpSection {
            display: none;
        }
    </style>
</head>
<body>
<div class="main-card">
    <div class="card-header">
        <h1><i class="fas fa-vote-yea"></i> Voter Mobile Link</h1>
        <p class="subtitle">ONE CLICK API LIVE! 🚀</p>
    </div>

    <div class="card-body">
        <!-- Message box -->
        <div id="msgBox" class="alert-box"></div>

        <!-- Step 1: Input Details -->
        <div id="detailsSection">
            <div class="promo-text">
                📢 अब किसी भी Voter में मोबाइल नंबर तुरंत लिंक करें – एक क्लिक API के साथ! ✅<br>
                <strong>👉 बस अपने Aadhaar का नाम और नंबर डालें</strong>
            </div>
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i> <strong>⚠️ ध्यान दें:</strong> अगर Aadhaar और Voter ID में नाम अलग है, तो Aadhaar वाला नाम ही डालें।
            </div>

            <form id="initForm">
                <div class="form-group">
                    <label>Voter ID (EPIC No.)</label>
                    <i class="fas fa-id-card input-icon"></i>
                    <input type="text" id="epic" placeholder="Example: YIC1454628" required>
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <i class="fas fa-mobile-alt input-icon"></i>
                    <input type="tel" id="mobile" placeholder="10 Digit Mobile" maxlength="10" required>
                </div>
                <div class="form-group">
                    <label>Aadhaar Number</label>
                    <i class="fas fa-fingerprint input-icon"></i>
                    <input type="text" id="aadhar" placeholder="12 Digit Aadhaar" maxlength="12" required>
                </div>
                <div class="form-group">
                    <label>Name (As per Aadhaar)</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="name" placeholder="Exact Name on Aadhaar" required>
                </div>
                <button type="submit" class="btn btn-primary" id="btnInit">
                    <span class="txt">Initiate Linking</span>
                    <div class="loader"></div>
                </button>
            </form>
        </div>

        <!-- Step 2: OTP Verify -->
        <div id="otpSection">
            <div style="text-align: center; margin-bottom: 25px;">
                <i class="fas fa-envelope-open-text" style="font-size: 3rem; color: #11998e; margin-bottom: 10px;"></i>
                <p style="color: #666; font-size: 0.9rem;">OTP sent to Aadhaar linked mobile.</p>
            </div>
            <form id="otpForm">
                <div class="form-group">
                    <label>Enter OTP</label>
                    <i class="fas fa-key input-icon"></i>
                    <input type="text" id="otp" placeholder="6 Digit OTP" maxlength="6" style="letter-spacing: 4px; text-align: center; font-size: 1.2rem; font-weight: bold;" required>
                </div>
                <button type="submit" class="btn btn-primary" id="btnVerify">
                    <span class="txt">Verify & Link</span>
                    <div class="loader"></div>
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='?reset=1'">Start Over</button>
            </form>
        </div>
    </div>

    <div class="footer">
        🚀 Fast | Easy | Instant<br>
        💻 Powered by PARI PRINT | Developed by LALA WEB DEVELOPER
    </div>
</div>

<script>
    // Helper: Show message in the box
    function showMessage(msg, type) {
        const box = document.getElementById('msgBox');
        box.innerHTML = (type === 'success' ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> ') + msg;
        box.className = `alert-box show ${type}`;
        console.log(`[${type}] ${msg}`); // Debug log
    }

    function clearMessage() {
        const box = document.getElementById('msgBox');
        box.className = 'alert-box';
        box.innerHTML = '';
    }

    function setLoading(btnId, loading) {
        const btn = document.getElementById(btnId);
        const txt = btn.querySelector('.txt');
        const loader = btn.querySelector('.loader');
        if (loading) {
            btn.disabled = true;
            txt.style.display = 'none';
            loader.style.display = 'block';
        } else {
            btn.disabled = false;
            txt.style.display = 'block';
            loader.style.display = 'none';
        }
    }

    // Step 1: Initiate
    document.getElementById('initForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage();
        setLoading('btnInit', true);

        const formData = new FormData();
        formData.append('action', 'initiate');
        formData.append('epic', document.getElementById('epic').value.trim());
        formData.append('mobile', document.getElementById('mobile').value.trim());
        formData.append('aadhar', document.getElementById('aadhar').value.trim());
        formData.append('name', document.getElementById('name').value.trim());

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            setLoading('btnInit', false);

            if (data.status === 'success') {
                showMessage(data.message, 'success');
                // Switch to OTP section after 1.5 seconds
                setTimeout(() => {
                    document.getElementById('detailsSection').style.display = 'none';
                    document.getElementById('otpSection').style.display = 'block';
                    // Keep the success message visible
                }, 1500);
            } else {
                showMessage(data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            setLoading('btnInit', false);
            showMessage('Network error: ' + err.message, 'error');
        }
    });

    // Step 2: Verify OTP
    document.getElementById('otpForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage();
        setLoading('btnVerify', true);

        const formData = new FormData();
        formData.append('action', 'verify');
        formData.append('otp', document.getElementById('otp').value.trim());

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            setLoading('btnVerify', false);

            if (data.status === 'success') {
                showMessage(data.message, 'success');
                // Optionally disable input and button
                document.getElementById('otp').disabled = true;
                document.getElementById('btnVerify').disabled = true;
            } else {
                showMessage(data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            setLoading('btnVerify', false);
            showMessage('Network error: ' + err.message, 'error');
        }
    });
</script>
</body>
</html>