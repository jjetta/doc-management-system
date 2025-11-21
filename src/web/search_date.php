<?php
require_once __DIR__ . '/../backend/cron/helpers/db_helpers.php';
require_once __DIR__ . '/../backend/cron/helpers/file_helpers.php';
require_once __DIR__ . '/../backend/config/db.php';

$script_name = basename(__FILE__);
$dblink = get_dblink();
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Document Management Web Front End</title>
<link href="assets/css/bootstrap.css" rel="stylesheet">
<style>
.main-box {
    text-align:center;
    padding:20px;
    border-radius:5px;
    -moz-border-radius:5px ;
    -webkit-border-radius:5px;
    margin-bottom:40px;
}
</style>
</head>
<body>
    <div class="row main-box">
        <h3>Document Management System</h3>
        <hr>
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">Search by Date</div>
                <div class="panel-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label class="control-label">Enter Two Dates</label>
                            <h5>Date One</h5>
                            <input type="date" name="date1" class="form-control">
                            <h5>Date Two</h5>
                            <input type="date" name="date2" class="form-control">
                        </div>
                        <div class="form-group">
                            <button type="submit" name="submit" value="submit" class="btn btn-success">Submit</button>
                    </form>
                    <?php 
                        if (isset($_POST['submit']) && $_POST['submit'] === 'submit') {
                            $date1 = $_POST['date1'];
                            $date2 = $_POST['date2'];

                            $documents = get_by_date($dblink, $date1, $date2);

                            echo '<hr>';
                            echo '<table class="table table-striped">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>Loan ID</th>';
                            echo '<th>File Name</th>';
                            echo '<th>File Size</th>';
                            echo '<th>Document Type</th>';
                            echo '<th>Last Access</th>';
                            echo '<th>Action</th>';
                            echo '</tr>';
                            echo '</thead>';

                            echo '<tbody>';
                            if (is_array($documents) && count($documents) > 0) {
                                foreach ($documents as $data) {
                                    $timestamp = date('Ymd_H_i_s', strtotime($data['uploaded_at']));
                                    $filename = "{$data['loan_number']}-{$data['doc_name']}-$timestamp.pdf";

                                    echo '<tr>';
                                    echo '<td>'.$data['loan_number'].'</td>';
                                    echo '<td>'.$data['filename'].'</td>';
                                    echo '<td>'.$data['size'].'</td>';
                                    echo '<td>'.$data['doctype'].'</td>';
                                    echo '<td>'. (isset($data['last_accessed_at']) ? $data['last_accessed_at'] : 'N/A') .'</td>';
                                    echo '<td><a href="search_view.php?fid='.$data['document_id'].'">View</a></td>';
                                    echo '</tr>';
                                }
                            }
                            echo '</tbody>';
                            echo '</table>';
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
