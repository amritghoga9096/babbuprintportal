<?php
error_reporting(0);

include('../includes/config.php');

$id = $_REQUEST['id'];

$updt = mysqli_query($ahk_conn,"delete from rc_vehical WHERE id=".$id."") ;

//header("location:backend.php#a".$id); exit();

echo '<script> window.open("rc_get_list.php#a'.$id.'","_self"); </script>' ;

?>

