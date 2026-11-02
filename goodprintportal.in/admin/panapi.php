<?php
include('panapikey.php');
if(!isset($status)){
    if(isset($_POST['application_no']) && isset($_POST['pan'])){
        $id=mysqli_real_escape_string($ahk_conn,$_POST['application_no']);
        $pan=mysqli_real_escape_string($ahk_conn,$_POST['pan']);
        $sks=mysqli_real_escape_string($ahk_conn,$_POST['status']);
        mysqli_query($ahk_conn,"UPDATE `pan_no` SET `pan_no`='$pan',status='$sks' WHERE application_no='$id'");
        $status='true';
        $data="Data Update Successfully";
        $code='9';
    }else{
        $status='true';
        $data="Data Not Updated";
        $code='8';
    }
        
}

echo json_encode(["ststus"=>$ststus,"data"=>$data,"returnvalue"=>$code]);
?>