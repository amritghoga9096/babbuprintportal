<?Php 
include('../includes/config.php'); ?>

<?php
error_reporting(0);

include('../includes/config.php');

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from rasn_print WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("Ration_Pdf_hkb_list.php#a'.$id.'","_self"); </script>' ;

?>

