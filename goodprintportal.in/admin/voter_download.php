<?php include('../includes/config.php'); ?>
<?php
if(isset($_GET['printscard'])){
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['printscard']);
mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM printscard_voter_mob Where id='".$searchid."'");
$b = mysqli_fetch_array($a);
}
$pdf=$b['pdf'];
$response = file_get_contents($pdf);
header('Content-type: application/pdf');
header('Content-Disposition: attachment; filename="e-EPIC_.pdf"');
print_r($response);
?>