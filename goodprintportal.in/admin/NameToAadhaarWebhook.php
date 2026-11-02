<?php 
include('../includes/config.php');
if(isset($_GET['application_no']) && $_GET['aadhaar_no'] && $_GET['status'] && $_GET['remark']){
    $application_no = mysqli_real_escape_string($ahk_conn,$_GET['application_no']);
    $aadhaar_no = base64_decode(mysqli_real_escape_string($ahk_conn,$_GET['aadhaar_no']));
    $status = base64_decode(mysqli_real_escape_string($ahk_conn,$_GET['status']));
    $remark = base64_decode(mysqli_real_escape_string($ahk_conn,$_GET['remark']));
    $check = mysqli_query($ahk_conn,"SELECT * FROM name_to_aadhaar WHERE application_no='$application_no'");
    if(mysqli_num_rows($check)==1){
        $update = mysqli_query($ahk_conn,"UPDATE name_to_aadhaar SET aadhaar_no='$aadhaar_no',status='$status', remark='$remark' WHERE application_no='$application_no'");
        if($status == 'refunded'){
            $data = mysqli_fetch_assoc($check);
            $amount = $data['fee'];
            mysqli_query($ahk_conn,"UPDATE users SET balance=balance+$amount WHERE phone='".$data['appliedby']."'");
        }
    }
}
?>