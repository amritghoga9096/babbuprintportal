<?php
error_reporting(0);
include("../includes/config.php");

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from ayushmancard_save_date WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("print_ayushman.php#a'.$id.'","_self"); </script>' ;

?>

