<?php
error_reporting(0);
include("../includes/config.php");

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from aadharauto WHERE aadharautoid=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("aadhar_advance_list.php#a'.$id.'","_self"); </script>' ;

?>

