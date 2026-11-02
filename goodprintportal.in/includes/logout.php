<?php
session_start();
// session_status();
session_unset();
session_destroy();
?>
<script>
    window.location.href='../login.php';
</script>

