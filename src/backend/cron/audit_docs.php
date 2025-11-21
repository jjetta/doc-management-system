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

$response = api_call('request_all_documents', $data, audit: true);

if (!$response ||
    !is_array($response) ||
    $response[1] === 'MSG: SID not found') {
    $retry = reconnect($dblink);

    if ($retry['success']) {
        log_message("[INFO] Retrying request_all_documents...");
        $sid = $retry['sid'];
    }

    $data = http_build_query([
        'uid' => $username,
        'sid' => $sid
    ]);

    $response = api_call('request_all_documents', $data, audit: true);
}

$all_generated_docs = parse_file_list($response, audit: true);
/* $current_docs = get_current_docs($dblink); */


/* $missing_docs = array_diff($all_generated_docs, $current_docs); */

/* if (!empty($missing_docs)) { */
/*     $found_docs = 0; */
/*     log_message("Number of missing docs: " . count($missing_docs)); */
/*     log_message("Missing docs: " . print_r($missing_docs, true)); */
/**/
/*     foreach ($missing_docs as $loan_number) { */
/*         if (get_or_create_loan($dblink, $loan_number) != null) { */
/*             $found_docs++; */
/*         }; */
/*     } */
/**/
/* } else { */
/*     log_message("You're up to date on docs. All good!"); */
/* } */

echo str_repeat("-", 100) . "\n";
