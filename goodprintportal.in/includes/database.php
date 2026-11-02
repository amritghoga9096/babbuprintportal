<?php 
// Database  Details 
$hostname = "localhost";
$username = "goodprintportal_good";
$password = "goodprintportal_good";
$database = "goodprintportal_good";

// Establish connection
$ahk_conn = mysqli_connect($hostname, $username, $password, $database);

// Check connection
if (!$ahk_conn) {
    // Include links.php for necessary dependencies (e.g., jQuery and Swal)
    include('links.php');
    ?>
    <script>
    $(document).ready(function() {
        Swal.fire(
            'Oops',
            'Database Connection Failed',
            'error'
        );
    });
    </script>
    <?php
}

// Auto-fix: Add missing columns to ll_exam_list table (exam_pin and state)
if ($ahk_conn) {
    // Check if exam_pin column exists
    $res = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM ll_exam_list LIKE 'exam_pin'");
    if (!$res || mysqli_num_rows($res) === 0) {
        @mysqli_query($ahk_conn, "ALTER TABLE ll_exam_list ADD COLUMN exam_pin VARCHAR(100) DEFAULT NULL AFTER password");
    }
    if ($res) { @mysqli_free_result($res); }

    // Check if state column exists
    $res2 = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM ll_exam_list LIKE 'state'");
    if (!$res2 || mysqli_num_rows($res2) === 0) {
        @mysqli_query($ahk_conn, "ALTER TABLE ll_exam_list ADD COLUMN state VARCHAR(100) DEFAULT NULL AFTER exam_pin");
    }
    if ($res2) { @mysqli_free_result($res2); }
}
?>
