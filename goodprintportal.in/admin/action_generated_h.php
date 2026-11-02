<?php
error_reporting(0);
include("../includes/config.php");

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from matching_dublicate_hkb WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("generated_h_list.php#a'.$id.'","_self"); </script>' ;

?>

