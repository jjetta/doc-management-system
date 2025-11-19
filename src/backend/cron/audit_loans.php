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

$response = api_call('request_all_loans', $data);

if (!$response ||
    !is_array($response) ||
    $response[1] === 'MSG: SID not found') {
    $retry = reconnect($dblink);

    if ($retry['success']) {
        log_message("[INFO] Retrying request_all_loans...");
        $sid = $retry['sid'];
    }

    $data = http_build_query([
        'uid' => $username,
        'sid' => $sid
    ]);

    $response = api_call('request_all_loans', $data);
}


$all_generated_loans = parse_loan_list($response);
$current_loans = get_current_loans($dblink);

$missing_loans = array_diff($all_generated_loans, $current_loans);

if (!empty($missing_loans)) {
    $found_loans = 0;
    log_message("Number of missing loans: " . count($missing_loans));
    log_message("Missing loans: " . print_r($missing_loans, true));

    foreach ($missing_loans as $loan_number) {
        if (get_or_create_loan($dblink, $loan_number) != null) {
            $found_loans++;
        };
    }

} else {
    log_message("You're up to date on all loans. All good!");
}

echo str_repeat("-", 100) . "\n";
