<?php
error_reporting(0);
include("../includes/config.php");

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from school_id WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("School_id_card_list.php#a'.$id.'","_self"); </script>' ;

?>

