<?php
require_once __DIR__ . '/../backend/cron/helpers/db_helpers.php';
require_once __DIR__ . '/../backend/cron/helpers/file_helpers.php';
require_once __DIR__ . '/../backend/config/db.php';

$script_name = basename(__FILE__);
$dblink = get_dblink();

$fid = isset($_GET['fid']) ? (int)$_GET['fid'] : 0;

if ($fid > 0) {
    $data = get_file_content($dblink, $fid);

    update_last_access($dblink, $fid);
}

$target_dir = "/var/www/html/src/web/views/";
file_put_contents($target_dir . $data['filename'], $data['content']);
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
                <div class="panel-heading">Search Main Menu</div>
                <div class="panel-body">
                    <h3>File Info:</h3>
                    <?php
                        echo '<div>File Name: '.$data['filename'].'</div>';
                        echo '<div>Loan Number: '.$data['loan_number'].'</div>';
                        echo '<div>File Size: '.$data['size'].' bytes</div>';
                        echo '<div>Last Access: '. (isset($data['last_accessed_at']) ? $data['last_accessed_at'] : 'N/A') .'</div>';
                        echo '<div><a href="views/'.$data['filename'].'">Preview File</a></div>';
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
