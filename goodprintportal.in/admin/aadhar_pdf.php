<?php
@ob_start();
@include('../includes/config.php'); 
@ob_clean();
mysqli_set_charset($ahk_conn, "utf8mb4"); 

if (isset($_GET['aadharautoid'], $_GET['type'])) {

    $aadharautoid = intval(base64_decode($_GET['aadharautoid']));
    $type = base64_decode($_GET['type']);

    $sql = "SELECT * FROM `aadharauto` WHERE aadharautoid = '$aadharautoid' LIMIT 1";
    $result = mysqli_query($ahk_conn, $sql);
    if (!$result || mysqli_num_rows($result) == 0) {
        die("No record found");
    }
    $data = mysqli_fetch_assoc($result);

    // API call
    $postData = [
        "data_base64" => base64_encode(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
        "type" => $type
    ]; 
    $ch = curl_init($aadharpdfUrlBase64);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        die("Curl Error: " . $curlError);
    }

    // Decode API response
    $resData = json_decode($response, true);
    if (!$resData || empty($resData['pdf'])) {
        die("Invalid response from API");
    }

    // Clean PDF base64
    $pdfBase64 = preg_replace('/^data:application\/pdf;base64,/', '', $resData['pdf']);
    $pdfContent = base64_decode($pdfBase64);

    if ($pdfContent === false || strlen($pdfContent) < 100) {
        die("Failed to decode PDF or PDF too small");
    }

    // File name
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $resData['file_name'] ?? "Aadhar_$aadharautoid") . ".pdf";

    // Clean all buffers before output
    if (ob_get_length()) {
        ob_end_clean();
    }

    // ✅ Output headers to open PDF in browser (not download)
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"'); // changed here
    header('Content-Length: ' . strlen($pdfContent));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Output the PDF content
    echo $pdfContent;
    exit;
}
else {
    die("Missing aadharautoid or type");
}
?>
