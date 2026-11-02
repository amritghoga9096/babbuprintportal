<?php
// /path/to/this/script.php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Get fee from pricing table for Aadhaar verify
$priceResult = mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='ration_to_adhaar_find_all' LIMIT 1");
$price = $priceResult ? mysqli_fetch_assoc($priceResult) : null;
$fee = isset($price['price']) ? (float)$price['price'] : 170;
$appliedby = $udata['phone'] ?? ''; // logged-in user's phone

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Helper: safe mysqli_real_escape wrapper
 */
function esc($conn, $val) {
    return mysqli_real_escape_string($conn, $val);
}

/**
 * Helper: show SweetAlert via JS (message is JSON-encoded server-side)
 * We'll echo the JS and optionally redirect to a clean URL to avoid form resubmits.
 */
function swal_and_redirect($title, $text, $icon = 'info', $redirect = null) {
    $titleJs = json_encode($title);
    $textJs  = json_encode($text);
    $iconJs  = json_encode($icon);
    echo "<script>
        if (typeof Swal !== 'undefined') {
            Swal.fire($titleJs, $textJs, $iconJs).then(() => {";
    if ($redirect) {
        $redirectJs = json_encode($redirect);
        echo "window.location.href = $redirectJs;";
    }
    echo "});
        } else {
            alert(" . json_encode($title . ' - ' . $text) . ");
            ".($redirect ? "window.location.href = " . json_encode($redirect) . ";" : "")."
        }
    </script>";
}

/**
 * Default redirect for check_status errors (per your request)
 */
$checkStatusErrorRedirect = 'ration2_uid_finder_all_state';

/**
 * Handle rationNo submission (Apply)
 */
if (!empty($_POST['rationNo'])) {
    // sanitize input to digits only
    $rationNo = preg_replace('/\D+/', '', $_POST['rationNo']);
    if ($rationNo === '') {
        swal_and_redirect('Invalid Input', 'Please enter a valid Ration number.', 'error', '');
        exit;
    }

    // check wallet
    $userBalance = isset($udata['balance']) ? (float)$udata['balance'] : 0;
    if ($userBalance < $fee) {
        // not enough wallet
        swal_and_redirect('Wallet Low', 'Please recharge your wallet.', 'error', 'wallet');
        exit;
    }

    // Prevent duplicate/pending request: check BEFORE calling external API
    $safeRationNo = esc($ahk_conn, $rationNo);
    $safeUser = esc($ahk_conn, $appliedby);
    $existsQ = mysqli_query($ahk_conn, "SELECT id FROM ration2uid_find WHERE rationNo='{$safeRationNo}' AND username='{$safeUser}' AND status='pending' LIMIT 1");
    if ($existsQ && mysqli_num_rows($existsQ) > 0) {
        swal_and_redirect('Already Exists', 'Pending request already exists.', 'info', '');
        exit;
    }

    // Single cURL call (only once)
    $curl = curl_init();
    $apiUrl = "https://{$flexsecureapiurl}/api/v2/Ration2Uid/Apply?apiKey={$flexsecure_key}&rationNumber={$rationNo}";
    curl_setopt_array($curl, array(
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ));
    $response = curl_exec($curl);
    $curlErr = curl_error($curl);
    curl_close($curl);

    if ($curlErr) {
        swal_and_redirect('Network Error', $curlErr, 'error', '');
        exit;
    }

    $resdata = json_decode($response, true);
    if (!is_array($resdata)) {
        // invalid JSON
        swal_and_redirect('API Error', 'Invalid response from API.', 'error', '');
        exit;
    }

    $status = $resdata['status'] ?? '';
    $rationNumberFromApi = $resdata['rationNumber'] ?? $rationNo;
    $message = $resdata['statusMessage'] ?? $resdata['error'] ?? 'Server Down';
    $errorText = $resdata['error'] ?? '';

    if ((string)$status === "100") {
        // SUCCESS: perform DB updates (deduct fee, wallet history, insert request)
        date_default_timezone_set('Asia/Kolkata');
        $timehkb = date("Y-m-d H:i:s");

        // Deduct balance (use a safe update)
        $safeUserPhone = esc($ahk_conn, $appliedby);
        $debitQuery = "UPDATE users SET balance = balance - {$fee} WHERE phone = '{$safeUserPhone}' LIMIT 1";
        $debit = mysqli_query($ahk_conn, $debitQuery);

        // fetch new balance for log
        $freshUser = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT balance FROM users WHERE phone='{$safeUserPhone}' LIMIT 1"));
        $debit_fee = isset($freshUser['balance']) ? $freshUser['balance'] : ($userBalance - $fee);

        // Log wallet history
        $insertLogQuery = "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('{$safeUserPhone}', '{$fee}', '{$debit_fee}', 'All State Ration UID Find', '1', 'Debit')";
        $logRes = mysqli_query($ahk_conn, $insertLogQuery);

        // Insert request record — ensure rationNo used is escaped and from API if available
        $safeRationNumberFromApi = esc($ahk_conn, $rationNumberFromApi);
        $insert = mysqli_query($ahk_conn, "INSERT INTO ration2uid_find(rationNo, status, username, date) VALUES ('{$safeRationNumberFromApi}', 'pending', '{$safeUserPhone}', '{$timehkb}')");

        if ($debit && $logRes && $insert) {
            // Success: show SweetAlert and redirect to clean GET (no reload)
            swal_and_redirect($message, 'Successfully submitted. Request is pending.', 'success', $_SERVER['PHP_SELF']);
            exit;
        } else {
            // rollback attempt (simple)
            // If debit succeeded but insert failed, attempt to credit back (best-effort)
            if ($debit) {
                mysqli_query($ahk_conn, "UPDATE users SET balance = balance + {$fee} WHERE phone = '{$safeUserPhone}' LIMIT 1");
            }
            swal_and_redirect('Insert Failed', 'Error saving record.', 'error', '');
            exit;
        }
    } else {
    // API returned non-100 status
    $errMsg = $errorText ?: ($resdata['message'] ?? 'API Error');
    // Redirect after OK
    swal_and_redirect($errMsg, 'API Error', 'error', 'ration2_uid_finder_all_state');
    exit;
}

}

/**
 * Handle check_status action (Check status and refund or complete)
 * Expecting POST field 'check_status_id'
 */
if (isset($_POST['check_status_id'])) {
    $rowId = intval($_POST['check_status_id']);
    if ($rowId <= 0) {
        swal_and_redirect('Error', 'Invalid record id.', 'error', $checkStatusErrorRedirect);
        exit;
    }

    $rowIdEsc = intval($rowId);
    $rowData = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM ration2uid_find WHERE id='{$rowIdEsc}' LIMIT 1"));

    if (!$rowData) {
        swal_and_redirect('Error', 'Record not found.', 'error', $checkStatusErrorRedirect);
        exit;
    }

    $checkPan = $rowData['rationNo'];
    $username = $rowData['username'];
    $currentStatus = $rowData['status'];

    if ($currentStatus === 'refunded') {
        swal_and_redirect('Already Refunded', 'This record has already been refunded.', 'info', $checkStatusErrorRedirect);
        exit;
    }

    // Call CheckStatus once
    $checkUrl = "https://{$flexsecureapiurl}/api/v2/Ration2Uid/CheckStatus?apiKey={$flexsecure_key}&rationNumber={$checkPan}";
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $checkUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ));
    $response = curl_exec($curl);
    $curlErr = curl_error($curl);
    curl_close($curl);

    if ($curlErr) {
        swal_and_redirect('Network Error', $curlErr, 'error', $checkStatusErrorRedirect);
        exit;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        swal_and_redirect('API Error', 'Invalid response from API.', 'error', $checkStatusErrorRedirect);
        exit;
    }

    $statusMessage = strtolower($data['statusMessage'] ?? '');
    $status = $data['status'] ?? '';

    $safeUsername = esc($ahk_conn, $username);
    $safeResponseForDb = esc($ahk_conn, $response);

    if ($statusMessage === 'refunded') {
        // Refund user
        $refund = mysqli_query($ahk_conn, "UPDATE users SET balance = balance + {$fee} WHERE phone = '{$safeUsername}' LIMIT 1");
        $freshUser = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT balance FROM users WHERE phone='{$safeUsername}' LIMIT 1"));
        $newBalance = isset($freshUser['balance']) ? $freshUser['balance'] : null;
        $log = mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('{$safeUsername}', '{$fee}', '{$newBalance}', 'All State Ration UID Find Refund', '1', 'Credit')");
        $updateStatus = mysqli_query($ahk_conn, "UPDATE ration2uid_find SET status='refunded', details='{$safeResponseForDb}' WHERE id='{$rowIdEsc}'");

        if ($refund && $log && $updateStatus) {
            swal_and_redirect('Refunded', 'Amount refunded successfully.', 'success', $checkStatusErrorRedirect);
            exit;
        } else {
            swal_and_redirect('Error', 'Refund failed.', 'error', $checkStatusErrorRedirect);
            exit;
        }
    } elseif ((string)$status === '100' || $status === 100) {
        // Completed
        $update = mysqli_query($ahk_conn, "UPDATE ration2uid_find SET details='{$safeResponseForDb}', status='completed' WHERE id='{$rowIdEsc}'");
        if ($update) {
            swal_and_redirect('Success', 'Details updated.', 'success', $checkStatusErrorRedirect);
            exit;
        } else {
            swal_and_redirect('Update Failed', 'Could not update Database.', 'error', $checkStatusErrorRedirect);
            exit;
        }
    } else {
        $error = $data['error'] ?? $data['message'] ?? 'Unknown error';
        // Redirect to ration2_uid_finder_all_state after user clicks OK
        swal_and_redirect($error, 'API Error', 'error', $checkStatusErrorRedirect);
        exit;
    }
}

// After handlers, fetch user's records for display
$resultData = mysqli_query($ahk_conn, "SELECT * FROM ration2uid_find WHERE username='" . esc($ahk_conn, $appliedby) . "' ORDER BY id ASC");

?>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Ration To Aadhaar Find All State</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-xl-4 mx-auto">
                <h6 class="mb-0 text-uppercase">Ration to Aadhaar Find</h6>
                <div class="card border-top border-0 border-4 border-primary">
                    <div class="card-body p-4">
            <form method="POST">
              <div class="form-group">
                <label>Ration Number</label>
                <input type="text" name="rationNo" id="rationNo" class="form-control"
                       placeholder="Enter Ration Number" minlength="4" maxlength="25" required />
              </div>
              <br>
              <button type="submit" name="submit_pan" class="btn btn-primary">Submit</button>
            </form>
            <p class="mt-2"><strong>Note:</strong> Wallet ₹<?php echo h($fee); ?> will be deducted per request.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-xl-12">
        <div class="card">
          <div class="card-body">
            <div class="table-responsive mt-3">
              <table id="historyTable" class="table table-bordered table-striped display nowrap" style="width:100%">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Ration</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php 
                $count = 1;
                if (mysqli_num_rows($resultData) > 0) {
                  while ($row = mysqli_fetch_assoc($resultData)) {
                    $statusLower = strtolower($row['status'] ?? '');
                    ?>
                    <tr>
                      <td><?php echo $count; ?></td>
                      <td><?php echo h($row['rationNo']); ?></td>
                      <td><?php echo strtoupper(h($row['status'])); ?></td>
                      <td><?php echo h($row['date']); ?></td>
                      <td>
                        <?php if ($statusLower === 'pending') { ?>
                          <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="check_status_id" value="<?php echo (int)$row['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-info">Check Status</button>
                          </form>
                        <?php } elseif ($statusLower === 'refunded') { ?>
                          <span class="badge bg-danger"><?php echo strtoupper(h($row['status'])); ?></span>
                        <?php } elseif ($statusLower === 'completed' || $statusLower === 'complete') { 
                            $detailsJsonEsc = h($row['details'] ?? '');
                          ?>
                          <button type="button"
                                  class="btn btn-sm btn-success btn-show-aadhaar"
                                  data-json="<?php echo $detailsJsonEsc; ?>">
                            Show Aadhaar
                          </button>
                        <?php } else { ?>
                          <span class="badge bg-secondary"><?php echo strtoupper(h($row['status'])); ?></span>
                        <?php } ?>
                      </td>
                    </tr>
                    <?php
                    $count++;
                  }
                }
                ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<!-- Vendor JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
  $('#historyTable').DataTable({
    responsive: true,
    dom: 'Bfrtip',
    buttons: ['copyHtml5','excelHtml5','csvHtml5','pdfHtml5','print'],
    pageLength: 10,
    lengthMenu: [5,10,25,50,100],
    order: [[0,"desc"]]
  });
});

$(document).on('click', '.btn-show-aadhaar', function () {
  let raw = $(this).attr('data-json') || '';
  if (!raw) {
    Swal.fire('No data', 'Details not available for this row.', 'info');
    return;
  }
  let payload;
  try { payload = JSON.parse(raw); }
  catch (e) {
    Swal.fire('Invalid data', 'Could not parse saved details JSON.', 'error');
    return;
  }

  const list = Array.isArray(payload.data) ? payload.data : [];
  const rowsHtml = list.map((item, idx) => {
    const name = (item && item.name) ? String(item.name) : '';
    const uid  = (item && item.uidNumber) ? String(item.uidNumber) : '';
    return `<tr>
      <td>${idx + 1}</td>
      <td>${$('<div>').text(name).html()}</td>
      <td>${$('<div>').text(uid).html()}</td>
    </tr>`;
  }).join('');

  const rationNumber = payload.rationNumber ? $('<div>').text(payload.rationNumber).html() : '';
  const count = (typeof payload.count === 'number') ? payload.count : list.length;
  const msg = payload.message ? $('<div>').text(payload.message).html() : '';
  const statusMsg = payload.statusMessage ? $('<span>').text(payload.statusMessage).html() : '';

  const html = `
    <div class="table-responsive">
      <div class="mb-2">
        <strong>Status:</strong> ${statusMsg}
      </div>
      <table class="table table-sm table-bordered">
        <thead>
          <tr><th>#</th><th>Name</th><th>UID Number</th></tr>
        </thead>
        <tbody>
          ${rowsHtml || `<tr><td colspan="3" class="text-center">No records</td></tr>`}
        </tbody>
      </table>
      <div class="mt-2">
        <strong>Ration No:</strong> ${rationNumber}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Count:</strong> ${count}
      </div>
    </div>
  `;

  Swal.fire({
    title: 'Aadhaar List',
    html: html,
    width: 720,
    confirmButtonText: 'Close'
  });
});
</script>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<?php include('../template/ahkweb/footer.php'); ?>
