<?php
// Increase limits to prevent timeout issues
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '600'); // Allow script to run longer
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Prevent buffering in Nginx

$host = "localhost"; // Change if needed
$username = "goodprintportal_good";
$password = "goodprintportal_good";
$database = "goodprintportal_good";
$sqlFilePath = __DIR__ . "/ylddqwml_good.sql"; // Path to SQL file in the same folder

// Check if file exists
if (!file_exists($sqlFilePath)) {
    die("<p style='color:red;'>Error: SQL file not found at $sqlFilePath</p>");
}

// Connect to MySQL
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("<p style='color:red;'>Connection failed: " . $conn->connect_error . "</p>");
}

// Open SQL file
$handle = fopen($sqlFilePath, "r");
if (!$handle) {
    die("<p style='color:red;'>Error opening SQL file.</p>");
}

// Display live progress bar
echo "<html><body><h3>Importing SQL File...</h3>";
echo '<div style="width: 100%; background: #ccc; border-radius: 5px;">
        <div id="progress" style="width: 0%; background: green; height: 20px; text-align: center; color: white;">
            0%
        </div>
      </div>';
echo "<script>
      function updateProgress(percent) {
          document.getElementById('progress').style.width = percent + '%';
          document.getElementById('progress').innerText = percent + '%';
      }
      </script>";

ob_flush();
flush(); // Send initial output to browser immediately

$query = "";
$totalLines = 0;
$currentLine = 0;

// First, count total lines for accurate progress tracking
$lineCount = 0;
$tempHandle = fopen($sqlFilePath, "r");
while (!feof($tempHandle)) {
    fgets($tempHandle);
    $lineCount++;
}
fclose($tempHandle);

// Now process each line and execute queries
while (($line = fgets($handle)) !== false) {
    $currentLine++;

    // Skip comments and empty lines
    if (trim($line) === "" || strpos($line, "--") === 0 || strpos($line, "/*") === 0) {
        continue;
    }

    // Append line to query
    $query .= $line;

    // Execute query if it ends with a semicolon
    if (substr(trim($query), -1) == ";") {
        if (!$conn->query($query)) {
            echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
        }
        $query = ""; // Reset query for next one
    }

    // Update progress live
    $progress = intval(($currentLine / $lineCount) * 100);
    echo "<script>updateProgress($progress);</script>";
    ob_flush();
    flush(); // Immediately send output to browser
}

fclose($handle);
$conn->close();

echo "<h3 style='color:green;'>Import Completed!</h3></body></html>";

?>
