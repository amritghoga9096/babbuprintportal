<?php
error_reporting(0);
include("../includes/config.php");

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from postpass WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("Ippb_pass_list.php#a'.$id.'","_self"); </script>' ;
                           
?>

