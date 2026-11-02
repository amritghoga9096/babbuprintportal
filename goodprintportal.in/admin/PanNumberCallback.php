<?php 
include('../includes/config.php');
if(isset($_GET['pan_no']) && $_GET['application_no']){
    $application_no = mysqli_real_escape_string($ahk_conn,$_GET['application_no']);
    $pan_no = base64_decode(mysqli_real_escape_string($ahk_conn,$_GET['pan_no']));
    $check = mysqli_query($ahk_conn,"SELECT * FROM pan_no WHERE application_no='$application_no'");
    if(mysqli_num_rows($check)==1){
        $update = mysqli_query($ahk_conn,"UPDATE pan_no SET pan_no='$pan_no',status='success' WHERE application_no='$application_no'");
    }
}
?>