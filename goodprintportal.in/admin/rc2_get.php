<?php
ob_start();
include('../includes/session.php');
include('../includes/config.php');

// ═══════════════════════════════════════════════════
// API CONFIG
// ═══════════════════════════════════════════════════
$new_api_base   = 'https://apiadda.in/RC_DATA/RCPDF';
$new_api_key    = 'e5742e-4c57fc-d1c4ac-0390b5-8cbd0e';
$new_api_secret = 'e06450a0867d62c';

$price_row = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='rc_vehical_fee'"));
$paymode = (float)($price_row['price'] ?? 0);

// ═══════════════════════════════════════════════════
// AJAX: CAPTCHA
// ═══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_captcha') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $ch = curl_init($new_api_base . '/captcha_api.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) { echo json_encode(['status'=>'error','message'=>'cURL: '.$err]); exit; }
    if ($code !== 200) { echo json_encode(['status'=>'error','message'=>'HTTP '.$code]); exit; }

    $data = json_decode($resp, true);
    if (!$data) { echo json_encode(['status'=>'error','message'=>'Invalid JSON from API']); exit; }

    if (isset($data['status']) && $data['status'] === 'success') {
        echo json_encode([
            'status'     => 'success',
            'svg'        => $data['captcha_svg'] ?? '',
            'session_id' => $data['session_id'] ?? '',
            'user_sid'   => $data['user_sid'] ?? ''
        ]);
    } else {
        echo json_encode(['status'=>'error','message'=>$data['message'] ?? 'Captcha failed']);
    }
    exit;
}

// ═══════════════════════════════════════════════════
// AJAX: FETCH RC
// ═══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fetch_rc') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $rc_no   = strtoupper(trim(mysqli_real_escape_string($ahk_conn, $_POST['rc_no'] ?? '')));
    $captcha = trim(mysqli_real_escape_string($ahk_conn, $_POST['captcha'] ?? ''));
    $sid     = trim(mysqli_real_escape_string($ahk_conn, $_POST['session_id'] ?? ''));
    $usid    = trim(mysqli_real_escape_string($ahk_conn, $_POST['user_sid'] ?? ''));
    $ctype   = trim(mysqli_real_escape_string($ahk_conn, $_POST['card_type'] ?? 'non chip'));

    if (!$rc_no || !$captcha || !$sid || !$usid) {
        echo json_encode(['Status'=>'Failed','Message'=>'Missing required fields']);
        exit;
    }

    $url = $new_api_base . '/rc_pdf_api.php?' . http_build_query([
        'rc_no'      => $rc_no,
        'captcha'    => $captcha,
        'session_id' => $sid,
        'user_sid'   => $usid,
        'api_key'    => $new_api_key,
        'secret_key' => $new_api_secret,
        'card_type'  => $ctype,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) { echo json_encode(['Status'=>'Failed','Message'=>'API Error: '.$err]); exit; }
    if ($code !== 200) { echo json_encode(['Status'=>'Failed','Message'=>'HTTP '.$code]); exit; }

    $result = json_decode($resp, true);
    if (!$result) { echo json_encode(['Status'=>'Failed','Message'=>'Invalid API response']); exit; }

    if (isset($result['status']) && $result['status'] === 'success' && !empty($result['pdf_base64'])) {

        $_SESSION['temp_rc_pdf'] = $result['pdf_base64'];
        $_SESSION['temp_rc_no']  = $rc_no;

        $rc = $result['rc_data'] ?? [];

        $mappedData = [
            'rc_number'                    => $rc['rc_number'] ?? $rc_no,
            'owner_name'                   => $rc['owner_name'] ?? '',
            'father_name'                  => $rc['father_name'] ?? '',
            'vehicle_chasi_number'         => $rc['chassis_no'] ?? '',
            'vehicle_engine_number'        => $rc['engine_no'] ?? '',
            'registration_date'            => $rc['registration_date'] ?? '',
            'fuel_type'                    => $rc['fuel_type'] ?? '',
            'norms_type'                   => $rc['emission_norms'] ?? '',
            'vehicle_category_description' => $rc['vehicle_class'] ?? '',
            'vehicle_category'             => $rc['vehicle_class'] ?? '',
            'maker_description'            => $rc['maker'] ?? '',
            'maker_model'                  => $rc['model'] ?? '',
            'color'                        => $rc['color'] ?? '',
            'body_type'                    => $rc['body_type'] ?? '',
            'seat_capacity'                => $rc['seating_capacity'] ?? '',
            'unladen_weight'               => $rc['unladen_weight'] ?? '',
            'cubic_capacity'               => $rc['cubic_capacity'] ?? '',
            'no_cylinders'                 => $rc['no_cylinders'] ?? '',
            'wheelbase'                    => $rc['wheelbase'] ?? '',
            'manufacturing_date_formatted' => $rc['mfg_date'] ?? '',
            'manufacturing_date'           => $rc['mfg_date'] ?? '',
            'fit_up_to'                    => $rc['fitness_upto'] ?? '',
            'owner_number'                 => $rc['ownership'] ?? '',
            'registered_at'                => $rc['rto_authority'] ?? '',
            'present_address'              => $rc['address'] ?? '',
            'permanent_address'            => $rc['address'] ?? ''
        ];

        echo json_encode([
            'Status'    => 'Success',
            'Message'   => 'RC Details fetched — verify before download',
            'data'      => $mappedData,
            'charged'   => $result['charged'] ?? 0,
            'remaining' => $result['remaining_balance'] ?? 0
        ]);

    } else {
        echo json_encode([
            'Status'  => 'Failed',
            'Message' => $result['message'] ?? 'Vehicle not found or invalid captcha'
        ]);
    }
    exit;
}

// ═══════════════════════════════════════════════════
// AJAX: GENERATE PDF with EDITED DATA
// ═══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_pdf') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $rc_no = strtoupper(trim(mysqli_real_escape_string($ahk_conn, $_POST['rc_no'] ?? '')));
    $card_type = trim(mysqli_real_escape_string($ahk_conn, $_POST['card_type'] ?? 'non chip'));
    
    // 🔥 IMPORTANT: Get EDITED data from frontend (user modifications)
    $editedData = [];
    if (isset($_POST['rc_data']) && is_array($_POST['rc_data'])) {
        $editedData = $_POST['rc_data'];
    }
    
    $pdf_base64 = $_SESSION['temp_rc_pdf'] ?? '';
    unset($_SESSION['temp_rc_pdf'], $_SESSION['temp_rc_no']);

    if (empty($pdf_base64)) {
        echo json_encode(['status'=>'error','message'=>'PDF session expired. Please fetch again.']);
        exit;
    }

    $username = $udata['phone'];
    $wallet_amt = (float)$udata['balance'];
    $debit_fee = $wallet_amt - $paymode;

    if ($wallet_amt < $paymode) {
        echo json_encode(['status'=>'error','message'=>'Wallet Balance Insufficient! Required: ₹'.$paymode]);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 🔥 CRITICAL: Prepare PDF data with USER EDITS
    // ─────────────────────────────────────────────────────────
    $pdfData = [
        'type' => $card_type,
        'vrn' => $editedData['rc_number'] ?? $rc_no,
        'state_code' => substr(($editedData['rc_number'] ?? $rc_no), 0, 2),
        'state' => '',
        'authority' => $editedData['registered_at'] ?? '',
        'owner_name' => $editedData['owner_name'] ?? '',
        'owner_father_name' => str_replace('S/O- ', '', $editedData['father_name'] ?? ''),
        'chassis' => $editedData['vehicle_chasi_number'] ?? '',
        'engine' => $editedData['vehicle_engine_number'] ?? '',
        'fule_type' => $editedData['fuel_type'] ?? '',
        'emission_norms' => $editedData['norms_type'] ?? '',
        'Address' => !empty($editedData['present_address']) ? $editedData['present_address'] : ($editedData['permanent_address'] ?? ''),
        'class' => $editedData['vehicle_category_description'] ?? ($editedData['vehicle_category'] ?? ''),
        'maker' => $editedData['maker_description'] ?? '',
        'model' => $editedData['maker_model'] ?? '',
        'color' => $editedData['color'] ?? '',
        'body_type' => $editedData['body_type'] ?? '',
        'seating_capacity' => $editedData['seat_capacity'] ?? '',
        'unladen_weight' => $editedData['unladen_weight'] ?? '',
        'cubic_cap' => $editedData['cubic_capacity'] ?? '',
        'cylinders' => $editedData['no_cylinders'] ?? '',
        'wheel_base' => $editedData['wheelbase'] ?? '',
        'mfg_month_year' => $editedData['manufacturing_date_formatted'] ?? ($editedData['manufacturing_date'] ?? ''),
        'reg_date' => $editedData['registration_date'] ?? '',
        'reg_valid_date' => $editedData['fit_up_to'] ?? '',
        'ownership' => $editedData['owner_number'] ?? ''
    ];

    // Call PDF generation API with edited data
    $jsonData = json_encode($pdfData);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://codenaxus.xyz/rc-api/index.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POSTREDIR => 3,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData),
            'Accept: application/json'
        ),
    ));
    
    $pdfResponse = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) { 
        echo json_encode(['status'=>'error', 'message'=>'PDF API Error: ' . $err]); 
        exit();
    }
    
    $pdfResponse = trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $pdfResponse));
    $jsonPdf = json_decode($pdfResponse, true);
    
    if (isset($jsonPdf['status']) && $jsonPdf['status'] === 'success') {
        $base64Data = $jsonPdf['pdf'];
        
        // Deduct wallet
        mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $paymode WHERE phone = '$username'");
        
        date_default_timezone_set("Asia/Kolkata");
        $time_hkb = date('d/m/Y g:i:s');
        
        // Store edited data in database
        $edited_json = json_encode($editedData);
        mysqli_query($ahk_conn, "INSERT INTO `rc_vehical`(`username`, `rc_vehical_no`, `status`, `pdf`, `date`, `edited_details`)
                                 VALUES ('$username', '$rc_no', 'PDF Generated', '$base64Data', '$time_hkb', '$edited_json')");
        
        mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`)
                                 VALUES ('$username', '$paymode', (SELECT balance FROM users WHERE phone='$username'), 'RC Vehicle PDF', '1', 'Debit')");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'RC PDF Generated Successfully!',
            'pdf' => $base64Data
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => $jsonPdf['message'] ?? 'Failed to generate PDF'
        ]);
    }
    exit;
}

ob_end_flush();
include('../template/ahkweb/header.php');
?>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap');

  :root {
    --rc-primary:#0057FF; --rc-accent:#00D1FF; --rc-success:#00C48C;
    --rc-danger:#FF4757; --rc-bg:#F0F4FF; --rc-card:#FFFFFF;
    --rc-border:#D6E0FF; --rc-text:#0A1433; --rc-muted:#6B7A9E;
    --rc-label:#8A97BB; --rc-shadow:0 4px 24px rgba(0,87,255,0.10);
    --rc-shadow-lg:0 12px 48px rgba(0,87,255,0.15);
  }
  .rc-wrapper{font-family:'DM Sans',sans-serif;background:var(--rc-bg);min-height:100vh;padding:32px 24px 60px}
  .rc-page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px}
  .rc-page-title{font-family:'Rajdhani',sans-serif;font-size:30px;font-weight:700;color:var(--rc-text);margin:0}
  .rc-page-title span{color:var(--rc-primary)}
  .rc-badge-price{background:linear-gradient(135deg,var(--rc-primary),var(--rc-accent));color:#fff;font-family:'Rajdhani',sans-serif;font-size:15px;font-weight:700;padding:7px 18px;border-radius:50px}
  .rc-brand-tag{font-size:11px;color:var(--rc-muted);font-weight:500;margin-top:2px}
  .rc-main-card{background:var(--rc-card);border-radius:20px;box-shadow:var(--rc-shadow);overflow:hidden;border:1px solid var(--rc-border)}
  .rc-card-strip{background:linear-gradient(90deg,var(--rc-primary) 0%,#1A5CFF 60%,var(--rc-accent) 100%);height:5px}
  .rc-card-body{padding:32px 32px 28px}
  .rc-steps{display:flex;align-items:center;margin-bottom:30px}
  .rc-step{display:flex;align-items:center;gap:8px}
  .rc-step-dot{width:32px;height:32px;border-radius:50%;background:var(--rc-border);color:var(--rc-muted);font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;font-family:'Rajdhani',sans-serif;transition:all .3s;flex-shrink:0}
  .rc-step-dot.active{background:var(--rc-primary);color:#fff;box-shadow:0 0 0 4px rgba(0,87,255,0.15)}
  .rc-step-dot.done{background:var(--rc-success);color:#fff}
  .rc-step-label{font-size:12px;font-weight:500;color:var(--rc-muted);white-space:nowrap}
  .rc-step-label.active{color:var(--rc-primary);font-weight:600}
  .rc-step-line{flex:1;height:2px;background:var(--rc-border);margin:0 10px;min-width:24px}
  .rc-form-grid{display:grid;grid-template-columns:1fr 1fr 1.5fr auto;gap:16px;align-items:end}
  @media(max-width:900px){.rc-form-grid{grid-template-columns:1fr 1fr}}
  @media(max-width:580px){.rc-form-grid{grid-template-columns:1fr}}
  .rc-field{display:flex;flex-direction:column;gap:6px}
  .rc-field label{font-size:11px;font-weight:600;letter-spacing:.7px;text-transform:uppercase;color:var(--rc-label)}
  .rc-field input,.rc-field select{height:46px;border:1.5px solid var(--rc-border);border-radius:10px;padding:0 14px;font-size:14px;font-family:'DM Sans',sans-serif;color:var(--rc-text);background:#F7F9FF;outline:none;transition:border-color .2s,box-shadow .2s;width:100%;box-sizing:border-box}
  .rc-field input:focus,.rc-field select:focus{border-color:var(--rc-primary);background:#fff;box-shadow:0 0 0 4px rgba(0,87,255,0.08)}
  .rc-captcha-row{display:flex;gap:8px;align-items:center}
  .rc-captcha-box{flex:1;height:46px;border:1.5px solid var(--rc-border);border-radius:10px;padding:0 14px;font-size:14px;font-family:'DM Sans',sans-serif;color:var(--rc-text);background:#F7F9FF;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box}
  .rc-captcha-box:focus{border-color:var(--rc-primary);background:#fff;box-shadow:0 0 0 4px rgba(0,87,255,0.08)}
  .rc-captcha-svg-wrap{height:46px;min-width:120px;background:#EDF0FF;border:1.5px solid var(--rc-border);border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:4px 8px}
  .rc-captcha-svg-wrap svg{max-height:36px;display:block}
  .rc-refresh-btn{width:46px;height:46px;border-radius:10px;border:1.5px solid var(--rc-border);background:#F7F9FF;color:var(--rc-primary);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
  .rc-refresh-btn:hover{background:var(--rc-primary);color:#fff;border-color:var(--rc-primary)}
  .rc-refresh-btn.spinning{animation:spin .6s linear}
  @keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
  .rc-fetch-btn{height:46px;padding:0 28px;background:linear-gradient(135deg,var(--rc-primary),#3B7BFF);color:#fff;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:16px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;white-space:nowrap;transition:all .2s;box-shadow:0 4px 14px rgba(0,87,255,0.3)}
  .rc-fetch-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,87,255,0.4)}
  .rc-fetch-btn:disabled{opacity:.65;cursor:not-allowed;transform:none}
  .rc-info-bar{display:flex;align-items:center;gap:20px;background:#F0F4FF;border-radius:10px;padding:12px 18px;margin-top:20px;flex-wrap:wrap}
  .rc-info-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--rc-muted);font-weight:500}
  .rc-info-item .dot{width:7px;height:7px;border-radius:50%;background:var(--rc-success);display:inline-block}
  .rc-info-item .dot-warn{background:#FFA234}
  .rc-toast{position:fixed;bottom:28px;right:28px;min-width:280px;max-width:380px;background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,0.14);padding:16px 20px;display:flex;align-items:flex-start;gap:12px;z-index:9999;transform:translateY(80px);opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);border-left:4px solid var(--rc-primary)}
  .rc-toast.show{transform:translateY(0);opacity:1}
  .rc-toast.rc-toast-error{border-left-color:var(--rc-danger)}
  .rc-toast.rc-toast-success{border-left-color:var(--rc-success)}
  .rc-toast-icon{font-size:22px;flex-shrink:0}
  .rc-toast-title{font-size:13px;font-weight:700;color:var(--rc-text);margin-bottom:2px}
  .rc-toast-msg{font-size:12px;color:var(--rc-muted);line-height:1.4}
  .rc-loading-overlay{position:fixed;inset:0;background:rgba(10,20,51,0.55);z-index:9998;display:none;align-items:center;justify-content:center;backdrop-filter:blur(3px)}
  .rc-loading-overlay.show{display:flex}
  .rc-loader-box{background:#fff;border-radius:20px;padding:36px 44px;text-align:center;box-shadow:var(--rc-shadow-lg)}
  .rc-loader-spinner{width:52px;height:52px;border:4px solid var(--rc-border);border-top-color:var(--rc-primary);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px}
  .rc-loader-text{font-family:'Rajdhani',sans-serif;font-size:17px;font-weight:600;color:var(--rc-text)}
  .rc-loader-sub{font-size:12px;color:var(--rc-muted);margin-top:4px}
  #rcDetailsModal .modal-dialog{max-width:950px}
  #rcDetailsModal .modal-content{border:none;border-radius:20px;overflow:hidden;box-shadow:var(--rc-shadow-lg);font-family:'DM Sans',sans-serif}
  .rc-modal-header{background:linear-gradient(135deg,var(--rc-primary),#2B6AFF);padding:18px 28px;display:flex;align-items:center;justify-content:space-between}
  .rc-modal-title{font-family:'Rajdhani',sans-serif;font-size:20px;font-weight:700;color:#fff;margin:0;display:flex;align-items:center;gap:10px}
  .edit-badge{background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:50px;font-size:11px;font-weight:500}
  .rc-modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;line-height:1}
  .rc-modal-close:hover{background:rgba(255,255,255,0.3)}
  .rc-modal-body{padding:24px 28px;max-height:65vh;overflow-y:auto}
  .rc-modal-body::-webkit-scrollbar{width:6px}
  .rc-modal-body::-webkit-scrollbar-thumb{background:var(--rc-border);border-radius:10px}
  .rc-section-title{font-family:'Rajdhani',sans-serif;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--rc-primary);padding:0 0 8px;border-bottom:2px solid var(--rc-border);margin:16px 0 14px 8px}
  .rc-modal-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:8px}
  @media(max-width:700px){.rc-modal-grid{grid-template-columns:repeat(2,1fr)}}
  .rc-modal-field{display:flex;flex-direction:column;gap:5px}
  .rc-modal-field label{font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--rc-label)}
  .rc-modal-field input,.rc-modal-field textarea{border:1.5px solid var(--rc-border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--rc-text);background:#FFF9E6;outline:none;transition:all .2s;width:100%;box-sizing:border-box}
  .rc-modal-field input:focus,.rc-modal-field textarea:focus{border-color:var(--rc-primary);background:#fff;box-shadow:0 0 0 3px rgba(0,87,255,0.08)}
  .rc-modal-field textarea{resize:vertical;min-height:60px}
  .rc-edit-note{background:#FFF8E1;border-left:3px solid #FFA234;padding:10px 15px;border-radius:8px;font-size:11px;color:#F57F17;margin-bottom:15px;display:flex;align-items:center;gap:8px}
  .rc-modal-footer{background:#F7F9FF;padding:16px 28px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--rc-border);gap:12px;flex-wrap:wrap}
  .rc-modal-note{font-size:12px;color:var(--rc-muted);display:flex;align-items:center;gap:6px}
  .rc-modal-note .warn-dot{width:8px;height:8px;border-radius:50%;background:#FFA234;flex-shrink:0}
  .rc-btn-cancel{padding:10px 20px;border-radius:9px;border:1.5px solid var(--rc-border);background:transparent;color:var(--rc-muted);font-size:14px;font-family:'DM Sans',sans-serif;font-weight:500;cursor:pointer;transition:all .2s}
  .rc-btn-cancel:hover{border-color:var(--rc-danger);color:var(--rc-danger)}
  .rc-btn-download{padding:10px 24px;border-radius:9px;border:none;background:linear-gradient(135deg,var(--rc-success),#00A875);color:#fff;font-size:14px;font-family:'Rajdhani',sans-serif;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;transition:all .2s;box-shadow:0 4px 14px rgba(0,196,140,0.3)}
  .rc-btn-download:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,196,140,0.4)}
  .rc-btn-download:disabled{opacity:.65;cursor:not-allowed;transform:none}
</style>

<div class="page-wrapper">
  <div class="page-content">
    <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
      <div class="d-flex align-items-center">
        <div class="breadcrumb-title pe-3">Services</div>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0 ps-2">
            <li class="breadcrumb-item active text-muted" aria-current="page">RC Card Blue Download</li>
          </ol>
        </nav>
      </div>
      <div class="btn-group">
        <a href="rc_get_list" class="btn btn-success"><i class="bi bi-list-ul me-1"></i> RC PDF List</a>
        <a href="javascript:history.back()" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i> Back</a>
      </div>
    </div>

    <div class="rc-wrapper">
      <div class="rc-page-header">
        <div>
          <h1 class="rc-page-title">RC Card <span>Blue Download</span></h1>
          <p class="rc-brand-tag">PARI PRINT • Captcha → Verify → Edit → Download 🚀</p>
        </div>
        <div class="rc-badge-price">₹<?php echo number_format($paymode,2); ?> per RC</div>
      </div>

      <div class="rc-main-card">
        <div class="rc-card-strip"></div>
        <div class="rc-card-body">

          <div class="rc-steps">
            <div class="rc-step">
              <div class="rc-step-dot active" id="step1dot">1</div>
              <span class="rc-step-label active" id="step1lbl">Enter Details</span>
            </div>
            <div class="rc-step-line"></div>
            <div class="rc-step">
              <div class="rc-step-dot" id="step2dot">2</div>
              <span class="rc-step-label" id="step2lbl">Verify & Edit</span>
            </div>
            <div class="rc-step-line"></div>
            <div class="rc-step">
              <div class="rc-step-dot" id="step3dot">3</div>
              <span class="rc-step-label" id="step3lbl">Download PDF</span>
            </div>
          </div>

          <form id="fetchRcForm" autocomplete="off">
            <div class="rc-form-grid">
              <div class="rc-field">
                <label>Vehicle Registration No.</label>
                <input type="text" id="vehicle_no" name="vehicle_no" placeholder="MH12AB1234" required oninput="this.value=this.value.toUpperCase().replace(/\s/g,'')">
              </div>
              <div class="rc-field">
                <label>Card Type</label>
                <select id="card_type" name="type" required>
                  <option value="non chip">Non Chip (Old)</option>
                  <option value="chip">Chip (New)</option>
                </select>
              </div>
              <div class="rc-field">
                <label>Captcha Verification</label>
                <div class="rc-captcha-row">
                  <input type="text" id="captcha_code" class="rc-captcha-box" placeholder="Enter captcha" required autocomplete="off">
                  <div class="rc-captcha-svg-wrap" id="captcha_svg_container">
                    <svg width="90" height="32" viewBox="0 0 90 32"><text x="8" y="22" fill="#8A97BB" font-size="13">Loading...</text></svg>
                  </div>
                  <button type="button" id="refreshCaptchaBtn" class="rc-refresh-btn" title="Refresh Captcha">↻</button>
                </div>
                <input type="hidden" id="session_id" value="">
                <input type="hidden" id="user_sid" value="">
              </div>
              <div class="rc-field" style="justify-content:flex-end;">
                <button type="submit" class="rc-fetch-btn" id="fetchBtn"><span>⚡</span> Fetch Details</button>
              </div>
            </div>
          </form>

          <div class="rc-info-bar">
            <div class="rc-info-item"><span class="dot"></span> PARI PRINT Connected</div>
            <div class="rc-info-item"><span class="dot"></span> Instant RC Fetch</div>
            <div class="rc-info-item"><span class="dot dot-warn"></span> ✎ Edit any field before download</div>
            <div class="rc-info-item" style="margin-left:auto;">
              Balance: <strong style="color:#0057FF;">₹<?php echo number_format($udata['balance'],2); ?></strong>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<div class="rc-loading-overlay" id="loadingOverlay">
  <div class="rc-loader-box">
    <div class="rc-loader-spinner"></div>
    <div class="rc-loader-text" id="loaderText">Processing...</div>
    <div class="rc-loader-sub" id="loaderSub">Please wait</div>
  </div>
</div>

<div class="rc-toast" id="rcToast">
  <div class="rc-toast-icon" id="toastIcon">ℹ️</div>
  <div>
    <div class="rc-toast-title" id="toastTitle">Notice</div>
    <div class="rc-toast-msg" id="toastMsg"></div>
  </div>
</div>

<!-- MODAL - EDITABLE FIELDS -->
<div class="modal fade" id="rcDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="rc-modal-header">
        <h5 class="rc-modal-title">📋 Verify & Edit RC Details <span class="edit-badge">✎ EDIT MODE</span></h5>
        <button type="button" class="rc-modal-close" data-bs-dismiss="modal">✕</button>
      </div>

      <div class="rc-modal-body">
        <div class="rc-edit-note">
          ✏️ <strong>Tip:</strong> आप किसी भी फील्ड को मैन्युअली एडिट कर सकते हैं — जो changes आप करेंगे वही PDF में आएगा!
        </div>

        <p class="rc-section-title">👤 Owner & Registration</p>
        <div class="rc-modal-grid">
          <div class="rc-modal-field"><label>RC Number</label><input type="text" id="m_rc_number" class="editable-field" readonly></div>
          <div class="rc-modal-field"><label>Owner Name</label><input type="text" id="m_owner_name" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Father Name</label><input type="text" id="m_father_name" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Chassis Number</label><input type="text" id="m_chassis" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Engine Number</label><input type="text" id="m_engine" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Registration Date</label><input type="text" id="m_reg_date" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
        </div>

        <p class="rc-section-title">🚗 Vehicle Specifications</p>
        <div class="rc-modal-grid">
          <div class="rc-modal-field"><label>Fuel Type</label><input type="text" id="m_fuel_type" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Emission Norms</label><input type="text" id="m_norms" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Class / Category</label><input type="text" id="m_class" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Maker</label><input type="text" id="m_maker" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Model</label><input type="text" id="m_model" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Color</label><input type="text" id="m_color" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Body Type</label><input type="text" id="m_body" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Seat Capacity</label><input type="text" id="m_seats" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Unladen Weight</label><input type="text" id="m_unladen" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Cubic Capacity</label><input type="text" id="m_cc" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Cylinders</label><input type="text" id="m_cylinders" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Wheelbase</label><input type="text" id="m_wheelbase" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
        </div>

        <p class="rc-section-title">📅 Validity & Authority</p>
        <div class="rc-modal-grid">
          <div class="rc-modal-field"><label>Mfg. Date</label><input type="text" id="m_mfg" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Fitness Upto</label><input type="text" id="m_fit_upto" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>Ownership No.</label><input type="text" id="m_ownership" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
          <div class="rc-modal-field"><label>RTO Authority</label><input type="text" id="m_rto" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></div>
        </div>

        <p class="rc-section-title">🏠 Address</p>
        <div class="rc-modal-field" style="margin-bottom:8px;">
          <label>Present Address</label>
          <textarea id="m_address" rows="2" class="editable-field" style="background:#FFF9E6; border-color:#FFA234;"></textarea>
        </div>
      </div>

      <div class="rc-modal-footer">
        <div class="rc-modal-note">
          <span class="warn-dot"></span>
          ₹<?php echo number_format($paymode,2); ?> will be deducted | ✎ Your edits will be saved in PDF
        </div>
        <div style="display:flex;gap:10px;">
          <button type="button" class="rc-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="rc-btn-download" id="downloadPdfBtn">⬇ Download RC PDF (with your edits)</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include("footer.php"); ?>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {

  function showToast(title, msg, type) {
    var icons = { success: '✅', error: '❌', info: 'ℹ️' };
    var t = $('#rcToast');
    t.removeClass('rc-toast-success rc-toast-error');
    if (type === 'success') t.addClass('rc-toast-success');
    if (type === 'error') t.addClass('rc-toast-error');
    $('#toastIcon').text(icons[type] || '📌');
    $('#toastTitle').text(title);
    $('#toastMsg').text(msg);
    t.addClass('show');
    setTimeout(function() { t.removeClass('show'); }, 4000);
  }

  function showLoader(text, sub) {
    $('#loaderText').text(text || 'Processing...');
    $('#loaderSub').text(sub || 'Please wait');
    $('#loadingOverlay').addClass('show');
  }
  function hideLoader() { $('#loadingOverlay').removeClass('show'); }

  // ── COLLECT ALL EDITED DATA FROM MODAL ──
  function collectEditedData() {
    return {
      rc_number: $('#m_rc_number').val(),
      owner_name: $('#m_owner_name').val(),
      father_name: $('#m_father_name').val(),
      vehicle_chasi_number: $('#m_chassis').val(),
      vehicle_engine_number: $('#m_engine').val(),
      registration_date: $('#m_reg_date').val(),
      fuel_type: $('#m_fuel_type').val(),
      norms_type: $('#m_norms').val(),
      vehicle_category_description: $('#m_class').val(),
      maker_description: $('#m_maker').val(),
      maker_model: $('#m_model').val(),
      color: $('#m_color').val(),
      body_type: $('#m_body').val(),
      seat_capacity: $('#m_seats').val(),
      unladen_weight: $('#m_unladen').val(),
      cubic_capacity: $('#m_cc').val(),
      no_cylinders: $('#m_cylinders').val(),
      wheelbase: $('#m_wheelbase').val(),
      manufacturing_date_formatted: $('#m_mfg').val(),
      fit_up_to: $('#m_fit_upto').val(),
      owner_number: $('#m_ownership').val(),
      registered_at: $('#m_rto').val(),
      present_address: $('#m_address').val()
    };
  }

  // ── CAPTCHA ──
  function loadCaptcha() {
    var btn = $('#refreshCaptchaBtn');
    btn.addClass('spinning');
    $('#captcha_svg_container').html('<svg width="90" height="32"><text x="8" y="22" fill="#8A97BB" font-size="12">Loading...</text></svg>');
    $.ajax({
      url: '', type: 'POST', dataType: 'json',
      data: { action: 'get_captcha' },
      success: function(res) {
        btn.removeClass('spinning');
        if (res.status === 'success') {
          $('#captcha_svg_container').html(res.svg);
          $('#session_id').val(res.session_id);
          $('#user_sid').val(res.user_sid);
        } else {
          $('#captcha_svg_container').html('<span style="font-size:11px;color:#FF4757;">Failed</span>');
          showToast('Captcha Error', 'Failed to load captcha', 'error');
        }
      },
      error: function() {
        btn.removeClass('spinning');
        $('#captcha_svg_container').html('<span style="font-size:11px;color:#FF4757;">Error</span>');
      }
    });
  }

  loadCaptcha();
  $('#refreshCaptchaBtn').on('click', loadCaptcha);

  // ── FETCH RC ──
  $('#fetchRcForm').on('submit', function(e) {
    e.preventDefault();
    var vehicle = $('#vehicle_no').val().trim();
    var captcha = $('#captcha_code').val().trim();
    var sid = $('#session_id').val();
    var usid = $('#user_sid').val();

    if (!vehicle) { showToast('Missing', 'Enter Vehicle Number', 'error'); return; }
    if (!captcha) { showToast('Missing', 'Enter Captcha', 'error'); return; }
    if (!sid || !usid) { showToast('Missing', 'Load captcha first', 'error'); return; }

    var btn = $('#fetchBtn');
    btn.html('<span>⏳</span> Fetching...').prop('disabled', true);
    showLoader('Fetching RC Details...', 'Contacting PARI PRINT');

    $.ajax({
      url: '', type: 'POST', dataType: 'json',
      data: {
        action: 'fetch_rc',
        rc_no: vehicle,
        captcha: captcha,
        session_id: sid,
        user_sid: usid,
        card_type: $('#card_type').val()
      },
      success: function(res) {
        hideLoader();
        btn.html('<span>⚡</span> Fetch Details').prop('disabled', false);
        loadCaptcha();
        $('#captcha_code').val('');

        if (res.Status === 'Success' && res.data) {
          var d = res.data;
          // Fill modal fields
          $('#m_rc_number').val(d.rc_number || vehicle);
          $('#m_owner_name').val(d.owner_name || '');
          $('#m_father_name').val(d.father_name || '');
          $('#m_chassis').val(d.vehicle_chasi_number || '');
          $('#m_engine').val(d.vehicle_engine_number || '');
          $('#m_reg_date').val(d.registration_date || '');
          $('#m_fuel_type').val(d.fuel_type || '');
          $('#m_norms').val(d.norms_type || '');
          $('#m_class').val(d.vehicle_category_description || '');
          $('#m_maker').val(d.maker_description || '');
          $('#m_model').val(d.maker_model || '');
          $('#m_color').val(d.color || '');
          $('#m_body').val(d.body_type || '');
          $('#m_seats').val(d.seat_capacity || '');
          $('#m_unladen').val(d.unladen_weight || '');
          $('#m_cc').val(d.cubic_capacity || '');
          $('#m_cylinders').val(d.no_cylinders || '');
          $('#m_wheelbase').val(d.wheelbase || '');
          $('#m_mfg').val(d.manufacturing_date_formatted || '');
          $('#m_fit_upto').val(d.fit_up_to || '');
          $('#m_ownership').val(d.owner_number || '');
          $('#m_rto').val(d.registered_at || '');
          $('#m_address').val(d.present_address || '');

          $('#step1dot').removeClass('active').addClass('done').text('✓');
          $('#step1lbl').removeClass('active');
          $('#step2dot').addClass('active');
          $('#step2lbl').addClass('active');

          $('#rcDetailsModal').modal('show');
          showToast('RC Found!', 'You can edit any field before download', 'success');
        } else {
          showToast('Fetch Failed', res.Message || 'Vehicle not found', 'error');
        }
      },
      error: function() {
        hideLoader();
        btn.html('<span>⚡</span> Fetch Details').prop('disabled', false);
        showToast('Server Error', 'Connection failed', 'error');
      }
    });
  });

  // ── GENERATE PDF with EDITED DATA ──
  $('#downloadPdfBtn').on('click', function() {
    var rcNo = $('#m_rc_number').val();
    if (!rcNo) { showToast('Error', 'No RC data', 'error'); return; }

    // 🔥 Collect all edited data from modal
    var editedData = collectEditedData();
    var cardType = $('#card_type').val();

    var btn = $(this);
    btn.html('⏳ Generating...').prop('disabled', true);
    showLoader('Generating PDF with your edits...', 'Please wait');

    $.ajax({
      url: '', type: 'POST', dataType: 'json',
      data: {
        action: 'generate_pdf',
        rc_no: rcNo,
        card_type: cardType,
        rc_data: editedData
      },
      success: function(res) {
        hideLoader();
        btn.html('⬇ Download RC PDF (with your edits)').prop('disabled', false);

        if (res.status === 'success') {
          var a = document.createElement('a');
          a.href = res.pdf;
          a.download = 'RC_' + rcNo + '.pdf';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);

          $('#step2dot').removeClass('active').addClass('done').text('✓');
          $('#step3dot').addClass('active');
          $('#step3lbl').addClass('active');

          showToast('Downloaded!', 'PDF with your edits saved', 'success');
          $('#rcDetailsModal').modal('hide');
          setTimeout(function() { window.location.href = 'rc_get_list'; }, 2000);
        } else {
          showToast('Failed', res.message || 'PDF generation failed', 'error');
        }
      },
      error: function(xhr) {
        hideLoader();
        btn.html('⬇ Download RC PDF (with your edits)').prop('disabled', false);
        var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error';
        showToast('Error', msg, 'error');
      }
    });
  });

  // Highlight editable fields
  $(document).on('focus', '.editable-field', function() {
    $(this).css('border-color', '#0057FF').css('background', '#fff');
  }).on('blur', '.editable-field', function() {
    $(this).css('border-color', '#FFA234').css('background', '#FFF9E6');
  });

});
</script>

</body>
</html>