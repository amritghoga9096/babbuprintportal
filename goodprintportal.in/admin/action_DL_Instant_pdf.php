<?Php include('config.php'); ?>

<?php
error_reporting(0);
include("config.php");

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from dlprint WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("DL_Instant_Hkb_list.php#a'.$id.'","_self"); </script>' ;

?>

