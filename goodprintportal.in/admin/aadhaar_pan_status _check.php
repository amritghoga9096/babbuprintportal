<?php
header('Content-Type: application/json');
include('../includes/config.php');

// Allow only POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed.']);
    exit;
}

// Validate Aadhaar number
$aadhaar = isset($_POST['aadhaar']) ? preg_replace('/\D/', '', $_POST['aadhaar']) : '';
if (empty($aadhaar) || strlen($aadhaar) !== 12) {
    echo json_encode(['error' => 'Invalid Aadhaar number']);
    exit;
}

// Build API URL
$apiBase = "https://" . htmlspecialchars($flexsecureapiurl);
$apiUrl = $apiBase . "/api/v2/NewPanRequest/adhaar_check.php?aadhaarNum=" . urlencode($aadhaar);

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// Execute cURL request
$responseApi = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo json_encode(['error' => 'API request failed: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

// Close cURL
curl_close($ch);

// Clean and return API response
$cleanResponse = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $responseApi);
echo $cleanResponse;
?>
