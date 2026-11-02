<?php
error_reporting(0);
session_start();
require_once('lib/AhkWeb_Config.php');
require_once('lib/AhkWebCheckSum.php');

$checkSum = "";
$upiuid = "";
$paramList = array();

$gateway_type = $_POST['gateway_type'] ?? 'Normal';
$cust_Mobile = $_POST['cust_Mobile'] ?? '';
$cust_Email = $_POST['cust_Email'] ?? '';
$orderId = $_POST['ORDER_ID'] ?? '';
$txnAmount = $_POST['txnAmount'] ?? '';
$txnNote = $_POST['txnNote'] ?? '';

if ($orderId === '' || $orderId === null) {
    $orderId = 'WALLET' . date('YmdHis') . rand(1000, 9999);
}

$amountNumeric = is_numeric($txnAmount) ? (float)$txnAmount : 0.0;
if ($amountNumeric <= 0) {
    http_response_code(400);
    echo "Invalid amount";
    exit;
}

if ($amountNumeric < 100) {
    http_response_code(400);
    echo "Minimum amount is 100";
    exit;
}

$isWhole = floor($amountNumeric) == $amountNumeric;
$txnAmount = $isWhole ? (string)((int)$amountNumeric) : number_format($amountNumeric, 2, '.', '');
$cust_Mobile = preg_replace('/\D/', '', (string)$cust_Mobile);
if (strlen($cust_Mobile) > 10) {
    $cust_Mobile = substr($cust_Mobile, -10);
}
$cust_Email = trim((string)$cust_Email);
$txnNote = trim((string)$txnNote);
$gateway_type = trim((string)$gateway_type);

$txnNote = preg_replace('/[^A-Za-z0-9 _.-]/', '', $txnNote);
$txnNote = str_replace(' ', '_', $txnNote);
if ($txnNote === '') {
    $txnNote = 'WalletTopup';
}
if ($cust_Mobile === '' && isset($_SESSION['phone'])) {
    $cust_Mobile = (string)$_SESSION['phone'];
}
if ($cust_Email === '' && isset($_SESSION['email'])) {
    $cust_Email = (string)$_SESSION['email'];
}

include('../includes/database.php');

mysqli_query($ahk_conn, "
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(64) NOT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `txn_status` varchar(32) NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

$sid = session_id();
$phoneToStore = $cust_Mobile !== '' ? $cust_Mobile : ($_SESSION['phone'] ?? '');
$emailToStore = $cust_Email !== '' ? $cust_Email : ($_SESSION['emailid'] ?? '');

$stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallet_transactions(order_id, session_id, phone, email, amount, txn_status) VALUES(?,?,?,?,?,'PENDING') ON DUPLICATE KEY UPDATE session_id=VALUES(session_id), phone=VALUES(phone), email=VALUES(email), amount=VALUES(amount)");
mysqli_stmt_bind_param($stmt, 'ssssd', $orderId, $sid, $phoneToStore, $emailToStore, $amountNumeric);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$configuredUpi = '';
$settingsRes = mysqli_query($ahk_conn, "SELECT upi_id FROM settings WHERE id=1 LIMIT 1");
if ($settingsRes) {
    $settingsRow = mysqli_fetch_assoc($settingsRes);
    $configuredUpi = $settingsRow['upi_id'] ?? '';
}

$protocol = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', (string)dirname($_SERVER['SCRIPT_NAME'] ?? '/paytmUPI'));
$scriptDir = rtrim($scriptDir, '/');
$callback_url = $protocol . "://" . $host . $scriptDir . "/txnResult.php";

if($gateway_type=="Advanced"){
    
$AHKWEB_TXN_URL='https://pdfgod.shop/order/process';

$upiuid = $configuredUpi !== '' ? $configuredUpi : 'paytmqr15iw00mz9g@paytm';

}else if($gateway_type=="Robotics"){

$AHKWEB_TXN_URL='https://pdfgod.shop/order/process';

$upiuid = $configuredUpi !== '' ? $configuredUpi : 'paytmqr15iw00mz9g@paytm';



}else if($gateway_type=="Normal"){
    
$AHKWEB_TXN_URL='https://pdfgod.shop/order/process';   

$upiuid = $configuredUpi !== '' ? $configuredUpi : 'paytmqr15iw00mz9g@paytm';

}

$existingWallet = mysqli_prepare($ahk_conn, "SELECT id FROM wallet WHERE txn_id=? LIMIT 1");
mysqli_stmt_bind_param($existingWallet, 's', $orderId);
mysqli_stmt_execute($existingWallet);
mysqli_stmt_store_result($existingWallet);
$walletExists = mysqli_stmt_num_rows($existingWallet) > 0;
mysqli_stmt_close($existingWallet);

if (!$walletExists) {
    $ins = mysqli_prepare($ahk_conn, "INSERT INTO wallet (phone, amount, txn_id, email, PAYMENTMODE, status) VALUES (?, ?, ?, ?, 'PaytmUPI', 'pending')");
    $amountStr = $txnAmount;
    mysqli_stmt_bind_param($ins, 'ssss', $phoneToStore, $amountStr, $orderId, $emailToStore);
    mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);
}

// Create an array having all required parameters for creating checksum.
$paramList["upiuid"] = $upiuid;
$paramList["token"] = $token;
$paramList["orderId"] = $orderId ;
$paramList["txnAmount"] = $txnAmount;
$paramList["txnNote"] = $txnNote;
$paramList["callback_url"] = $callback_url;
$paramList["cust_Mobile"] = $cust_Mobile;
$paramList["cust_Email"] = $cust_Email;
$checkSum = AhkWebCheckSum::generateSignature($paramList,$secret);
?>
<html>
<head>
<title>Gateway Check Out Page</title>
</head>
<body>
	<center><h1>Please do not refresh this page...</h1></center>
		<form method="post" action="<?php echo $AHKWEB_TXN_URL ?>" name="f1">
		<table border="1">
			<tbody>
			<?php
			foreach($paramList as $name => $value) {
				echo '<input type="hidden" name="' . $name .'" value="' . $value . '">';
			}
			?>
			<input type="hidden" name="checksum" value="<?php echo $checkSum ?>">
			</tbody>
		</table>
		<script type="text/javascript">
			document.f1.submit();
		</script>
	</form>
</body>
</html>
