<?php
error_reporting(0);

include('../includes/config.php');

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from shram_ax WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("shram_list.php#a'.$id.'","_self"); </script>' ;

?>

