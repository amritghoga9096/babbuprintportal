<?php include('../includes/database.php');
header('Content-Type:application/json');
if(isset($_GET['api'])){
    $key=mysqli_real_escape_string($ahk_conn,$_GET['api']);
    $cheakapikey=mysqli_query($ahk_conn,"select * from settings where api='$key'");
    if(mysqli_num_rows($cheakapikey)>0){
     $cheakapirow=mysqli_fetch_assoc($cheakapikey);
     if($cheakapirow['keystatus']==1){
    }else{
    $status='true';
    $data="Api Key Has Been Expired";
    $code='3';
    }
    }else{
    $status='true';
    $data="Please Provide Valid Api Key";
    $code='2';
    }
    }else{
    $status='true';
    $data="Please Provide Api Key";
    $code='1';
}
?>