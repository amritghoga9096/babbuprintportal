<?php
error_reporting(0);
include("../includes/config.php");

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from airtelpass WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("Airtel_pass_list.php#a'.$id.'","_self"); </script>' ;

?>

