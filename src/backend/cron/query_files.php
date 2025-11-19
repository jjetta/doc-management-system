<?php
require_once __DIR__ . '/helpers/api_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers/log_helpers.php';
require_once __DIR__ . '/helpers/file_helpers.php';
require_once __DIR__ . '/helpers/db_helpers.php';

$script_name = basename(__FILE__);

$dblink = get_dblink();

$username = getenv('API_USER');
$sid = get_session($dblink); // sid = session id

$data = http_build_query([
    'uid' => $username,
    'sid' => $sid
]);

$response = api_call('query_files', $data);

// retry in the event our session_id got kicked or a timeout
if (!$response || 
    !is_array($response) || 
    $response[1] === 'MSG: SID not found') {
    $retry = reconnect($dblink);

    if ($retry['success']) {
        log_message("[INFO] Retrying query_files...");
        $sid = $retry['sid'];
    }

    $data = http_build_query([
        'uid' => $username,
        'sid' => $sid
    ]);

    $response = api_call('query_files', $data);
}

// if there's no files, just exit
if ($response[1] === 'MSG: No new files found' || $response[1] === 'MSG: []') {
    log_message("[INFO] No files to query. Moving along.");
    echo str_repeat("-", 100) . "\n";
    exit(0);
}

if ($response[0] === 'Status: OK') {
    $files = parse_file_list($response);
} else {
    log_message("[ERROR] API returned unexpected status or format.");
    exit(1);
}

log_message("[INFO] Processing files...");
foreach ($files as $file) {
    $file_parts = explode('-', $file);

    // Validate filename format and type
    if (count($file_parts) !== 3 || !str_ends_with($file, '.pdf')) {
        log_message("Skipping invalid filename: $file");
        continue;
    }

    [$loan_number, $docname, $timestamp] = $file_parts;

    // Update document_types table if necessary
    $doctype_id = get_or_create_doctype($dblink, $docname);

    // Update loans table if necessary
    $loan_id = get_or_create_loan($dblink, $loan_number);
    if ($loan_id === null) {
        log_message("[ERROR] Could not ensure loan exists for $loan_number");
        continue;
    }

    // prepare the timestamp for insertion into the db
    $mysql_ts = get_mysql_ts($timestamp);

    // Update documents table with file metadata
    save_file_metadata($dblink, $loan_id, $doctype_id, $mysql_ts, $docname);
}
log_message("[INFO] Processing complete.");
echo str_repeat("-", 100) . "\n";
