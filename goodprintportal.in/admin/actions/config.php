<?php
require('../../includes/database.php');
// require('links.php');
// Fetch Website Settings Data
$host = $_SERVER['SERVER_NAME'];
$dir = dirname($_SERVER['SCRIPT_NAME']);

$webres = mysqli_query($ahk_conn,"SELECT * FROM settings WHERE id=1 AND status=1");

$webdata = mysqli_fetch_assoc($webres);
// Check If User Is Logged In or Not

function checkSession(){
    if(!$_SESSION['phone']){
        header('Location: ../login.php');
    }
}


if(isset($_SESSION['phone'])){
    $udata = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM users WHERE phone='".$_SESSION['phone']."'"));

//  Check Admin Here 
    function checkAdmin($usertype){
        if($usertype == "admin"){
            return true;
        }else{
            return false;
        }
    }
    // 
    // get All users List
    $allusersforadmin = mysqli_num_rows(mysqli_query($ahk_conn,"SELECT * FROM users"));
    $eidtoaadhaar = mysqli_num_rows(mysqli_query($ahk_conn,"SELECT * FROM aadhaarpdf"));
    // EID to aadhaar for User 
    $eidtoaadhaarfuser = mysqli_num_rows(mysqli_query($ahk_conn,"SELECT * FROM aadhaarpdf WHERE appliedby='".$udata['phone']."'"));

    // 
    $eidtoaadhaarNoforadmin = mysqli_num_rows(mysqli_query($ahk_conn,"SELECT * FROM aadhaar_no "));
    $eidtoaadhaarNoforuser = mysqli_num_rows(mysqli_query($ahk_conn,"SELECT * FROM aadhaar_no WHERE appliedby='".$udata['phone']."'"));
    // 
    $pan_noforadmin = mysqli_num_rows(mysqli_query($ahk_conn,"SELECT * FROM pan_no "));
    $pan_no_foruser = mysqli_num_rows(mysqli_query($ahk_conn,"SELECT * FROM pan_no WHERE appliedby='".$udata['phone']."'"));

}


?>