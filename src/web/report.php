<?php
require_once __DIR__ . '/../backend/cron/helpers/db_helpers.php';
require_once __DIR__ . '/../backend/cron/helpers/file_helpers.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/cron/helpers/reporting_helpers.php';

$script_name = basename(__FILE__);
$dblink = get_dblink();

$total = total_number_of_loans($dblink);
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Document Management Web Front End</title>
<link href="assets/css/bootstrap.css" rel="stylesheet">
<style>
.main-box {
    text-align: center;
    padding:20px;
    border-radius:5px;
    -moz-border-radius:5px ;
    -webkit-border-radius:5px;
    margin-bottom:40px;
}

.main-box panel-body {
    display: inline-block;
    text-align: left;
    max-width: 800px;
}
</style>
</head>
<body>
    <div class="row main-box">
        <h3>Document Management System</h3>
        <hr>
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">Report</div>
                    <div class="panel-body">
                    <?php
                        echo "<h4>Total Number of Loans: $total";
                    ?>
                    </div>
            </div>
        </div>
    </div>
</body>
</html>
