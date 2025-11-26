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

.main-box ul {
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
                    <ul>
                        <li><h4>Total number of unique loan numbers generated with a printout of those loan numbers</h4></li>
                        <li>Total size of all documents received from the API and the average size of all documents across all loans</li>
                        <li>Total count of all documents received from the API and the average number of documents across all loans</li>
                        <li>For each loan number from number 1, 
                            <ul>
                                <li>Total number of documents received</li>
                                <li>Average size of all documents for the given loan number and state if this average is above or below the global average size from question 2</li>
                                <li>Compare the total number of documents for each loan to the global average and state if it is above or below average from question 3</li>
                            </ul>
                        </li>
                        <li>A complete loan is one that has at least one of all 12 document types
                            <ul>
                                <li>A list of all loan numbers that are missing at least one of these documents and which document(s) is missing</li>
                                <li>A list of all loan numbers that have all documents</li>
                                <li>A list of all loan numbers that received 0 documents</li>
                            </ul>
                        </li>
                        <li>List the total number of each document type received across all loan numbers</li>
                        <li>Error Logging:</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
