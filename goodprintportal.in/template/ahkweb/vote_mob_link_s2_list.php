<?php include("header.php"); 
$s_phone=$udata['phone'];
$res = mysqli_query($ahk_conn, "SELECT * FROM `m_link_s2` WHERE userid='$s_phone' ORDER BY `id` DESC");
?>
<script>
    $(document).ready(() => {
        $('#default-datatable').DataTable();
    });
</script>
<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Mobile Link S2 List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="voter_link_s2" class="btn btn-success">
                    <i class="bi bi-link-45deg me-1"></i> Voter Mobile Link S2
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                </div>
                <hr>
<div class="content-wrap">
    <div class="main">
        <div class="col-md-12">
            <div class="main-content">
                <section class="section">
                    <div class="card-header bg-dark">
                        <div class="card-title">
                            <h3 style="color:white"><strong>Mobile Link S2 List </strong></h3>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="default-datatable" class="table table-bordered" width="100%">
                                            <thead style="color:white;background:linear-gradient(-62deg, #0d47a1, #03a9f4);">
                                                <tr>
                                                    <th style="color:white">#</th>
                                                    <th style="color:white; text-align:center;">Epic No</th>
                                                    <th style="color:white; text-align:center;">Link Mobile</th>
                                                    <th style="color:white; text-align:center;">Status Code</th>
                                                    <th style="color:white; text-align:center;">Status</th>
                                                    <th style="color:white; text-align:center;">Reference ID</th>
                                                    <th style="color:white; text-align:center;">Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                 <?php
                                                $sql = mysqli_query($ahk_conn, "SELECT * FROM m_link_s2 WHERE userid='$s_phone' order by id DESC");
                                                if (mysqli_num_rows($sql) > 0) {
                                                    $slno = 1;
                                                    while ($data = mysqli_fetch_assoc($sql)) {
                                                        $status_code = isset($data['status_code']) ? htmlspecialchars($data['status_code']) : '-';
                                                        $ref_id = isset($data['reference_id']) ? htmlspecialchars($data['reference_id']) : '-';
                                                        $scolor = 'inherit';
                                                        if ($status_code === '100' || $status_code === 'SUCCESS') {
                                                            $scolor = '#16a34a';
                                                        } elseif ($status_code === '200' || $status_code === 'FAILED' || $status_code === 'API_ERROR') {
                                                            $scolor = '#dc2626';
                                                        }
                                                ?>
                                                        <tr>
                                                            <td><?php echo $slno; ?></td>
                                                            <td style="text-align:center;"><?php echo $data['epic_no']; ?></td>
                                                            <td style="text-align:center;"><?php echo $data['mobile_no']; ?></td>
                                                            <td style="text-align:center; font-weight:700; color:<?php echo $scolor; ?>;"><?php echo $status_code; ?></td>
                                                            <td style="text-align:center;"><?php echo $data['response']; ?></td>
                                                            <td style="text-align:center;"><?php echo $ref_id; ?></td>
                                                            <td style="text-align:center;"><?php echo $data['date']; ?></td>
                                                        </tr>
                                                <?php
                                                        $slno++;
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
                </section>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        new ClipboardJS('.btn');
    });
</script>

<?php 
include('footer.php');
?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery.easy-pie-chart/jquery.easypiechart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/sparkline-chart/jquery.sparkline.min.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/excanvas.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
<script>
$(function() {
    $(".knob").knob();
});
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
