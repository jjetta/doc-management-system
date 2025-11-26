<?php
require_once __DIR__ . '/helpers/api_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers/log_helpers.php';
require_once __DIR__ . '/helpers/file_helpers.php';
require_once __DIR__ . '/helpers/db_helpers.php';

$script_name = basename(__FILE__);

$dblink = get_dblink();

$username = getenv('API_USER');
$sid = create_session(); // sid = session id

$data = http_build_query([
    'uid' => $username,
    'sid' => $sid
]);

$response = api_call('query_files', $data);

// retry in the event our session_id got kicked or a timeout
if (!$response || 
    !is_array($response) || 
    $response[1] === 'MSG: SID not found') {
    $retry = reconnect();

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
    process_file($dblink, $file);
}
log_message("[INFO] Processing complete.");

close_session($sid);
echo str_repeat("-", 100) . "\n";
