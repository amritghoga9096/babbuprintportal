<?php
include('../template/ahkweb/header.php');

$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='Pin_code_Fee' "));
$fee = $price['price'];

$resdata = [];
$show_form = true;  // Flag to control form visibility

if (isset($_POST['search_value'])) {
    $search_value = trim($_POST['search_value']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        $api_key = urlencode($flexsecure_key);
        $url = "https://{$flexsecureapiurl}/api/v1/PincodeVerification?apiKey={$api_key}&pincode={$search_value}";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $resdata = json_decode($response, true) ?? [];

        if (isset($resdata['status']) && $resdata['status'] === "100") {
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-{$fee} WHERE phone='{$appliedby}'");
            mysqli_query($ahk_conn, "
                INSERT INTO wallethistory(userid, amount, balance, purpose, status, type)
                VALUES ('{$appliedby}','{$fee}','{$debit_fee}','Pincode Verification','1','Debit')");
            $show_form = false; // Hide form on success
        } else {
            $error_message = $resdata['message'] ?? 'Something went wrong.';
            echo "<script>$(function(){ Swal.fire('Error', '{$error_message}', 'error') }); setTimeout(() => { window.location=''; }, 5000);</script>";
        }
    } else {
        echo "<script>$(function(){ Swal.fire('Low Balance', 'Please recharge your wallet.', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    }
}
?>

<head>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
  <!-- jsPDF and AutoTable plugin for PDF generation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Pin Code & Post Office Verify</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

    <div class="container-fluid pt-4 px-4">
      <div class="row g-4">

        <!-- Show form only if $show_form == true -->
        <?php if ($show_form): ?>
         <div class="col-lg-5">
          <div class="card h-100">
            <div class="card-body">
              <h3 class="text-center mb-4"><strong>Pincode Verification</strong></h3>
              <form method="POST" class="row g-3">
                <div class="col-12">
                  <label for="search_value" class="form-label">Enter Pincode</label>
                  <input name="search_value" type="text" id="search_value"
                    class="form-control" placeholder="Enter Pincode or Area Name"
                    required title="Enter a pincode or area name" autofocus>
                </div>

                <div class="col-12 mt-2">
                  <div class="row">
                    <div class="col-md-6">
                      <input class="form-control" value="Fee ₹ <?=htmlspecialchars($fee)?>" readonly>
                    </div>
                    <div class="col-md-6 text-end">
                      <button class="btn btn-success" type="submit">
                        <i class="fa fa-check-circle"></i> Submit
                      </button>
                    </div>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Show full width result if search successful -->
        <?php if (!$show_form && !empty($resdata['data']) && is_array($resdata['data'])): ?>
        <div class="col-12">
          <div class="card h-100 bg-light" id="printSection">
            <div class="card-body">
              <h4 class="mb-3">Pincode Verification Result</h4>
              <div class="table-responsive">
                <table class="table table-bordered table-striped" id="resultTable">
                  <thead class="table-dark">
                    <tr>
                      <th>Name</th>
                      <th>Description</th>
                      <th>Branch Type</th>
                      <th>Delivery Status</th>
                      <th>Circle</th>
                      <th>District</th>
                      <th>Division</th>
                      <th>Region</th>
                      <th>Block</th>
                      <th>State</th>
                      <th>Country</th>
                      <th>Pincode</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($resdata['data'] as $item): ?>
                    <tr>
                      <td><?=htmlspecialchars($item['Name'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['Description'] ?? 'N/A')?></td>
                      <td><?=htmlspecialchars($item['BranchType'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['DeliveryStatus'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['Circle'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['District'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['Division'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['Region'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['Block'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['State'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['Country'] ?? '')?></td>
                      <td><?=htmlspecialchars($item['Pincode'] ?? '')?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="text-end mt-3">
                <!-- Print button -->
                <button class="btn btn-primary me-2" onclick="printVerification()">🖨️ Print</button>

                <!-- PDF Download button -->
                <button class="btn btn-danger" onclick="downloadPDF()">📄 Download PDF</button>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<?php include('footer.php'); ?>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<script>
// Print function - landscape mode
function printVerification() {
  const content = document.getElementById("printSection").innerHTML;
  const w = window.open('', '_blank', 'width=1200,height=800');
  w.document.write(`
    <html><head><title>Pincode Results</title>
    <style>
      @page { size: landscape; }
      body { font-family: Arial; padding: 20px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #000; padding: 8px; text-align: left; }
      th { background: #f2f2f2; }
    </style>
    </head><body>${content}
    <script>window.onload = () => window.print();<\/script>
    </body></html>
  `);
  w.document.close();
}

// PDF generation using jsPDF + autotable
async function downloadPDF() {
  const { jsPDF } = window.jspdf;

  const doc = new jsPDF({
    orientation: 'landscape',
    unit: 'pt',
    format: 'a4'
  });

  doc.setFontSize(18);
  doc.text("Pincode Verification Result", 40, 40);

  // Prepare headers and data from table
  const table = document.getElementById('resultTable');
  const headers = [];
  const data = [];

  // Extract headers
  table.querySelectorAll('thead tr th').forEach(th => {
    headers.push(th.innerText.trim());
  });

  // Extract rows data
  table.querySelectorAll('tbody tr').forEach(tr => {
    const row = [];
    tr.querySelectorAll('td').forEach(td => {
      row.push(td.innerText.trim());
    });
    data.push(row);
  });

  doc.autoTable({
    head: [headers],
    body: data,
    startY: 60,
    styles: { fontSize: 9 },
    headStyles: { fillColor: [41, 128, 185] },
    theme: 'grid',
  });

  doc.save('Pincode_Verification_Result.pdf');
}
</script>
