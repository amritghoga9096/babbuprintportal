<?php
function jsonRespFetchPlan($arr){
    echo json_encode($arr);
    exit;
}
$api_key = urlencode($flexsecure_key);

$mobile = preg_replace('/\D+/', '', $_GET['mobile'] ?? '');
if (strlen($mobile) !== 10) {
    jsonResp(['Message' => 'InvalidMobile', 'error' => 'Please provide a 10-digit mobile number.']);
}

$planUrl = "https://$flexsecureapiurl/api/v1/mobileRecharge/plan.php?apiKey=$api_key&mobileNumber=" . urlencode($mobile);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $planUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err || !$resp) {
    jsonResp(['Message' => 'APIError', 'error' => 'Plan API call failed: ' . ($err ?: 'Empty response')]);
}

$resp = trim($resp);
$json = json_decode($resp, true);

if (!$json) {
    jsonResp(['Message' => 'APIError', 'error' => 'Invalid JSON from Plan API', 'raw' => $resp]);
}

$out = [
    'Message' => 'Success',
    'mobile' => $mobile,
    'circle' => $json['circle'] ?? '',
    'operator' => $json['operator'] ?? '',
    'data' => []
];

if (isset($json['plans']['groupings'])) {
    foreach ($json['plans']['groupings'] as $group) {

        if (!isset($group['productList'])) continue;

        $plans = [];
        foreach ($group['productList'] as $p) {
            $plans[] = [
                'amount' => $p['price'] ?? '',
                'benefit' => $p['description'] ?? $p['displayName'] ?? '',
                'validity' => $p['validity'] ?? '',
                'talktime' => $p['talktime'] ?? ($p['data'] ?? '')
            ];
        }

        $out['data'][] = [
            'fullName' => $group['name'] ?? 'Plans',
            'plans' => $plans
        ];
    }
}

jsonResp($out);
