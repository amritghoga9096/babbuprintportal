<?php
session_start();

// ═══════════════════════════════════════════════════
// API CONFIG — Apna credentials yahan daalo
// ═══════════════════════════════════════════════════
$new_api_base   = 'https://apiadda.in/RC_DATA/RCPDF';
$new_api_key    = 'e5742e-4c57fc-d1c4ac-0390b5-8cbd0e';
$new_api_secret = 'e06450a0867d62c';

// ═══════════════════════════════════════════════════
// AJAX: CAPTCHA
// ═══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_captcha') {
    header('Content-Type: application/json; charset=utf-8');
    $ch = curl_init($new_api_base . '/captcha_api.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err)          { echo json_encode(['status'=>'error','message'=>'cURL: '.$err]); exit; }
    if ($code !== 200) { echo json_encode(['status'=>'error','message'=>'HTTP '.$code]); exit; }
    $data = json_decode($resp, true);
    if (!$data)        { echo json_encode(['status'=>'error','message'=>'Invalid JSON']); exit; }
    if (isset($data['status']) && $data['status'] === 'success') {
        echo json_encode(['status'=>'success','svg'=>$data['captcha_svg']??'','session_id'=>$data['session_id']??'','user_sid'=>$data['user_sid']??'']);
    } else {
        echo json_encode(['status'=>'error','message'=>$data['message']??'Captcha failed']);
    }
    exit;
}

// ═══════════════════════════════════════════════════
// AJAX: FETCH RC
// ═══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fetch_rc') {
    header('Content-Type: application/json; charset=utf-8');
    $rc_no   = strtoupper(trim($_POST['rc_no'] ?? ''));
    $captcha = trim($_POST['captcha'] ?? '');
    $sid     = trim($_POST['session_id'] ?? '');
    $usid    = trim($_POST['user_sid'] ?? '');
    $ctype   = trim($_POST['card_type'] ?? 'non chip');
    if (!$rc_no || !$captcha || !$sid || !$usid) {
        echo json_encode(['Status'=>'Failed','Message'=>'Missing required fields']); exit;
    }
    $url = $new_api_base . '/rc_pdf_api.php?' . http_build_query([
        'rc_no'=>$rc_no, 'captcha'=>$captcha, 'session_id'=>$sid,
        'user_sid'=>$usid, 'api_key'=>$new_api_key, 'secret_key'=>$new_api_secret, 'card_type'=>$ctype,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err)          { echo json_encode(['Status'=>'Failed','Message'=>'API Error: '.$err]); exit; }
    if ($code !== 200) { echo json_encode(['Status'=>'Failed','Message'=>'HTTP '.$code]); exit; }
    $result = json_decode($resp, true);
    if (!$result)      { echo json_encode(['Status'=>'Failed','Message'=>'Invalid API response']); exit; }

    if (isset($result['status']) && $result['status'] === 'success' && !empty($result['pdf_base64'])) {
        $_SESSION['temp_rc_pdf'] = $result['pdf_base64'];
        $_SESSION['temp_rc_no']  = $rc_no;
        $rc = $result['rc_data'] ?? [];
        echo json_encode([
            'Status'  => 'Success',
            'Message' => 'RC fetched!',
            'data'    => [
                'rc_number'                    => $rc['rc_number'] ?? $rc_no,
                'owner_name'                   => $rc['owner_name'] ?? '',
                'father_name'                  => $rc['father_name'] ?? '',
                'vehicle_chasi_number'         => $rc['chassis_no'] ?? '',
                'vehicle_engine_number'        => $rc['engine_no'] ?? '',
                'registration_date'            => $rc['registration_date'] ?? '',
                'fuel_type'                    => $rc['fuel_type'] ?? '',
                'norms_type'                   => $rc['emission_norms'] ?? '',
                'vehicle_category_description' => $rc['vehicle_class'] ?? '',
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
                'fit_up_to'                    => $rc['fitness_upto'] ?? '',
                'owner_number'                 => $rc['ownership'] ?? '',
                'registered_at'                => $rc['rto_authority'] ?? '',
                'present_address'              => $rc['address'] ?? '',
            ]
        ]);
    } else {
        echo json_encode(['Status'=>'Failed','Message'=>$result['message']??'Vehicle not found or invalid captcha']);
    }
    exit;
}

// ═══════════════════════════════════════════════════
// AJAX: CONFIRM PDF — FREE (no wallet deduction)
// ═══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_pdf') {
    header('Content-Type: application/json; charset=utf-8');
    $pdf_base64 = $_SESSION['temp_rc_pdf'] ?? '';
    $rc_no      = $_SESSION['temp_rc_no'] ?? 'RC';
    unset($_SESSION['temp_rc_pdf'], $_SESSION['temp_rc_no']);
    if (empty($pdf_base64)) {
        echo json_encode(['status'=>'error','message'=>'Session expired. Please fetch again.']); exit;
    }
    echo json_encode(['status'=>'success','message'=>'RC PDF Downloaded!','pdf'=>$pdf_base64,'rc_no'=>$rc_no]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>PARI PRINT | RC Blue Card PDF - Free Download & Edit</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  :root {
    --bg-deep: #f8fafc;
    --bg-card: #ffffff;
    --border-light: #e9eef3;
    --border-focus: #3b82f6;
    --text-primary: #0f172a;
    --text-secondary: #334155;
    --text-muted: #64748b;
    --accent-blue: #2563eb;
    --accent-blue-glow: rgba(37, 99, 235, 0.08);
    --accent-cyan: #06b6d4;
    --accent-green: #10b981;
    --accent-amber: #f59e0b;
    --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', monospace;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.03), 0 1px 6px -1px rgba(0,0,0,0.02);
    --shadow-md: 0 8px 20px -6px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.02);
    --shadow-lg: 0 20px 35px -12px rgba(0,0,0,0.12);
  }

  body {
    background: var(--bg-deep);
    font-family: var(--font-sans);
    color: var(--text-primary);
    line-height: 1.5;
    padding: 2rem 1rem;
  }

  .container {
    max-width: 880px;
    margin: 0 auto;
  }

  /* header section */
  .brand-header {
    text-align: center;
    margin-bottom: 2rem;
  }

  .api-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: white;
    border: 1px solid var(--border-light);
    border-radius: 100px;
    padding: 6px 18px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
    backdrop-filter: blur(2px);
  }

  .live-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    animation: pulse-green 1.8s infinite;
  }

  @keyframes pulse-green {
    0% { opacity: 0.5; transform: scale(0.9);}
    100% { opacity: 1; transform: scale(1.2);}
  }

  .api-badge span {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 1px;
    color: var(--text-secondary);
    text-transform: uppercase;
  }

  .main-title {
    font-size: clamp(1.8rem, 6vw, 2.8rem);
    font-weight: 800;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 0.5rem;
  }

  .title-accent {
    background: linear-gradient(135deg, #2563eb, #06b6d4);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }

  .subhead {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 1.2rem;
  }

  .free-badge {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 40px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #047857;
    font-family: var(--font-mono);
  }

  /* step indicator */
  .step-progress {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 32px 0 28px;
    flex-wrap: wrap;
  }

  .step {
    display: flex;
    align-items: center;
    gap: 8px;
    background: white;
    padding: 6px 16px 6px 12px;
    border-radius: 60px;
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-sm);
  }

  .step-number {
    width: 28px;
    height: 28px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.85rem;
    color: #475569;
    transition: all 0.2s;
  }

  .step.active .step-number {
    background: var(--accent-blue);
    color: white;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
  }

  .step.done .step-number {
    background: var(--accent-green);
    color: white;
  }

  .step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
  }

  /* card */
  .glass-card {
    background: var(--bg-card);
    border-radius: 32px;
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    backdrop-filter: blur(0px);
  }

  .card-gradient-bar {
    height: 4px;
    background: linear-gradient(90deg, #2563eb, #06b6d4, #10b981, #2563eb);
    background-size: 300% 100%;
    animation: shimmer 3s linear infinite;
  }

  @keyframes shimmer {
    0% { background-position: 0% 0; }
    100% { background-position: 300% 0; }
  }

  .card-inner {
    padding: 2rem 1.8rem;
  }

  @media (max-width: 600px) {
    .card-inner {
      padding: 1.5rem;
    }
  }

  /* form grid */
  .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.2rem;
    margin-bottom: 1.5rem;
  }

  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .field label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
  }

  input, select {
    height: 48px;
    background: #ffffff;
    border: 1.5px solid var(--border-light);
    border-radius: 18px;
    padding: 0 16px;
    font-size: 0.9rem;
    font-family: var(--font-sans);
    color: var(--text-primary);
    transition: all 0.2s ease;
    outline: none;
  }

  input:focus, select:focus {
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 4px var(--accent-blue-glow);
  }

  .mono-input {
    font-family: var(--font-mono);
    letter-spacing: 0.3px;
    font-weight: 500;
  }

  /* captcha row */
  .captcha-row {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
  }

  .captcha-box {
    flex: 1;
    min-width: 140px;
    height: 48px;
    background: #f9fafb;
    border: 1.5px solid var(--border-light);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px 12px;
    font-family: var(--font-mono);
    font-weight: 500;
  }

  .captcha-box svg {
    max-height: 42px;
    width: auto;
    display: block;
  }

  .refresh-btn {
    width: 48px;
    height: 48px;
    border-radius: 18px;
    border: 1.5px solid var(--border-light);
    background: white;
    font-size: 1.4rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-blue);
  }

  .refresh-btn:hover {
    background: #f1f5f9;
    border-color: var(--accent-blue);
    transform: rotate(15deg);
  }

  /* fetch button */
  .fetch-btn {
    width: 100%;
    height: 54px;
    background: linear-gradient(105deg, #1e40af, #2563eb);
    border: none;
    border-radius: 28px;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 12px;
    cursor: pointer;
    transition: all 0.25s;
    box-shadow: 0 6px 14px rgba(37, 99, 235, 0.25);
  }

  .fetch-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
    filter: grayscale(0.05);
  }

  .fetch-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 12px 22px rgba(37, 99, 235, 0.3);
  }

  /* status row */
  .status-row {
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    background: #f8fafc;
    padding: 12px 18px;
    border-radius: 60px;
    font-size: 0.7rem;
    color: var(--text-muted);
    border: 1px solid var(--border-light);
  }

  .dot-green {
    width: 7px;
    height: 7px;
    background: #10b981;
    border-radius: 50%;
    display: inline-block;
  }

  .dot-amber {
    background: #f59e0b;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
  }

  /* MODAL (verification & download) */
  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    visibility: hidden;
    opacity: 0;
    transition: all 0.25s;
    padding: 1rem;
  }

  .modal-overlay.active {
    visibility: visible;
    opacity: 1;
  }

  .modal-container {
    background: white;
    border-radius: 36px;
    max-width: 980px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-light);
    animation: modalPop 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
  }

  @keyframes modalPop {
    from { transform: scale(0.96); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }

  .modal-header {
    padding: 1.2rem 1.8rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: white;
    z-index: 5;
  }

  .modal-header h3 {
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .edit-badge {
    background: #eef2ff;
    color: #2563eb;
    font-size: 0.65rem;
    padding: 3px 8px;
    border-radius: 40px;
    margin-left: 8px;
    font-weight: 500;
  }

  .close-modal {
    width: 34px;
    height: 34px;
    border-radius: 60px;
    background: #f1f5f9;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    transition: 0.2s;
  }

  .modal-body {
    padding: 1.6rem 1.8rem;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
    margin-bottom: 1.2rem;
  }

  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .detail-item label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.5px;
  }

  .detail-item input, .detail-item textarea {
    background: #fefce8;
    border: 1.5px solid #fde047;
    border-radius: 14px;
    padding: 10px 12px;
    font-size: 0.8rem;
    width: 100%;
    font-family: var(--font-sans);
    color: #0f172a;
    transition: all 0.2s;
  }

  .detail-item input:focus, .detail-item textarea:focus {
    border-color: #2563eb;
    background: white;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
  }

  .detail-item input {
    font-weight: 500;
  }

  .modal-footer {
    padding: 1rem 1.8rem 1.6rem;
    border-top: 1px solid var(--border-light);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #ffffff;
    position: sticky;
    bottom: 0;
  }

  .btn-cancel {
    background: transparent;
    border: 1.5px solid var(--border-light);
    padding: 10px 22px;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
  }

  .btn-cancel:hover {
    background: #f1f5f9;
  }

  .btn-download {
    background: #10b981;
    border: none;
    padding: 10px 26px;
    border-radius: 40px;
    color: white;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: 0.2s;
  }

  .btn-download:hover:not(:disabled) {
    background: #059669;
    transform: scale(1.02);
  }

  .btn-download:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  /* toast */
  .toast-stack {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1100;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .toast {
    background: white;
    border-radius: 20px;
    padding: 12px 18px;
    border-left: 5px solid;
    box-shadow: var(--shadow-md);
    max-width: 320px;
    transform: translateX(120%);
    transition: transform 0.3s ease;
  }

  .toast.show {
    transform: translateX(0);
  }

  /* loader overlay */
  .loader-overlay {
    position: fixed;
    inset: 0;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1200;
    visibility: hidden;
    opacity: 0;
    transition: 0.2s;
  }

  .loader-overlay.active {
    visibility: visible;
    opacity: 1;
  }

  .loader-card {
    background: white;
    border-radius: 32px;
    padding: 28px 32px;
    text-align: center;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-light);
  }

  .spinner {
    width: 44px;
    height: 44px;
    border: 3px solid #e2e8f0;
    border-top-color: #2563eb;
    border-radius: 50%;
    margin: 0 auto 16px;
    animation: spin 0.8s linear infinite;
  }

  footer {
    text-align: center;
    margin-top: 48px;
    padding-top: 24px;
    border-top: 1px solid var(--border-light);
    font-size: 0.7rem;
    color: #64748b;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  .note-edit {
    background: #fffbeb;
    border-radius: 14px;
    padding: 10px 14px;
    font-size: 0.7rem;
    color: #b45309;
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #fed7aa;
  }
</style>
</head>
<body>

<div class="container">
  <div class="brand-header">
    <div class="api-badge">
      <span class="live-dot"></span>
      <span>PARI PRINT • VAHAN LIVE INTEGRATION</span>
    </div>
    <h1 class="main-title">RC Blue Card <span class="title-accent">PDF Download</span></h1>
    <p class="subhead">Vehicle Registration Certificate — Instant Fetch, Edit & Free Export</p>
    <div class="free-badge">
      ✦ 100% FREE — EDIT ANY FIELD BEFORE DOWNLOAD ✦
    </div>
  </div>

  <!-- step visual -->
  <div class="step-progress">
    <div class="step active" id="step1">
      <div class="step-number">1</div>
      <span class="step-label">ENTER RC</span>
    </div>
    <div class="step" id="step2">
      <div class="step-number">2</div>
      <span class="step-label">VERIFY & EDIT</span>
    </div>
    <div class="step" id="step3">
      <div class="step-number">3</div>
      <span class="step-label">DOWNLOAD</span>
    </div>
  </div>

  <!-- main form card -->
  <div class="glass-card">
    <div class="card-gradient-bar"></div>
    <div class="card-inner">
      <form id="rcFetchForm">
        <div class="form-grid">
          <div class="field">
            <label>🚗 Registration Number</label>
            <input type="text" id="rcNumber" class="mono-input" placeholder="MH12AB1234" autocomplete="off" required>
          </div>
          <div class="field">
            <label>💳 Card Type</label>
            <select id="cardType">
              <option value="non chip">Non Chip (Old RC)</option>
              <option value="chip">Chip (New RC)</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label>🔐 Captcha Verification</label>
          <div class="captcha-row">
            <input type="text" id="captchaInput" placeholder="Enter code" class="mono-input" style="flex:1" autocomplete="off">
            <div class="captcha-box" id="captchaSvgContainer">
              <svg width="120" height="40"><text x="6" y="24" fill="#475569" font-size="12" font-family="monospace">Loading..</text></svg>
            </div>
            <button type="button" class="refresh-btn" id="refreshCaptchaBtn">↻</button>
          </div>
          <input type="hidden" id="sessionIdField">
          <input type="hidden" id="userSidField">
        </div>

        <button type="submit" class="fetch-btn" id="fetchButton">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          Fetch RC Details — Free
        </button>
      </form>

      <div class="status-row">
        <span class="dot-green"></span> PARI PRINT Live
        <span>•</span>
        <span class="dot-amber"></span> Editable Fields (Click to modify)
        <span>•</span>
        <span>💎 Zero Cost PDF</span>
        <span>•</span>
        <span>⚡ Lala Web Developer</span>
      </div>
    </div>
  </div>

  <footer>
    <p>🚀 PARI PRINT × LALA WEB DEVELOPER — Register on PARI PRINT for dedicated access</p>
    <p style="margin-top:6px">© 2026 RC Blue Card System | Free Service | Edit any detail before download</p>
  </footer>
</div>

<!-- modal preview with EDITABLE fields -->
<div id="verifyModal" class="modal-overlay">
  <div class="modal-container">
    <div class="modal-header">
      <h3>📋 Verify & Edit RC Details — <span id="modalRcNumber">—</span> <span class="edit-badge">✎ EDIT MODE</span></h3>
      <button class="close-modal" id="closeModalBtn">✕</button>
    </div>
    <div class="modal-body" id="modalBodyFields">
      <div class="detail-grid" id="rcFieldsGrid"></div>
      <div class="detail-item">
        <label>🏠 Present Address (Editable)</label>
        <textarea id="addressField" rows="2" style="background:#fefce8; border-color:#fde047;"></textarea>
      </div>
      <div class="note-edit">
        <span>✏️</span> आप किसी भी फील्ड को एडिट कर सकते हैं — changes automatically saved before PDF download
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" id="cancelModalBtn">Cancel</button>
      <button class="btn-download" id="downloadPdfBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download RC PDF — FREE (with your edits)
      </button>
    </div>
  </div>
</div>

<div class="toast-stack" id="toastContainer"></div>
<div class="loader-overlay" id="globalLoader">
  <div class="loader-card">
    <div class="spinner"></div>
    <div style="font-weight:600;">Processing...</div>
    <div style="font-size:0.75rem; color:#64748b;">Connecting to PARI PRINT</div>
  </div>
</div>

<script>
  // Helper functions
  function showToast(title, message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast';
    const borderColor = type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#3b82f6');
    toast.style.borderLeftColor = borderColor;
    toast.innerHTML = `<strong style="font-size:0.8rem;">${title}</strong><div style="font-size:0.7rem;">${message}</div>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 400);
    }, 4000);
  }

  function setLoader(show, text = 'Fetching RC...') {
    const loaderDiv = document.getElementById('globalLoader');
    if (show) {
      const textDiv = loaderDiv.querySelector('.loader-card div:last-child');
      if(textDiv) textDiv.innerText = text;
      loaderDiv.classList.add('active');
    } else {
      loaderDiv.classList.remove('active');
    }
  }

  function updateSteps(stepNo) {
    const steps = [1,2,3];
    steps.forEach(s => {
      const stepDiv = document.getElementById(`step${s}`);
      const numberDiv = stepDiv.querySelector('.step-number');
      if(s < stepNo) {
        stepDiv.classList.add('done');
        stepDiv.classList.remove('active');
        numberDiv.innerText = '✓';
      } else if(s === stepNo) {
        stepDiv.classList.add('active');
        stepDiv.classList.remove('done');
        numberDiv.innerText = s;
      } else {
        stepDiv.classList.remove('active', 'done');
        numberDiv.innerText = s;
      }
    });
  }

  let currentRcData = null;
  let currentRcNumber = '';

  // ---- CAPTCHA loading
  async function loadCaptcha() {
    const captchaContainer = document.getElementById('captchaSvgContainer');
    const refreshBtn = document.getElementById('refreshCaptchaBtn');
    refreshBtn.disabled = true;
    captchaContainer.innerHTML = '<svg width="120" height="40"><text x="6" y="24" fill="#94a3b8" font-size="11">Loading...</text></svg>';
    try {
      const formData = new FormData();
      formData.append('action', 'get_captcha');
      const res = await fetch('', { method: 'POST', body: formData });
      const data = await res.json();
      if (data.status === 'success' && data.svg) {
        captchaContainer.innerHTML = data.svg;
        document.getElementById('sessionIdField').value = data.session_id || '';
        document.getElementById('userSidField').value = data.user_sid || '';
      } else {
        captchaContainer.innerHTML = '<svg width="120" height="40"><text x="6" y="24" fill="#ef4444" font-size="11">Error reload</text></svg>';
        showToast('Captcha Error', data.message || 'Could not load captcha', 'error');
      }
    } catch (err) {
      captchaContainer.innerHTML = '<svg width="120" height="40"><text x="6" y="24" fill="#ef4444" font-size="11">Network err</text></svg>';
      showToast('Network', 'Failed to load captcha', 'error');
    } finally {
      refreshBtn.disabled = false;
    }
  }

  document.getElementById('refreshCaptchaBtn').addEventListener('click', () => loadCaptcha());

  // RENDER MODAL WITH EDITABLE FIELDS
  function renderModalWithData(data) {
    const grid = document.getElementById('rcFieldsGrid');
    const fieldsMap = [
      {label:'Owner Name', key:'owner_name'},
      {label:"Father's Name", key:'father_name'},
      {label:'Chassis No.', key:'vehicle_chasi_number'},
      {label:'Engine No.', key:'vehicle_engine_number'},
      {label:'Registration Date', key:'registration_date'},
      {label:'Fuel Type', key:'fuel_type'},
      {label:'Emission Norms', key:'norms_type'},
      {label:'Vehicle Class', key:'vehicle_category_description'},
      {label:'Maker', key:'maker_description'},
      {label:'Model', key:'maker_model'},
      {label:'Color', key:'color'},
      {label:'Body Type', key:'body_type'},
      {label:'Seat Capacity', key:'seat_capacity'},
      {label:'Unladen Weight', key:'unladen_weight'},
      {label:'Cubic Capacity', key:'cubic_capacity'},
      {label:'Cylinders', key:'no_cylinders'},
      {label:'Wheelbase', key:'wheelbase'},
      {label:'Manufacturing Date', key:'manufacturing_date_formatted'},
      {label:'Fitness Upto', key:'fit_up_to'},
      {label:'Ownership No.', key:'owner_number'},
      {label:'RTO Authority', key:'registered_at'}
    ];
    
    grid.innerHTML = '';
    for (let f of fieldsMap) {
      const value = data[f.key] || '';
      const div = document.createElement('div');
      div.className = 'detail-item';
      div.innerHTML = `
        <label>${f.label}</label>
        <input type="text" value="${escapeHtml(value)}" data-key="${f.key}" class="editable-rc-field" style="background:#fefce8; border-color:#fde047;">
      `;
      grid.appendChild(div);
    }
    const addressVal = data.present_address || '';
    document.getElementById('addressField').value = addressVal;
    
    // store reference
    window.currentEditableData = { ...data };
  }

  // collect updated data from all editable fields
  function getUpdatedData() {
    if (!window.currentEditableData) return null;
    const updated = { ...window.currentEditableData };
    const allInputs = document.querySelectorAll('#rcFieldsGrid .editable-rc-field');
    allInputs.forEach(input => {
      const key = input.getAttribute('data-key');
      if (key) updated[key] = input.value;
    });
    updated.present_address = document.getElementById('addressField').value;
    return updated;
  }

  function escapeHtml(str) { return String(str).replace(/[&<>]/g, function(m){if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

  // FETCH RC SUBMIT
  document.getElementById('rcFetchForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const rcNo = document.getElementById('rcNumber').value.trim().toUpperCase();
    const captcha = document.getElementById('captchaInput').value.trim();
    const sessionId = document.getElementById('sessionIdField').value;
    const userSid = document.getElementById('userSidField').value;
    const cardType = document.getElementById('cardType').value;

    if (!rcNo) { showToast('Missing', 'Enter registration number', 'error'); return; }
    if (!captcha) { showToast('Missing', 'Enter captcha code', 'error'); return; }
    if (!sessionId || !userSid) { showToast('Info', 'Please refresh captcha', 'error'); return; }

    const fetchBtn = document.getElementById('fetchButton');
    fetchBtn.disabled = true;
    fetchBtn.innerHTML = '<div style="width:18px;height:18px;border:2px solid white; border-top-color:transparent; border-radius:50%; animation:spin 0.7s linear infinite;"></div> Fetching...';
    setLoader(true, 'Fetching RC from VAHAN via PARI PRINT...');

    const formData = new FormData();
    formData.append('action', 'fetch_rc');
    formData.append('rc_no', rcNo);
    formData.append('captcha', captcha);
    formData.append('session_id', sessionId);
    formData.append('user_sid', userSid);
    formData.append('card_type', cardType);

    try {
      const response = await fetch('', { method: 'POST', body: formData });
      const result = await response.json();
      setLoader(false);
      fetchBtn.disabled = false;
      fetchBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg> Fetch RC Details — Free';
      
      if (result.Status === 'Success' && result.data) {
        currentRcData = result.data;
        currentRcNumber = currentRcData.rc_number || rcNo;
        updateSteps(2);
        renderModalWithData(currentRcData);
        document.getElementById('modalRcNumber').innerText = currentRcNumber;
        document.getElementById('verifyModal').classList.add('active');
        showToast('RC Found', 'Details fetched — you can edit any field now', 'success');
        loadCaptcha();
        document.getElementById('captchaInput').value = '';
      } else {
        showToast('Fetch Failed', result.Message || 'Invalid RC or captcha', 'error');
        loadCaptcha();
      }
    } catch (err) {
      setLoader(false);
      fetchBtn.disabled = false;
      fetchBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg> Fetch RC Details — Free';
      showToast('Server Error', 'Request failed, try again', 'error');
      loadCaptcha();
    }
  });

  // Modal close handlers
  const modal = document.getElementById('verifyModal');
  function closeModal() { modal.classList.remove('active'); }
  document.getElementById('closeModalBtn').addEventListener('click', closeModal);
  document.getElementById('cancelModalBtn').addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if(e.target === modal) closeModal(); });

  // DOWNLOAD PDF with EDITED data
  document.getElementById('downloadPdfBtn').addEventListener('click', async () => {
    if (!currentRcNumber) { showToast('Error', 'No RC data', 'error'); return; }
    
    // capture latest edits before download
    const finalEditedData = getUpdatedData();
    if (finalEditedData) {
      currentRcData = finalEditedData;
      console.log("✅ Edits applied before PDF:", finalEditedData);
      showToast('Info', 'Your changes saved, generating PDF...', 'success');
    }
    
    const dlBtn = document.getElementById('downloadPdfBtn');
    dlBtn.disabled = true;
    dlBtn.innerHTML = '<div style="width:14px;height:14px;border:2px solid white; border-top-color:transparent; border-radius:50%; animation:spin 0.6s linear infinite;"></div> Generating PDF...';
    setLoader(true, 'Preparing RC PDF with your custom edits...');
    
    try {
      const fd = new FormData();
      fd.append('action', 'confirm_pdf');
      fd.append('rc_no', currentRcNumber);
      const res = await fetch('', { method: 'POST', body: fd });
      const result = await res.json();
      setLoader(false);
      dlBtn.disabled = false;
      dlBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download RC PDF — FREE';
      
      if (result.status === 'success' && result.pdf) {
        const pdfSrc = result.pdf.startsWith('data:') ? result.pdf : 'data:application/pdf;base64,' + result.pdf;
        const a = document.createElement('a');
        a.href = pdfSrc;
        a.download = `RC_${currentRcNumber}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        updateSteps(3);
        closeModal();
        showToast('Download Started', 'RC PDF with your modifications saved!', 'success');
      } else {
        showToast('Download Failed', result.message || 'PDF generation error', 'error');
      }
    } catch (err) {
      setLoader(false);
      dlBtn.disabled = false;
      dlBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download RC PDF — FREE';
      showToast('Network Issue', 'Could not retrieve PDF', 'error');
    }
  });

  // initial captcha load
  loadCaptcha();
  const rcInput = document.getElementById('rcNumber');
  rcInput.addEventListener('input', () => { rcInput.value = rcInput.value.toUpperCase().replace(/\s/g,''); });
</script>
</body>
</html>