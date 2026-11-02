<?php
/**
 * PariPrint API Request Wrapper (End-Customer Auto-Tag Injector)
 *
 * WORKFLOW:
 *   1. Service files (rc_get.php, rc_get_nochip.php, voter_pdf.php, ...) call
 *      this file as if it were the real PariPrint api-proxy.php endpoint.
 *   2. We load the GoodPrint user session, grab the LOGGED-IN end-customer's
 *      name + mobile, and APPEND them as customer_name / customer_mobile params.
 *   3. We forward the complete request (ALL ORIGINAL params + new customer
 *      auto-tags) to the REAL PariPrint api-proxy.php via cURL.
 *   4. We transparently pass PariPrint's raw response (status code, content
 *      type, body) back to the calling service file.
 *
 * ADVANTAGES:
 *   - Zero changes needed to rc_get.php, voter_pdf.php, dl_find.php, etc.
 *   - Every existing + future service using $pariprint_*_endpoint variables
 *     automatically benefits.
 *   - 100% backward compatible - if wrapper is removed, revert 4 lines in
 *     includes/config.php and everything is back to normal.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$wIncDir = __DIR__ . '/includes';

/* ---- Load session polyfills + config ($udata, $CENTRAL_API_BASE, etc) ---- */
if (!function_exists('str_contains')) @include_once $wIncDir . '/session.php';
@include_once $wIncDir . '/config.php';

/* ---- Collect ALL incoming params (service files may hit via GET or POST) ---- */
$params = [];
if (is_array($_GET)) {
    foreach ($_GET as $k => $v) {
        if ($k === '_') continue; /* skip cache-buster timestamps */
        $params[$k] = $v;
    }
}
if (is_array($_POST)) {
    foreach ($_POST as $k => $v) {
        $params[$k] = $v; /* POST overrides GET for same key (standard PHP style) */
    }
}

/* ---- AUTO-INJECT END-CUSTOMER TAGS from GoodPrint session ($udata) ---- */
if (is_array($GLOBALS['udata'] ?? null)) {
    $customerMobile = trim((string)($GLOBALS['udata']['phone'] ?? ''));
    $customerName   = trim((string)($GLOBALS['udata']['name']  ?? ''));

    if ($customerMobile !== '' && !isset($params['customer_mobile']) && empty($params['customer_mobile'])) {
        $params['customer_mobile'] = $customerMobile;
    }
    if ($customerMobile !== '' && !isset($params['user_mobile']) && empty($params['user_mobile'])) {
        $params['user_mobile'] = $customerMobile;
    }
    if ($customerMobile !== '' && !isset($params['client_mobile']) && empty($params['client_mobile'])) {
        $params['client_mobile'] = $customerMobile;
    }
    if ($customerMobile !== '' && !isset($params['mobile_number']) && empty($params['mobile_number'])) {
        $params['mobile_number'] = $customerMobile;
    }

    if ($customerName === '' && $customerMobile !== '') {
        $customerName = 'Customer ' . $customerMobile;
    }
    if ($customerName !== '' && !isset($params['customer_name']) && empty($params['customer_name'])) {
        $params['customer_name'] = $customerName;
    }
    if ($customerName !== '' && !isset($params['user_name']) && empty($params['user_name'])) {
        $params['user_name'] = $customerName;
    }
    if ($customerName !== '' && !isset($params['client_name']) && empty($params['client_name'])) {
        $params['client_name'] = $customerName;
    }
    if ($customerName !== '' && !isset($params['full_name']) && empty($params['full_name'])) {
        $params['full_name'] = $customerName;
    }
}

/* ---- Resolve REAL upstream PariPrint base URL ---- */
$ppBase = 'https://pariprint.in';
if (!empty($GLOBALS['CENTRAL_API_BASE']) && is_string($GLOBALS['CENTRAL_API_BASE']) && $GLOBALS['CENTRAL_API_BASE'] !== '') {
    $ppBase = rtrim($GLOBALS['CENTRAL_API_BASE'], '/');
}
$upstream = $ppBase . '/api-proxy.php';

/* ---- Pick HTTP method ---- */
$method = 'GET';
if (!empty($_SERVER['REQUEST_METHOD'])) {
    $method = strtoupper($_SERVER['REQUEST_METHOD']);
    if ($method !== 'GET' && $method !== 'POST') {
        $method = empty($_POST) ? 'GET' : 'POST';
    }
}
if ($method === 'GET' && !empty($_POST)) {
    $method = 'POST';
}

/* ---- Execute upstream call via cURL ---- */
$queryString = http_build_query($params);
$respBody    = '';
$respHttp    = 0;
$respType    = 'application/json';
$curlErr     = '';

if (function_exists('curl_init')) {
    $ch = curl_init();
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_URL, $upstream);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);
    } else {
        curl_setopt($ch, CURLOPT_URL, $upstream . '?' . $queryString);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $respBody = (string)curl_exec($ch);
    $respHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct       = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if ($ct !== '') $respType = $ct;
    $curlErr  = (string)curl_error($ch);
    curl_close($ch);
} else {
    /* Fallback: file_get_contents (if curl disabled on host) */
    $ctx = stream_context_create([
        'http' => [
            'method'  => $method,
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $queryString,
            'timeout' => 90,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $finalUrl = ($method === 'POST') ? $upstream : ($upstream . '?' . $queryString);
    $respBody = (string)@file_get_contents($finalUrl, false, $ctx);

    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hdr) {
            if (stripos($hdr, 'HTTP/') === 0) {
                $parts = explode(' ', $hdr, 3);
                if (isset($parts[1])) $respHttp = (int)$parts[1];
            }
            if (stripos($hdr, 'Content-Type:') === 0) {
                $respType = trim(substr($hdr, strlen('Content-Type:')));
            }
        }
    }
}

/* ---- In case of total transport failure, respond with a structured JSON error ---- */
if ($respBody === '' && $curlErr !== '') {
    $respHttp = 502;
    $respType = 'application/json';
    $respBody = json_encode([
        'status'  => false,
        'success' => false,
        'message' => 'PariPrint API wrapper: upstream connection failed (' . $curlErr . ')',
        'debug'   => [
            'upstream' => $upstream,
            'method'   => $method,
            'param_keys' => array_keys($params),
            'injected_mobile' => $params['customer_mobile'] ?? null,
            'injected_name'   => $params['customer_name']   ?? null,
        ],
    ]);
}

/* ---- Relay content-type + HTTP status back to the caller ---- */
if (!headers_sent()) {
    @header('Content-Type: ' . $respType, true, $respHttp > 0 ? $respHttp : 200);
    @header('X-PP-Inject: enabled; auto-tags=' . (isset($params['customer_mobile']) ? 'yes' : 'no'));
    @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

echo $respBody;
exit;
