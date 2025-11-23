<?php
require_once __DIR__ . '/helpers/api_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers/log_helpers.php';
require_once __DIR__ . '/helpers/file_helpers.php';
require_once __DIR__ . '/helpers/db_helpers.php';

$script_name = basename(__FILE__);

$dblink = get_dblink();

$username = getenv('API_USER');

log_message("Creating session...");
$sid = create_session(); // sid = sessiond id

$data = http_build_query([
    'uid' => $username,
    'sid' => $sid
]);

$response = api_call('request_all_documents', $data, audit: true);

if (!$response ||
    !is_array($response) ||
    $response[1] === 'MSG: SID not found') {
    $retry = reconnect($dblink);

    if ($retry['success']) {
        log_message("[INFO] Retrying request_all_documents...");
        $sid = $retry['sid'];
    } else {
        log_message('Terminating script...');
        echo str_repeat("-", 100) . "\n";
        exit(1);
    }

    $data = http_build_query([
        'uid' => $username,
        'sid' => $sid
    ]);

    $response = api_call('request_all_documents', $data, audit: true);
}

$all_generated_docs = parse_file_list($response, audit: true);
$current_docs = audit_docs($dblink);

$missing_docs = array_diff($all_generated_docs, $current_docs);

if (!empty($missing_docs)) {
    log_message("Number of missing docs: " . count($missing_docs));
    log_message("Missing docs: " . print_r($missing_docs, true));

    process_files($dblink, $missing_docs);
} else {
    log_message("You're up to date on docs. All good!");
}

log_message("Closing session...");
close_session($sid);

echo str_repeat("-", 100) . "\n";
