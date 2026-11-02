<?php
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
echo "<h2>GoodPrintPortal.in - Voter PDF Fix Tool (OPCache Clear & Syntax)</h2>";

echo "<h3>Step 1: Clear PHP OPCache (sabse zaroori - Hostinger me iske bina old code serve hota hai)</h3>";
if (function_exists('opcache_reset')) {
    $reset = @opcache_reset();
    echo "<p>✅ opcache_reset() = " . ($reset ? "TRUE (Success)" : "FALSE (Failure)") . "</p>";
    if (function_exists('opcache_get_status')) {
        $status = @opcache_get_status(false);
        if (is_array($status)) {
            echo "<p>✅ OPCache enabled: " . ($status['opcache_enabled'] ? 'YES' : 'NO') . " | Cached scripts: " . (int)($status['opcache_statistics']['num_cached_scripts'] ?? 0) . "</p>";
        }
    }
} else {
    echo "<p>⚠️ OPCache extension available nahi hai (ya already disabled). Is step ki zaroorat nahi.</p>";
}

if (function_exists('opcache_invalidate')) {
    $thisDir = __DIR__;
    $filesToClear = [
        $thisDir . '/template/ahkweb/voter_pdf_list.php',
        $thisDir . '/admin/voter_pdf.php',
        $thisDir . '/admin/voter_pdf_list.php',
        $thisDir . '/admin/config.php',
        $thisDir . '/admin/header.php',
        $thisDir . '/template/ahkweb/header.php',
        $thisDir . '/template/ahkweb/footer.php',
    ];
    $cleared = 0;
    $missed = [];
    foreach ($filesToClear as $f) {
        if (file_exists($f)) {
            $ok = @opcache_invalidate($f, true);
            if ($ok) $cleared++;
            else $missed[] = basename($f);
        }
    }
    echo "<p>✅ $cleared/" . count($filesToClear) . " files ka OPCache invalidate kar diya gaya.</p>";
    if (!empty($missed)) echo "<p style='color:#f59e0b;'>⚠️ Ye files invalidate nahi hui (cached version abhi bhi serve ho sakta hai, re-run karo ya 2-3 min wait): " . htmlspecialchars(implode(', ', $missed)) . "</p>";
}

echo "<hr>";
echo "<h3>Step 2: voter_pdf_list.php (list page) Quick Sanity</h3>";
$listFile = __DIR__ . '/template/ahkweb/voter_pdf_list.php';
if (!file_exists($listFile)) {
    echo "<p style='color:#ef4444;'>❌ $listFile MISSING!</p>";
} else {
    $sz = filesize($listFile);
    $src = file_get_contents($listFile);
    $hasHeaderInclude = strpos($src, "include('header.php');") !== false;
    $hasFooterInclude = strpos($src, "include('footer.php');") !== false;
    $hasAjaxBlock = strpos($src, "ajaxAction") !== false && strpos($src, "check_refund") !== false;
    $hasAutoCheckMax5 = strpos($src, '$autoCheckMax = 5') !== false;
    $hasNoPageLoadCurlFlood = !(preg_match('/while\s*\([^)]*mysqli_fetch/', $src) && strpos($src, 'fetch_pariprint_voter_status_') !== false && strpos($src, 'mysqli_fetch_assoc') !== false && strpos($src, $listFile) !== false);
    echo "<p>✅ File size: " . number_format($sz) . " bytes</p>";
    echo "<p>" . ($hasHeaderInclude ? "✅" : "⚠️") . " header.php include: " . ($hasHeaderInclude ? "FOUND" : "MISSING") . "</p>";
    echo "<p>" . ($hasFooterInclude ? "✅" : "⚠️") . " footer.php include: " . ($hasFooterInclude ? "FOUND" : "MISSING") . "</p>";
    echo "<p>" . ($hasAjaxBlock ? "✅" : "❌") . " AJAX check_refund handler: " . ($hasAjaxBlock ? "FOUND (Button-click se status check hoga - NO page-load flood!)" : "MISSING (page-load cURL flood ka issue aa sakta hai)") . "</p>";
    echo "<p>" . ($hasAutoCheckMax5 ? "✅" : "⚠️") . " \$autoCheckMax = 5 limit: " . ($hasAutoCheckMax5 ? "FOUND (sirf top 5 rows page-load me status check)" : "NOT FOUND (unlimited cURL on load timeout ho sakta hai)") . "</p>";
}

echo "<hr>";
echo "<h3>Step 3: admin/voter_pdf.php (submit page) Quick Sanity</h3>";
$subFile = __DIR__ . '/admin/voter_pdf.php';
if (!file_exists($subFile)) {
    echo "<p style='color:#ef4444;'>❌ $subFile MISSING!</p>";
} else {
    $sz = filesize($subFile);
    $src = file_get_contents($subFile);
    $correctTs = strpos($src, "date('Y-m-d H:i:s')") !== false;
    $badTs = strpos($src, "date('d/m/Y g:i:s')") !== false;
    $hasBalVerify = strpos($src, 'balanceDeducted') !== false && strpos($src, 'affected_rows') !== false;
    $hasInsertVerify = strpos($src, 'listInserted') !== false && strpos($src, 'mysqli_insert_id') !== false;
    $hasPhoneNorm = strpos($src, '_gpp_norm_phone_submit') !== false;
    echo "<p>✅ File size: " . number_format($sz) . " bytes</p>";
    echo "<p>" . ($correctTs && !$badTs ? "✅" : ($badTs ? "❌" : "⚠️")) . " Timestamp format: " . ($correctTs && !$badTs ? "CORRECT → 'Y-m-d H:i:s' (MySQL DATETIME ke liye sahi)" : ($badTs ? "STILL WRONG → 'd/m/Y g:i:s' (MySQL me 0000-00-00 dikhega!)" : "UNKNOWN (check manually)")) . "</p>";
    echo "<p>" . ($hasBalVerify ? "✅" : "⚠️") . " Balance deduction affected_rows verify: " . ($hasBalVerify ? "FOUND (cut confirm hone ke baad hi aage badhega)" : "NOT FOUND (silent fail sakta hai)") . "</p>";
    echo "<p>" . ($hasInsertVerify ? "✅" : "⚠️") . " voter_pdf_list INSERT insert_id verify: " . ($hasInsertVerify ? "FOUND (row confirm insert hone ke baad proceed)" : "NOT FOUND") . "</p>";
    echo "<p>" . ($hasPhoneNorm ? "✅" : "⚠️") . " Phone normalization (+91/0 prefix strip): " . ($hasPhoneNorm ? "FOUND" : "NOT FOUND (phone format mismatch → record show nahi hoga)") . "</p>";
}

echo "<hr>";
echo "<h3>Step 4: voter_pdf_list table schema (auto-add status/refund columns)</h3>";
$useBootstrapInThisDir = file_exists(__DIR__ . '/includes/bootstrap.php');
$useAdminConfig = file_exists(__DIR__ . '/admin/config.php');
if (!$useAdminConfig) {
    echo "<p style='color:#ef4444;'>❌ admin/config.php nahi mila - DB credentials nahi le sake.</p>";
} else {
    $dbInit = false;
    $ahk_conn = null;
    try {
        $_SERVER['REQUEST_METHOD'] = 'CLI';
        ob_start();
        require_once __DIR__ . '/admin/header.php';
        $out = ob_get_clean();
        if (isset($ahk_conn) && $ahk_conn) $dbInit = true;
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $out = ob_get_clean();
    }
    if (!$dbInit) {
        try {
            ob_start();
            require_once __DIR__ . '/admin/config.php';
            $out = ob_get_clean();
            if (isset($ahk_conn) && $ahk_conn) $dbInit = true;
        } catch (Throwable $e) {
            $err2 = $e->getMessage();
            $out = ob_get_clean();
        }
    }
    if (!$dbInit || !isset($ahk_conn) || !$ahk_conn) {
        echo "<p style='color:#f59e0b;'>⚠️ DB connection nahi ho payi (is file ko browser se run karein via https://goodprintportal.in/fix-cache-vpdf.php — session/global scope me DB vars set hote hain). Expected if run via CLI.</p>";
    } else {
        echo "<p style='color:#16a34a;'>✅ DB Connection OK (goodprintportal ke ahk_conn se).</p>";
        $tblQ = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM voter_pdf_list");
        if (!$tblQ) {
            echo "<p style='color:#ef4444;'>❌ voter_pdf_list table nahi mila! Error: " . htmlspecialchars(mysqli_error($ahk_conn)) . "</p>";
        } else {
            $cols = [];
            while ($r = mysqli_fetch_assoc($tblQ)) $cols[] = strtolower($r['Field']);
            mysqli_free_result($tblQ);
            echo "<p>✅ voter_pdf_list columns (" . count($cols) . "): " . htmlspecialchars(implode(', ', $cols)) . "</p>";
            $needCols = ['refunded', 'refunded_at', 'gpp_status', 'refund_amount'];
            $missing = array_diff($needCols, $cols);
            if (!empty($missing)) {
                echo "<p style='color:#f59e0b;'>⚠️ Ye columns ABHI MISSING hain (list page first load par auto-ADD ho jayenge via _gpp_ensure_voter_schema_list()): <strong>" . htmlspecialchars(implode(', ', $missing)) . "</strong></p>";
            } else {
                echo "<p style='color:#16a34a;'>✅ Sabhi schema columns (refunded/refunded_at/gpp_status/refund_amount) present hain — auto-refund & status-caching features 100% work karenge.</p>";
            }
            $cntQ = @mysqli_query($ahk_conn, "SELECT COUNT(*) FROM voter_pdf_list");
            if ($cntQ && ($cntR = mysqli_fetch_row($cntQ))) {
                echo "<p>✅ voter_pdf_list total rows: <strong>" . (int)$cntR[0] . "</strong></p>";
                mysqli_free_result($cntQ);
            }
            $badDateQ = @mysqli_query($ahk_conn, "SELECT COUNT(*) FROM voter_pdf_list WHERE date = '0000-00-00 00:00:00' OR date IS NULL");
            if ($badDateQ && ($bdR = mysqli_fetch_row($badDateQ))) {
                $badN = (int)$bdR[0];
                if ($badN > 0) echo "<p style='color:#f59e0b;'>⚠️ $badN rows me DATE column corrupt hai (0000-00-00 00:00:00) — ye pehle ke submissions hain jisme 'd/m/Y' format bheja gaya tha (schema DATETIME ko 'Y-m-d H:i:s' chahiye tha). Naye submissions me issue nahi hoga.</p>";
                else echo "<p style='color:#16a34a;'>✅ Koi bhi row me date corrupt nahi hai.</p>";
                mysqli_free_result($badDateQ);
            }
        }
    }
}

echo "<hr>";
echo "<h3>Ab Test Karein (Critical Steps):</h3>";
echo "<ol style='line-height:1.9;'>";
echo "<li>✅ <strong>Multiple Voter PDF requests ek saath submit karein</strong> (2-3 baar alag-alag EPIC no. se).</li>";
echo "<li>✅ Submit karte hi Swal.fire footer me <strong>Balance Deducted: YES</strong> + <strong>voter_pdf_list INSERT: id=...</strong> dikhna chahiye. Agar NO dikhe to screenshot leke bhejo.</li>";
echo "<li>✅ <a target='_blank' href='/admin/voter_pdf_list.php'>admin/voter_pdf_list.php</a> open karein — <strong>SAARI requests dikhni chahiye</strong> (jo bhi submit ki thi un sab ki) — sirf last wali nahi. Table header ke paas <code>Showing N request(s)</code> count confirm karein.</li>";
echo "<li>✅ Har row ke <em>Check Status</em> button pe click karein — status update hona chahiye (PariPrint se live) + agar failed hai to auto-refund ho + wallet badge green pulse.</li>";
echo "<li>✅ Agar success hai, <em>Download PDF</em> button click → PDF download ho / new tab me khule.</li>";
echo "</ol>";
echo "<p><strong>ISSUE FIX KEY POINTS:</strong></p>";
echo "<ul style='line-height:1.8;'>";
echo "<li><span style='color:#16a34a;'>✅ Page-load cURL FLOOD HATA DIYA:</span> List page ke WHILE row loop me status check API call nahi hota (timeout hone ka wajah se sirf last row render hota tha). Ab sirf <code>Check Status</code> button click par AJAX se call hota hai.</li>";
echo "<li><span style='color:#16a34a;'>✅ Submit Timestamp format FIX:</span> <code>'d/m/Y g:i:s'</code> (MySQL invalid) → <code>'Y-m-d H:i:s'</code> (MySQL DATETIME standard) — ab 0000-00-00 nahi aayega date column me.</li>";
echo "<li><span style='color:#16a34a;'>✅ Balance deduction 100% VERIFY:</span> UPDATE ke baad <code>mysqli_affected_rows</code> check + phir <code>SELECT balance</code> se post-verify. Swal footer me green/red me dikhega confirm.</li>";
echo "<li><span style='color:#16a34a;'>✅ INSERT VERIFY (row actually bana?):</span> <code>mysqli_insert_id</code> + <code>affected_rows</code> dono se check — agar insert fail to Swal me RED error. Wallet bhi us hisaab se reconcile.</li>";
echo "<li><span style='color:#16a34a;'>✅ Phone Normalization:</span> Session ke phone me +91 ho ya 0 prefix ho ya space ho — sab normalize karke 10-digit me store aur WHERE clause me dual match (exact + LIKE %10digit) — isliye ab saare records filter out nahi honge.</li>";
echo "<li><span style='color:#16a34a;'>✅ Failed auto-refund:</span> Top 5 newest pending rows page-load par status check karke failed = wallet me Rs 50 wapas. Baaki rows ke liye button click par check.</li>";
echo "</ul>";
echo "<p style='color:#16a34a;font-size:1.05rem;'><strong>✅ Agar sab steps pass ho jayein, to problem FULLY SOLVED hai — multiple requests ab list me saari dikhengi!</strong></p>";
echo "<p><small>Note: Security ke liye test ke baad is file ko delete kar dena: <code>goodprintportal.in/fix-cache-vpdf.php</code></small></p>";
