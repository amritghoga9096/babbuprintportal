<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_layout.php';

$db = db();

function fetch_admin_api_transactions(mysqli $db, bool $reseller = false): array
{
    $tables = [];
    if ($reseller) {
        $tables = [
            ['name' => 'api_reseller_requests', 'response_col' => 'upstream_response', 'has_extra_cols' => true],
            ['name' => 'rc_pdf_without_chip_requests', 'response_col' => 'api_response', 'has_extra_cols' => true],
        ];
    } else {
        $tables = [
            ['name' => 'api_service_requests', 'response_col' => 'api_response', 'has_extra_cols' => false],
        ];
    }

    $seenKeys = [];
    $items = [];
    foreach ($tables as $t) {
        $tableName = $t['name'];
        $responseCol = $t['response_col'];
        $hasExtra = (bool) $t['has_extra_cols'];

        if ($reseller) {
            $extraSelect = $hasExtra
                ? "k.api_key, r.request_method, r.requester_ip, r.user_agent, r.{$responseCol} AS raw_response"
                : "k.api_key, r.request_method, r.requester_ip, r.user_agent, r.{$responseCol} AS raw_response";
            $extraJoin = 'LEFT JOIN api_access_keys k ON k.id = r.api_access_key_id';
        } else {
            if ($hasExtra) {
                $extraSelect = "NULL AS api_key, r.request_method, r.requester_ip, r.user_agent, r.{$responseCol} AS raw_response";
            } else {
                $extraSelect = "NULL AS api_key, 'WEB' AS request_method, NULL AS requester_ip, NULL AS user_agent, r.{$responseCol} AS raw_response";
            }
            $extraJoin = '';
        }

        $whereSql = '';
        if ($tableName === 'rc_pdf_without_chip_requests') {
            if ($reseller) {
                $whereSql = ' WHERE COALESCE(r.api_access_key_id, 0) > 0 ';
            } else {
                $whereSql = ' WHERE COALESCE(r.api_access_key_id, 0) = 0 ';
            }
        }

        $sql = "
            SELECT
                r.id,
                r.created_at,
                r.processed_at,
                r.status_name,
                r.charge_amount,
                r.result_value,
                r.input_payload,
                s.service_name,
                s.slug,
                s.result_label,
                s.input_one_label,
                s.input_one_name,
                s.input_one_placeholder,
                s.input_one_type,
                s.input_two_label,
                s.input_two_name,
                s.input_two_placeholder,
                s.input_two_type,
                s.input_three_label,
                s.input_three_name,
                s.input_three_placeholder,
                s.input_three_type,
                u.id AS user_id,
                u.full_name,
                u.email,
                u.mobile,
                u.wallet_balance,
                {$extraSelect}
            FROM {$tableName} r
            INNER JOIN api_services s ON s.id = r.api_service_id
            INNER JOIN users u ON u.id = r.user_id
            {$extraJoin}
            {$whereSql}
            ORDER BY r.id DESC
        ";

        $result = $db->query($sql);
        if (!$result) {
            continue;
        }
        while ($row = $result->fetch_assoc()) {
            $rowId = (int) ($row['id'] ?? 0);
            $dedupKey = $tableName . '|' . $rowId;
            if (isset($seenKeys[$dedupKey])) {
                continue;
            }
            $seenKeys[$dedupKey] = true;
            $row['transaction_source'] = $reseller ? 'reseller' : 'tool';
            $row['result_value'] = api_service_resolve_request_result($row, $row);
            $items[] = $row;
        }
        $result->free();
    }

    return $items;
}

function fetch_admin_api_today_metrics(mysqli $db): array
{
    $todayUserSuccessful = (int) scalar_value($db, "SELECT COUNT(*) FROM api_service_requests WHERE LOWER(status_name) = 'success' AND DATE(created_at) = CURDATE()") + (int) scalar_value($db, "SELECT COUNT(*) FROM rc_pdf_without_chip_requests WHERE LOWER(status_name) = 'success' AND DATE(created_at) = CURDATE() AND COALESCE(api_access_key_id, 0) = 0");
    $todayResellerSuccessful = (int) scalar_value($db, "SELECT COUNT(*) FROM api_reseller_requests WHERE LOWER(status_name) = 'success' AND DATE(created_at) = CURDATE()") + (int) scalar_value($db, "SELECT COUNT(*) FROM rc_pdf_without_chip_requests WHERE LOWER(status_name) = 'success' AND DATE(created_at) = CURDATE() AND COALESCE(api_access_key_id, 0) > 0");
    $todayAmountDeducted = (float) scalar_value($db, "SELECT COALESCE(SUM(charge_amount), 0) FROM api_service_requests WHERE DATE(created_at) = CURDATE()") +
        (float) scalar_value($db, "SELECT COALESCE(SUM(charge_amount), 0) FROM api_reseller_requests WHERE DATE(created_at) = CURDATE()") +
        (float) scalar_value($db, "SELECT COALESCE(SUM(charge_amount), 0) FROM rc_pdf_without_chip_requests WHERE DATE(created_at) = CURDATE()");

    return [
        'today_user_successful' => $todayUserSuccessful,
        'today_reseller_successful' => $todayResellerSuccessful,
        'today_total_successful' => $todayUserSuccessful + $todayResellerSuccessful,
        'today_amount_deducted' => $todayAmountDeducted,
    ];
}

function admin_api_status_badge(string $status): string
{
    return match (strtolower($status)) {
        'success' => 'success',
        'pending' => 'warning',
        default => 'danger',
    };
}

$toolTransactions = fetch_admin_api_transactions($db, false);
$resellerTransactions = fetch_admin_api_transactions($db, true);
$transactions = array_merge($toolTransactions, $resellerTransactions);

$globalDedup = [];
$dedupedTransactions = [];
foreach ($transactions as $tx) {
    $slug = trim((string) ($tx['slug'] ?? ''));
    if ($slug === 'instant-rc-pdf-without-chip') {
        $rcno = '';
        $inp = @json_decode((string) ($tx['input_payload'] ?? ''), true);
        if (is_array($inp)) {
            $rcno = trim((string) ($inp['rcno'] ?? $inp['rc_no'] ?? $inp['rcNumber'] ?? ''));
        }
        if ($rcno === '') {
            if (preg_match('/\b(?:rc\s*no|rcno|rc\s*number)[:\s]*([A-Z0-9\-]+)/i', (string) ($tx['input_one_label'] ?? '') . ' ' . (string) ($tx['input_payload'] ?? ''), $m)) {
                $rcno = strtoupper(trim((string) ($m[1] ?? '')));
            }
        }
        $ts = date('Y-m-d H:i', (int) strtotime((string) ($tx['created_at'] ?? '0')));
        $key = $slug . '|' . $rcno . '|' . $ts;
        if (isset($globalDedup[$key])) {
            continue;
        }
        $globalDedup[$key] = true;
    }
    $dedupedTransactions[] = $tx;
}
$transactions = $dedupedTransactions;
unset($dedupedTransactions, $globalDedup);

usort($transactions, static function (array $a, array $b): int {
    $tsA = (int) strtotime((string) ($a['created_at'] ?? '0'));
    $tsB = (int) strtotime((string) ($b['created_at'] ?? '0'));
    if ($tsB !== $tsA) {
        return $tsB <=> $tsA;
    }
    $idA = (int) ($a['id'] ?? 0);
    $idB = (int) ($b['id'] ?? 0);
    return $idB <=> $idA;
});

$totalTransactions = count($transactions);
$successfulTransactions = count(array_filter($transactions, static fn (array $item): bool => strtolower((string) $item['status_name']) === 'success'));
$resellerCount = count($resellerTransactions);
$totalCharges = array_reduce($transactions, static fn (float $carry, array $item): float => $carry + (float) ($item['charge_amount'] ?? 0), 0.0);
$todayMetrics = fetch_admin_api_today_metrics($db);

admin_header('API Transactions', 'api-transactions');
?>
<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?php echo $totalTransactions; ?></h3>
                <p>Total API Transactions</p>
            </div>
            <div class="icon"><i class="fas fa-receipt"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>Rs. <?php echo number_format($totalCharges, 2); ?></h3>
                <p>Total Charges Deducted</p>
            </div>
            <div class="icon"><i class="fas fa-wallet"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?php echo $successfulTransactions; ?></h3>
                <p>Successful Transactions</p>
            </div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?php echo $resellerCount; ?></h3>
                <p>Reseller API Hits</p>
            </div>
            <div class="icon"><i class="fas fa-network-wired"></i></div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-gradient-warning">
            <div class="inner">
                <h3><?php echo $todayMetrics['today_reseller_successful']; ?></h3>
                <p>Today's Reseller Successful Hit</p>
            </div>
            <div class="icon"><i class="fas fa-network-wired"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-gradient-info">
            <div class="inner">
                <h3><?php echo $todayMetrics['today_user_successful']; ?></h3>
                <p>Today's User Successful Hit</p>
            </div>
            <div class="icon"><i class="fas fa-user-check"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-gradient-primary">
            <div class="inner">
                <h3><?php echo $todayMetrics['today_total_successful']; ?></h3>
                <p>Today's Total Successful Hit</p>
            </div>
            <div class="icon"><i class="fas fa-check-double"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-gradient-success">
            <div class="inner">
                <h3>Rs. <?php echo number_format($todayMetrics['today_amount_deducted'], 2); ?></h3>
                <p>Today's Total Amount Deducted</p>
            </div>
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">All API Transactions</h3>
    </div>
    <div class="card-body table-responsive">
        <table id="apiTransactionsTable" class="table table-bordered table-striped datatable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>User Details</th>
                    <th>API Service</th>
                    <th>Inputs</th>
                    <th>Result</th>
                    <th>Status</th>
                    <th>Charge</th>
                    <th>User Wallet</th>
                    <th>Extra</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $transaction): ?>
                <?php $apiSortTs = (int) strtotime((string) ($transaction['created_at'] ?? '0')); ?>
                <tr>
                    <td style="min-width:150px" data-order="<?php echo $apiSortTs; ?>">
                        <div><strong><?php echo e(date('d M Y', strtotime((string) $transaction['created_at']))); ?></strong></div>
                        <div class="small-muted"><?php echo e(date('h:i A', strtotime((string) $transaction['created_at']))); ?></div>
                        <div class="small-muted">#<?php echo strtoupper(($transaction['transaction_source'] === 'reseller' ? 'RAPI' : 'API') . (int) $transaction['id']); ?></div>
                    </td>
                    <td>
                        <?php if ((string) $transaction['transaction_source'] === 'reseller'): ?>
                            <span class="badge badge-warning">Reseller API</span>
                        <?php else: ?>
                            <span class="badge badge-info">User Tool API</span>
                        <?php endif; ?>
                    </td>
                    <td style="min-width:250px">
                        <div><strong><?php echo e((string) $transaction['full_name']); ?></strong></div>
                        <div class="small-muted">User ID: <?php echo (int) $transaction['user_id']; ?></div>
                        <div class="small-muted"><?php echo e((string) $transaction['email']); ?></div>
                        <div class="small-muted"><?php echo e((string) ($transaction['mobile'] ?: '-')); ?></div>
                    </td>
                    <td style="min-width:220px">
                        <div><strong><?php echo e((string) $transaction['service_name']); ?></strong></div>
                        <div class="small-muted"><?php echo e((string) $transaction['slug']); ?></div>
                    </td>
                    <td style="min-width:260px">
                        <div><?php echo e(api_service_input_summary($transaction, $transaction)); ?></div>
                    </td>
                    <td style="min-width:220px">
                        <?php $resultValue = (string) ($transaction['result_value'] ?? ''); ?>
                        <?php if (api_result_is_downloadable($resultValue)): ?>
                            <a href="<?php echo e($resultValue); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Download PDF</a>
                        <?php else: ?>
                            <div><?php echo e($resultValue !== '' ? $resultValue : '-'); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo e(admin_api_status_badge((string) $transaction['status_name'])); ?>">
                            <?php echo e(ucwords(str_replace('_', ' ', (string) $transaction['status_name']))); ?>
                        </span>
                    </td>
                    <td>Rs. <?php echo number_format((float) $transaction['charge_amount'], 2); ?></td>
                    <td>
                        <div class="font-weight-bold text-success">Rs. <?php echo number_format((float) $transaction['wallet_balance'], 2); ?></div>
                        <div class="small-muted">Current wallet balance</div>
                    </td>
                    <td style="min-width:260px">
                        <div class="small-muted mb-1">Method: <?php echo e((string) ($transaction['request_method'] ?: 'WEB')); ?></div>
                        <?php if (!empty($transaction['api_key'])): ?>
                            <div class="small-muted mb-1">API Key: <?php echo e((string) $transaction['api_key']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($transaction['requester_ip'])): ?>
                            <div class="small-muted mb-1">IP: <?php echo e((string) $transaction['requester_ip']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($transaction['processed_at'])): ?>
                            <div class="small-muted">Processed: <?php echo e(date('d M Y h:i A', strtotime((string) $transaction['processed_at']))); ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
$(function () {
    setTimeout(function () {
        var $apiTbl = $('#apiTransactionsTable');
        if ($apiTbl.length && $.fn.DataTable) {
            if ($.fn.DataTable.isDataTable($apiTbl[0])) {
                $apiTbl.DataTable().destroy();
            }
            $apiTbl.DataTable({
                pageLength: 10,
                responsive: true,
                order: [[0, 'desc']],
                displayStart: 0
            });
        }
    }, 50);
});
</script>
<?php admin_footer(); ?>
