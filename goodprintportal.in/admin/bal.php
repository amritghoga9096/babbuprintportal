<?php
include('../includes/config.php');
// Query to select all user balances
$sql = "SELECT balance FROM users WHERE `balance` !='0' AND `type` !='admin'";

// Execute the query
$result = $ahk_conn->query($sql);

// Initialize a variable to hold the total balance
$totalBalance = 0;

// Check if there are any results
if ($result->num_rows > 0) {
    // Loop through the results and calculate the total balance
    while ($row = $result->fetch_assoc()) {
        $totalBalance += $row["balance"];
    }
}
echo "Total Balance : ₹".$totalBalance;
// Close the database connection
$ahk_conn->close();
?>