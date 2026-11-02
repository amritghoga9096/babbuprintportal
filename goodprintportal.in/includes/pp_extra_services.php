<?php
/*
 * 13 Pariprint products (Udyam PDF, DL / Vahan to Mobile, Mask Aadhaar, Voter Advance, ABHA, LPG, Pincode, IFSC,
 * UAN, Ration to Aadhaar, PAN to Insurance, TIN). All run through the Pariprint reseller API with the key set in
 * admin settings ($pariprint_api_key). Prices live in the `pricing` table (editable from Admin -> Pricing).
 * PHP 7.4 compatible.
 */

function pp_extra_services()
{
    $dob = ['type' => 'dob', 'label' => 'Date of Birth', 'ph' => 'DD-MM-YYYY'];
    return [
        'udyam-to-udyam-pdf' => ['name' => 'Udyam No to Udyam PDF', 'price_key' => 'pp_udyam_to_pdf_fee', 'price' => 100, 'img' => 'pp_udyam-pdf.svg', 'pdf' => true,
            'desc' => 'Udyam Registration Number se Udyam Registration Certificate ka PDF turant download karein.',
            'inputs' => ['udyam_Number' => ['type' => 'udyam', 'label' => 'Udyam Registration Number', 'ph' => 'UDYAM-JH-11-0064860']]],
        'dl-to-mobile-find' => ['name' => 'DL to Mobile Find', 'price_key' => 'pp_dl_to_mobile_fee', 'price' => 30, 'img' => 'pp_dl-mobile.svg',
            'desc' => 'Driving Licence number aur date of birth se DL se linked mobile number, naam aur father name.',
            'inputs' => ['dlNo' => ['type' => 'dl', 'label' => 'Driving Licence Number', 'ph' => 'JK0220200007063'], 'dob' => $dob]],
        'pan-to-mask-aadhaar' => ['name' => 'PAN to Mask Aadhaar', 'price_key' => 'pp_pan_to_mask_aadhaar_fee', 'price' => 10, 'img' => 'pp_mask-aadhaar.svg',
            'desc' => 'PAN number se linked Aadhaar number (masked, jaise 91XXXXXXXX56) check karein.',
            'inputs' => ['panNumber' => ['type' => 'pan', 'label' => 'PAN Number', 'ph' => 'FNWPS5620C']]],
        'vahan-to-mobile' => ['name' => 'Vahan to Mobile', 'price_key' => 'pp_vahan_to_mobile_fee', 'price' => 30, 'img' => 'pp_vahan-mobile.svg',
            'desc' => 'Vehicle registration number se RC me registered mobile number.',
            'inputs' => ['vrn' => ['type' => 'vrn', 'label' => 'Vehicle Number', 'ph' => 'MH14KW1234']]],
        'voter-advance' => ['name' => 'Voter Advance', 'price_key' => 'pp_voter_advance_fee', 'price' => 10, 'img' => 'pp_voter-advance.svg',
            'desc' => 'EPIC (Voter ID) number se voter ki advance details.',
            'inputs' => ['epic' => ['type' => 'epic', 'label' => 'EPIC / Voter ID Number', 'ph' => 'SDB2251234']]],
        'abha-card-download' => ['name' => 'ABHA Card Download', 'price_key' => 'pp_abha_card_fee', 'price' => 50, 'img' => 'pp_abha.svg',
            'desc' => '12-digit Aadhaar number aur date of birth se ABHA (Ayushman Bharat Health Account) card download.',
            'inputs' => ['uid' => ['type' => 'aadhaar', 'label' => '12-Digit Aadhaar Number', 'ph' => '924787812345'], 'dob' => $dob]],
        'lpg-details' => ['name' => 'LPG Details', 'price_key' => 'pp_lpg_details_fee', 'price' => 20, 'img' => 'pp_lpg.svg',
            'desc' => 'LPG connection se linked mobile number se gas company, consumer name aur pincode.',
            'inputs' => ['mobile' => ['type' => 'mobile', 'label' => '10-Digit Mobile Number', 'ph' => '8084312345']]],
        'pincode-details' => ['name' => 'Pincode Details', 'price_key' => 'pp_pincode_details_fee', 'price' => 3, 'img' => 'pp_pincode.svg',
            'desc' => '6-digit pincode se sabhi post office, branch type, district, division, region aur state.',
            'inputs' => ['pincode' => ['type' => 'pincode', 'label' => '6-Digit Pincode', 'ph' => '825301']]],
        'ifsc-details' => ['name' => 'IFSC Details', 'price_key' => 'pp_ifsc_details_fee', 'price' => 3, 'img' => 'pp_ifsc.svg',
            'desc' => 'IFSC code se bank name, branch, MICR, address, city, district aur state.',
            'inputs' => ['ifsc_code' => ['type' => 'ifsc', 'label' => 'IFSC Code', 'ph' => 'SBIN0018601']]],
        'uan-employment-history' => ['name' => 'UAN Employment History', 'price_key' => 'pp_uan_history_fee', 'price' => 40, 'img' => 'pp_uan.svg',
            'desc' => '12-digit UAN number se EPFO employment history.',
            'inputs' => ['uan_number' => ['type' => 'uan', 'label' => '12-Digit UAN Number', 'ph' => '100000000000']]],
        'ration-to-aadhaar' => ['name' => 'Ration to Aadhaar Number', 'price_key' => 'pp_ration_to_aadhaar_fee', 'price' => 40, 'img' => 'pp_ration-aadhaar.svg',
            'desc' => 'Ration card number se card holder details aur family members (naam, relation, DOB, Aadhaar).',
            'inputs' => ['ration_no' => ['type' => 'ration', 'label' => 'Ration Card Number', 'ph' => '213740322711']]],
        'pan-to-insurance' => ['name' => 'PAN to Insurance', 'price_key' => 'pp_pan_to_insurance_fee', 'price' => 25, 'img' => 'pp_insurance.svg',
            'desc' => 'PAN number se linked insurance policies ki details.',
            'inputs' => ['pan_number' => ['type' => 'pan', 'label' => 'PAN Number', 'ph' => 'ABCDE1234F']]],
        'tin-details' => ['name' => 'TIN Details', 'price_key' => 'pp_tin_details_fee', 'price' => 25, 'img' => 'pp_tin.svg',
            'desc' => 'Company name, 2-digit state code aur TIN number se TIN verification.',
            'inputs' => ['company_name' => ['type' => 'company', 'label' => 'Company Name', 'ph' => 'SAMPLE COMPANY PVT LTD'],
                'state_code' => ['type' => 'state_code', 'label' => 'State Code (2 digit)', 'ph' => '27'],
                'tin_number' => ['type' => 'tin', 'label' => 'TIN Number', 'ph' => '27123456789']]],
    ];
}

/** Request table + missing pricing rows (existing prices are never touched). Runs once per request at most. */
function pp_extra_setup($conn)
{
    if (!$conn || !empty($GLOBALS['_pp_extra_setup_done'])) return;
    $GLOBALS['_pp_extra_setup_done'] = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pp_extra_requests` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(40) NOT NULL,
        `username` VARCHAR(50) NOT NULL,
        `slug` VARCHAR(60) NOT NULL,
        `service_name` VARCHAR(120) NOT NULL,
        `input_summary` VARCHAR(255) DEFAULT NULL,
        `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `status` VARCHAR(20) NOT NULL DEFAULT 'failed',
        `message` TEXT DEFAULT NULL,
        `result_json` LONGTEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_slug` (`username`, `slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $have = [];
    $res = @mysqli_query($conn, "SELECT service_name FROM pricing WHERE service_name LIKE 'pp\\_%'");
    while ($res && ($r = mysqli_fetch_assoc($res))) $have[$r['service_name']] = true;
    foreach (pp_extra_services() as $svc) {
        if (isset($have[$svc['price_key']])) continue;
        $stmt = mysqli_prepare($conn, 'INSERT INTO pricing (service_name, price) VALUES (?, ?)');
        if ($stmt) {
            $k = $svc['price_key'];
            $p = (string) $svc['price'];
            mysqli_stmt_bind_param($stmt, 'ss', $k, $p);
            @mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

function pp_extra_fee($conn, array $svc)
{
    $stmt = mysqli_prepare($conn, 'SELECT price FROM pricing WHERE service_name = ? LIMIT 1');
    $k = $svc['price_key'];
    mysqli_stmt_bind_param($stmt, 's', $k);
    mysqli_stmt_execute($stmt);
    $row = db_stmt_fetch_assoc($stmt);
    mysqli_stmt_close($stmt);
    return ($row && is_numeric($row['price'])) ? round((float) $row['price'], 2) : (float) $svc['price'];
}

/**
 * One stored request row (with result_json). Plain mysqli_query on purpose: without mysqlnd, bind_result on the
 * LONGTEXT column tries to allocate its 4 GB max size and the page dies blank. $phone / $slug null = no filter.
 */
function pp_extra_get_request($conn, $id, $phone = null, $slug = null, $successOnly = false)
{
    $sql = 'SELECT * FROM pp_extra_requests WHERE id = ' . (int) $id;
    if ($phone !== null) $sql .= " AND username = '" . mysqli_real_escape_string($conn, (string) $phone) . "'";
    if ($slug !== null) $sql .= " AND slug = '" . mysqli_real_escape_string($conn, (string) $slug) . "'";
    if ($successOnly) $sql .= " AND status = 'success'";
    $res = mysqli_query($conn, $sql . ' LIMIT 1');
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) mysqli_free_result($res);
    return $row ?: null;
}

/** Returns [clean value, error message or null]. */
function pp_extra_clean($type, $v)
{
    $v = trim((string) $v);
    switch ($type) {
        case 'udyam':
            $v = strtoupper(preg_replace('/\s+/', '', $v));
            return [$v, preg_match('/^UDYAM-[A-Z]{2}-\d{2}-\d{7}$/', $v) ? null : 'Sahi Udyam number daaliye (jaise UDYAM-JH-11-0064860).'];
        case 'dl':
            $v = strtoupper(preg_replace('/[\s-]+/', '', $v));
            return [$v, preg_match('/^[A-Z]{2}[A-Z0-9]{8,16}$/', $v) ? null : 'Sahi Driving Licence number daaliye.'];
        case 'dob':
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) {
                $d = (int) $m[3]; $mo = (int) $m[2]; $y = (int) $m[1];
            } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $v, $m)) {
                $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
            } else {
                return [$v, 'Date of Birth DD-MM-YYYY format me daaliye.'];
            }
            if (!checkdate($mo, $d, $y) || $y < 1900 || $y > (int) date('Y')) return [$v, 'Sahi Date of Birth daaliye.'];
            return [sprintf('%02d-%02d-%04d', $d, $mo, $y), null];
        case 'pan':
            $v = strtoupper(preg_replace('/\s+/', '', $v));
            return [$v, preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $v) ? null : 'Sahi 10-digit PAN number daaliye (jaise ABCDE1234F).'];
        case 'aadhaar':
            $v = preg_replace('/[\s-]+/', '', $v);
            return [$v, preg_match('/^[2-9][0-9]{11}$/', $v) ? null : 'Sahi 12-digit Aadhaar number daaliye.'];
        case 'vrn':
            $v = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $v));
            return [$v, preg_match('/^[A-Z0-9]{6,11}$/', $v) ? null : 'Sahi vehicle number daaliye (jaise MH14KW1234).'];
        case 'epic':
            $v = strtoupper(preg_replace('/\s+/', '', $v));
            return [$v, preg_match('/^[A-Z0-9\/]{6,20}$/', $v) ? null : 'Sahi EPIC / Voter ID number daaliye.'];
        case 'mobile':
            $v = preg_replace('/\D+/', '', $v);
            if (strlen($v) === 12 && substr($v, 0, 2) === '91') $v = substr($v, 2);
            return [$v, preg_match('/^[6-9][0-9]{9}$/', $v) ? null : 'Sahi 10-digit mobile number daaliye.'];
        case 'pincode':
            $v = preg_replace('/\s+/', '', $v);
            return [$v, preg_match('/^[1-9][0-9]{5}$/', $v) ? null : 'Sahi 6-digit pincode daaliye.'];
        case 'ifsc':
            $v = strtoupper(preg_replace('/\s+/', '', $v));
            return [$v, preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $v) ? null : 'Sahi IFSC code daaliye (jaise SBIN0018601).'];
        case 'uan':
            $v = preg_replace('/\s+/', '', $v);
            return [$v, preg_match('/^[0-9]{12}$/', $v) ? null : 'Sahi 12-digit UAN number daaliye.'];
        case 'ration':
            $v = preg_replace('/\s+/', '', $v);
            return [$v, preg_match('/^[0-9]{8,16}$/', $v) ? null : 'Sahi ration card number daaliye (8-16 digit).'];
        case 'company':
            $v = preg_replace('/\s+/', ' ', $v);
            return [$v, (strlen($v) >= 2 && strlen($v) <= 150) ? null : 'Company name daaliye.'];
        case 'state_code':
            $v = preg_replace('/\D+/', '', $v);
            if (strlen($v) === 1) $v = '0' . $v;
            return [$v, preg_match('/^[0-9]{2}$/', $v) ? null : 'Sahi 2-digit state code daaliye (jaise 27).'];
        case 'tin':
            $v = preg_replace('/\s+/', '', $v);
            return [$v, preg_match('/^[0-9]{8,15}$/', $v) ? null : 'Sahi TIN number daaliye (sirf digits).'];
    }
    return [$v, null];
}

/** Server-to-server call to Pariprint. The key goes in the POST body only (never in a URL / the browser). */
function pp_extra_call($slug, array $inputs, $apiKey)
{
    $base = function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in';
    // Pariprint reads the key from the service's own param name; send every spelling it uses.
    $post = array_merge(['slug' => $slug], $inputs, ['api_key' => $apiKey, 'apiKey' => $apiKey, 'apikey' => $apiKey, 'x-api-key' => $apiKey]);
    $ch = curl_init($base . '/api-proxy.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 150,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $err = $body === false ? curl_error($ch) : '';
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) return ['ok' => false, 'message' => 'Server se connect nahi ho paya. ' . $err, 'data' => null];

    $data = json_decode((string) $body, true);
    if (!is_array($data)) return ['ok' => false, 'message' => 'Server ne galat response diya. Thodi der baad try karein.', 'data' => null];

    // Pariprint answers HTTP 200 only for a billable success (it checks the provider's response code itself).
    $failed = false;
    foreach (['status', 'success'] as $k) {
        if (!array_key_exists($k, $data)) continue;
        $v = $data[$k];
        if ($v === false || $v === 0 || $v === '0' || (is_string($v) && in_array(strtolower(trim($v)), ['false', 'error', 'failed', 'fail'], true))) $failed = true;
    }
    $msg = '';
    foreach (['message', 'statusMessage', 'error', 'msg'] as $k) {
        if (isset($data[$k]) && is_scalar($data[$k]) && trim((string) $data[$k]) !== '' && !in_array(strtolower(trim((string) $data[$k])), ['success', 'failed'], true)) {
            $msg = trim((string) $data[$k]);
            break;
        }
    }
    if ($http !== 200 || $failed) {
        return ['ok' => false, 'message' => $msg !== '' ? pp_extra_scrub_text($msg, $apiKey) : 'Record nahi mila / service failed.', 'data' => null];
    }
    return ['ok' => true, 'message' => $msg !== '' ? $msg : 'Success', 'data' => pp_extra_scrub($data)];
}

function pp_extra_scrub_text($s, $apiKey)
{
    if ($apiKey !== '') $s = str_replace($apiKey, '***', $s);
    return preg_replace('#https?://\S+#i', '[link]', $s);
}

/** Drop Pariprint links / ids / upstream balance before anything is stored or shown. */
function pp_extra_scrub(array $data)
{
    $drop = ['download_url', 'request_id', 'order_id', 'amount', 'balance_left', 'balance', 'wallet_balance', 'charge', 'api_key', 'apikey'];
    foreach ($data as $k => $v) {
        if (in_array(strtolower((string) $k), $drop, true)) {
            unset($data[$k]);
        } elseif (is_string($v) && preg_match('#^https?://#i', $v)) {
            unset($data[$k]);
        } elseif (is_array($v)) {
            $data[$k] = pp_extra_scrub($v);
        }
    }
    return $data;
}

/** The downloadable file inside a stored result: ['ext' => pdf|png|jpg, 'bin' => bytes] or null. */
function pp_extra_file(array $data)
{
    foreach (['pdf', 'abha_card'] as $k) {
        if (empty($data[$k]) || !is_string($data[$k])) continue;
        $v = trim($data[$k]);
        if (preg_match('#^data:[^;]+;base64,(.*)$#is', $v, $m)) $v = $m[1];
        $bin = base64_decode(preg_replace('/\s+/', '', $v), true);
        if ($bin === false || $bin === '') continue;
        if (strncmp($bin, '%PDF', 4) === 0) return ['ext' => 'pdf', 'bin' => $bin];
        if (strncmp($bin, "\x89PNG", 4) === 0) return ['ext' => 'png', 'bin' => $bin];
        if (strncmp($bin, "\xFF\xD8", 2) === 0) return ['ext' => 'jpg', 'bin' => $bin];
    }
    return null;
}

function pp_extra_label($k)
{
    $k = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', (string) $k);
    return ucwords(trim(str_replace(['_', '-'], ' ', $k)));
}

/** Pretty HTML table(s) for a stored result (files and echo keys skipped). */
function pp_extra_render(array $node, $depth = 0)
{
    $skip = ['status', 'statusmessage', 'success', 'message', 'msg', 'error', 'code', 'pdf', 'abha_card', 'uidforrationdetails', 'ifsc_codechd', 'dltomobilefind'];
    $kv = '';
    $blocks = '';
    foreach ($node as $k => $v) {
        if ($depth === 0 && in_array(strtolower((string) $k), $skip, true)) continue;
        $label = htmlspecialchars(pp_extra_label($k));
        if (is_array($v)) {
            if ($v === []) {
                $kv .= '<tr><th style="width:40%">' . $label . '</th><td class="text-muted">Koi record nahi mila</td></tr>';
                continue;
            }
            $isRows = array_keys($v) === range(0, count($v) - 1);
            foreach ($v as $row) if (!is_array($row)) $isRows = false;
            if ($isRows) {
                $cols = [];
                foreach ($v as $row) foreach ($row as $ck => $cv) if (!is_array($cv)) $cols[(string) $ck] = true;
                $h = '<div class="fw-semibold mt-3 mb-1">' . $label . ' (' . count($v) . ')</div><div class="table-responsive"><table class="table table-bordered table-sm mb-0"><thead><tr>';
                foreach (array_keys($cols) as $c) $h .= '<th class="text-nowrap">' . htmlspecialchars(pp_extra_label($c)) . '</th>';
                $h .= '</tr></thead><tbody>';
                foreach (array_slice($v, 0, 100) as $row) {
                    $h .= '<tr>';
                    foreach (array_keys($cols) as $c) {
                        $cv = $row[$c] ?? null;
                        $h .= '<td class="text-break">' . htmlspecialchars(is_bool($cv) ? ($cv ? 'Yes' : 'No') : (($cv === null || $cv === '') ? '-' : (string) $cv)) . '</td>';
                    }
                    $h .= '</tr>';
                }
                $blocks .= $h . '</tbody></table></div>';
            } elseif ($depth < 3) {
                $inner = pp_extra_render($v, $depth + 1);
                if ($inner !== '') $blocks .= '<div class="fw-semibold mt-3 mb-1">' . $label . '</div>' . $inner;
            }
            continue;
        }
        if ($v === null) continue;
        $s = is_bool($v) ? ($v ? 'Yes' : 'No') : trim((string) $v);
        if ($s === '' || strlen($s) > 1500 || strpos($s, 'data:') === 0) continue;
        $kv .= '<tr><th style="width:40%">' . $label . '</th><td class="text-break">' . htmlspecialchars($s) . '</td></tr>';
    }
    return ($kv !== '' ? '<div class="table-responsive"><table class="table table-bordered table-striped table-sm mb-0"><tbody>' . $kv . '</tbody></table></div>' : '') . $blocks;
}
