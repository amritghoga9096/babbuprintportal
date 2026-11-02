<?php
include('../includes/config.php');
include('../includes/session.php'); 

header('Content-Type: application/json');

if (isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = mysqli_real_escape_string($ahk_conn, $_POST['order_id']);
    $new_status = mysqli_real_escape_string($ahk_conn, $_POST['status']);
    $pan_number = mysqli_real_escape_string($ahk_conn, $_POST['pan_number'] ?? '');
    
    // Security: Sirf logged-in user hi check kar sakta hai
    $mobile = $udata['phone']; 

    // 1. Record Database se Fetch karo (Amount aur Old Status pata karne ke liye)
    $q = mysqli_query($ahk_conn, "SELECT * FROM pan_list WHERE order_id='$order_id' AND userid='$mobile'");
    if(mysqli_num_rows($q) == 0){
        echo json_encode(['status' => false, 'message' => 'Record not found']);
        exit;
    }
    $row = mysqli_fetch_assoc($q);
    $old_status = $row['status'];
    $amount = $row['amount'];

    // 2. REFUND LOGIC START
    // Agar status 'failed' hai aur pehle se 'pending' tha, tab hi refund karein
    // Isse double refund bach jayega
    if ($new_status == 'failed' && $old_status == 'pending') {
        
        // (A) Wallet me Money Credit karo
        $update_bal = mysqli_query($ahk_conn, "UPDATE users SET balance = balance + $amount WHERE phone='$mobile'");
        
        // (B) New Balance fetch karo history ke liye
        $bq = mysqli_query($ahk_conn, "SELECT balance FROM users WHERE phone='$mobile'");
        $br = mysqli_fetch_assoc($bq);
        $new_balance = $br['balance'];

        // (C) Wallethistory me 'Refund' Entry add karo
        date_default_timezone_set("Asia/Kolkata");
        $date_time = date('d/m/Y g:i:s');
        
        $insert_hist = mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) 
            VALUES ('$mobile', '$amount', '$new_balance', 'Pan Refund - $order_id', '1', 'Credit')");
    }
    // REFUND LOGIC END

    // 3. Pan List me Status Update karo
    $query = "UPDATE pan_list SET status='$new_status', pan_number='$pan_number' WHERE order_id='$order_id' AND userid='$mobile'";
    
    if (mysqli_query($ahk_conn, $query)) {
        echo json_encode(['status' => true, 'message' => 'Status Updated']);
    } else {
        echo json_encode(['status' => false, 'message' => 'Update Failed']);
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid Data']);
}
?>