<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/log_helpers.php';
require_once __DIR__ . '/db_helpers.php';

define('API_URL', 'https://cs4743.professorvaladez.com/api/');

$script_name = basename(__FILE__);

$dblink = get_dblink();

function api_call($endpoint, $data, $octet = false, $audit = false) {
    log_message("Calling endpoint: " . API_URL . $endpoint);

    $ch = curl_init(API_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($data)
        ],
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        log_message("CURL ERROR: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    if ($octet) { // Not decoding because this would be raw pdf data
        return $response;
    }

    $response_info = json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Invalid JSON response: " . json_last_error_msg());
        return false;
    }

    if ($audit) {
        log_message("[API RESPONSE]");
        log_message($response_info[0]);
        echo str_repeat("-", 100) . "\n";
        return $response_info;
    }

    log_message("[API RESPONSE]");
    foreach ($response_info as $info) {
        log_message($info);
    }
    echo str_repeat("-", 100) . "\n";

    return $response_info;
}

function create_session($dblink) {

    $data = http_build_query([
        'username' => getenv('API_USER'),
        'password' => getenv('API_PASS')
    ]);

    $response = api_call('create_session', $data);

    if ($response[0] !== "Status: OK") {
        log_message("Failed to create session. $response[2]");

        if ($response[1] === "MSG: Previous Session Found" || $response[1] === "MSG: SID not found") {
            api_call('clear_session', $data);

            log_message("[RETRY] Retrying create_session...");
            $response = api_call('create_session', $data);
        }
    }

    // Happy path at the bottom
    if ($response[0] === "Status: OK") {
        db_save_session($dblink, $response[2]);
    } else {
        log_message("[FATAL] Session creation ultimately failed.");
    }
}

function reconnect($dblink) {

    $data = http_build_query([
        'username' => getenv('API_USER'),
        'password' => getenv('API_PASS')
    ]);

    //clear session
    log_message("[RECONNECT] Clearing session...");
    api_call('clear_session', $data);

    //create session
    log_message("[RECONNECT] Creating a new session...");
    $response = api_call('create_session', $data);

    if (!is_array($response) ||
        count($response) < 3 ||
        $response[0] !== 'Status: OK') {
        log_message("[FATAL][RECONNECT] Attempt to reconnect ultimately failed.");
        return [
            'success' => false,
            'sid' => null
        ];
    }

    $session_id = $response[2];
    try {
        db_save_session($dblink, $session_id);
    } catch (Exception $e){
        log_message("[ERROR][RECONNECT] Failed to save session: " . $e->getMessage());
        return [
            'success' => false,
            'sid' => null
        ];
    }

    log_message("[RECONNECT] Successfully re-established connection");
    return [
        'success' => true,
        'sid' => $session_id
    ];
}

