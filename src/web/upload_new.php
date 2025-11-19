<?php
require_once __DIR__ . '/../backend/cron/helpers/db_helpers.php';
require_once __DIR__ . '/../backend/cron/helpers/file_helpers.php';
require_once __DIR__ . '/../backend/config/db.php';

$script_name = basename(__FILE__);

$dblink = get_dblink();

$query = "SELECT doctype FROM document_types ORDER BY doctype ASC";
$result = $dblink->query($query) or die("Query failed: " . $dblink->error);

$document_types = [];
while ($row = $result->fetch_assoc()) {
    $document_types[] = $row['doctype'];
}

$input_loan_number = '';
$loan_error = '';

$input_doctype = '';
$doctype_error = '';

$file_error = '';

if (isset($_POST['submit']) && $_POST['submit'] == "submit") {
    $input_loan_number = trim($_POST['loanId']);
    $input_doctype = trim($_POST['docType']);

    if (empty($input_loan_number)) {
        $loan_error = 'Loan number cannot be empty!';
    } elseif (!ctype_digit($input_loan_number)) {
        $loan_error = 'Loan number must only contain digits!';
    } elseif (strlen($input_loan_number) > 9) {
        $loan_error = 'Loan number cannot be more than 9 digits!';
    }

    if (empty($input_doctype)) {
        $doctype_error = 'You gotta choose a doctype!';
    }

    $file_name     = $_FILES['userfile']['name']; // name of the file uploaded
    $file_location = $_FILES['userfile']['tmp_name']; // location of the file on the server
    $file_size     = $_FILES['userfile']['size']; // size of the file uploaded by user 

    if (empty($file_location) || !is_uploaded_file($file_location)) {
        $file_error = 'Please select a file to upload!';
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_location);
        finfo_close($finfo);

        if ($mime !== 'application/pdf') {
            $file_error = 'The uploaded file is not a valid PDF!';
        }
    }
    

    if (empty($loan_error) && empty($doctype_error) && empty($file_error)) {
        $fp = fopen($file_location, 'r'); // open file location of uploaded file for reading
        $file_content = fread($fp, filesize($file_location));
        fclose($fp); // CLOSE THE FILE POINTER, or else you will run out of memory.

        $loan_number = str_pad($input_loan_number, 9, '0', STR_PAD_LEFT);
        $loan_id = get_or_create_loan($dblink, $loan_number);
        $doctype = $input_doctype;
        $doctype_id = get_or_create_doctype($dblink, $doctype);
        $uploaded_at = get_mysql_ts(date('Ymd_H_i_s'));

        $document_id = save_file_metadata($dblink, $loan_id, $doctype_id, $uploaded_at, $doctype);
        db_write_doc($dblink, $document_id, $file_content);

        echo '<div class="alert alert-success">Document uploaded successfully!</div>';
    }
    
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Document Management Web Front End</title>
<link href="assets/css/bootstrap.css" rel="stylesheet">
<link href="assets/css/bootstrap-fileupload.min.css" rel="stylesheet">
<script src="assets/js/jquery-1.10.2.js"></script>
<script src="assets/js/bootstrap.js"></script>
<script src="assets/js/bootstrap-fileupload.js"></script>
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
        <div class="panel-heading">Upload New Loan ID</div>
        <div class="panel-body">
            <h3>Please fill out the form below.</h3>
            <hr>
            <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="MAX_FILE_SIZE" value="5000000">
                <div class="form-group<?php echo !empty($loan_error) ? ' has-error' : ''; ?>">
                    <label class="control-label">Loan Number:</label>
                    <input class="form-control" name="loanId" type="text" value="<?php echo htmlspecialchars($input_loan_number); ?>">
                    <?php if (!empty($loan_error)): ?>
                    <span class="help-block"><?php echo htmlspecialchars($loan_error); ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group<?php echo !empty($doctype_error) ? ' has-error' : ''; ?>">
                    <label class="control-label">Document Type:</label>
                    <select name="docType" class="form-control">
                        <option value="">Select Document Type</option>
                        <?php foreach ($document_types as $doctype): ?>
                        <option value="<?php echo htmlspecialchars($doctype); ?>"
                            <?php echo ($input_doctype == $doctype) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doctype); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($doctype_error)): ?>
                        <span class="help-block"><?php echo htmlspecialchars($doctype_error); ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group<?php echo !empty($file_error) ? ' has-error' : ''; ?>">
                    <label class="control-label">File Upload</label>
                    <div class="">
                        <div class="fileupload fileupload-new" data-provides="fileupload">
                            <div class="fileupload-preview thumbnail" style="width:200px; height:150px"></div>
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="btn btn-file btn-primary">
                                        <span class="fileupload-new">Select File</span>
                                        <span class="fileupload-exists">Change</span>
                                        <input name="userfile" type="file">
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <a href="#" class="btn btn-danger fileupload-exists" data-dismiss="fileupload">Remove</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($file_error)): ?>
                        <span class="help-block"><?php echo htmlspecialchars($file_error); ?></span>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="form-group">
                    <button type="submit" name="submit" value="submit" class="btn btn-lg btn-block btn-success">Upload File</button>
                </div>
            </form>
        </div>
    </div>
        </div>
    </div>
</body>
</html>
