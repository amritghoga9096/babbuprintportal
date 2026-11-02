<?php 
// Database  Details 
$hostname = "localhost";
$username = "goodprintportal_good";
$password = "goodprintportal_good";
$database = "goodprintportal_good";

$ahk_conn = mysqli_connect($hostname,$username,$password,$database);

if(!$ahk_conn){
    include('links.php');
   ?>
   <script>
    $(function(){
        Swal.fire(
            'Opps',
            'Dadatabase Connection Failed',
            'error'
        )
    })
   </script>
   <?php
}