<?php
require_once __DIR__ . '/helpers/api_helpers.php';
require_once __DIR__ . '/helpers/file_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers/log_helpers.php';
require_once __DIR__ . '/helpers/db_helpers.php';

$script_name = basename(__FILE__);

$start_time = time();
$dblink = get_dblink();

$sid = create_session();
$username = getenv('API_USER');

$downloaded = 0;
$failed = 0;

// get the top 100 docs from the db whose status is pending or failed and 
// store them in an associative array where document_id => filename
$pending_docs = get_pending_docs($dblink);

if (empty($pending_docs)) {
    log_message("No documents are pending for download. All good!");
    exit(0);
}

log_message("[INFO] Downloading files...");
log_message(str_repeat('-', 45));
foreach ($pending_docs as $document_id => $filename) {
    log_message("");
    log_message("[DOC #$document_id] " . str_repeat('=', 60));
    $data = http_build_query([
        'sid' => $sid,
        'uid' => $username,
        'fid' => $filename
    ]);

    $content = api_call('request_file', $data, octet: true);
    $mime = $content ? get_mime_type($content) : '';

    if (!$content || $mime !== "application/pdf") {
        log_message("[WARN] Failed to download document #$document_id.");

        $failed++;
        mark_as_failed($dblink, $document_id);

        if ($mime === "application/json") { //if we're getting json back from the api, we likely need to create another session
            log_message("[INFO] Detected possible session expiration. Attempting to reconnect...");
            $retry = reconnect($dblink);

            if ($retry['success']) {
                $sid = $retry['sid'];
            } else {
                log_message("[FATAL] Reconnect failed. Terminating batch.");
                log_message("[DOC #$document_id] " . str_repeat('=', 60));
                break;
            }
        }

        log_message("[DOC #$document_id] " . str_repeat('=', 60));
        log_message("");
        continue;
    }

    if (db_write_doc($dblink, $document_id, $content)) {
        $downloaded++;
    } else {
        $failed++;
        mark_as_failed($dblink, $document_id);
    }

    log_message("[DOC #$document_id] " . str_repeat('=', 60));
    log_message("");
}

close_session($sid);
$elapsed = time() - $start_time;
$total_docs = count($pending_docs);

echo str_repeat("-", 100) . "\n";
log_message("[INFO]    Batch complete.");
log_message("[METRICS] Downloaded: $downloaded");
log_message("[METRICS] Failed: $failed");
log_message("[METRICS] Total processed: " . ($downloaded + $failed) . "/$total_docs");
log_message("[METRICS] Success ratio: " . $downloaded . "/$total_docs");
log_message("[METRICS] Execution time: {$elapsed}s");
log_message("[METRICS] Average processing time per file: " . round($elapsed / $total_docs, 2) . "s");
echo str_repeat("-", 100) . "\n";
