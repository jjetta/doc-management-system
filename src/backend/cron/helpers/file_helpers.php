<?php
require_once 'log_helpers.php';
require_once __DIR__ . '/../../config/db.php';

$script_name = basename(__FILE__);

function parse_file_list($response, $audit = false) {
    log_message("Parsing files...");

    $tmp = explode(":", $response[1]);
    $files = json_decode($tmp[1]);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("[ERROR] Failed to decode file list. JSON error: " . json_last_error_msg());
        return [];
    }

    if (empty($files)) {
        log_message("[query_files] No files returned by API");
    } else {
        log_message("[INFO] Number of files received: " . count($files));
    }

    if (!$audit) {
        log_message("[INFO] Files received: " . print_r($files, true));
    } else {
        audit_message("All generated docs: " . print_r($files, true), docs: true);
    }

    return $files;
}

function parse_loan_list($response) { // this function is strictly for auditing (making sure i'm not missing any loans)
    log_message("Parsing loan IDs...");

    $tmp = explode(":", $response[1]);
    $loans = json_decode($tmp[1]);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("[ERROR] Failed to decode loan list. JSON error: " . json_last_error_msg());
        return [];
    }

    if (empty($loans)) {
        log_message("[INFO] No loans returned by API");
    } else {
        log_message("[INFO] Total loan count: " . count($loans));
    }

    audit_message("All generated loans: " . print_r($loans, true), loans: true);

    return $loans;
}

function get_doctype_from_filename($doctype) {
    // Remove trailing underscore + number
    $doctype = preg_replace('/_\d+$/', '', $doctype);

    // Remove trailing 's' (case-insensitive)
    $doctype = preg_replace('/s$/i', '', $doctype);

    // Replace underscores inside the name with spaces
    $doctype = str_replace('_', ' ', $doctype);

    return $doctype;
}

function get_mysql_ts($raw_ts) {
    // Remove the file extension if present
    $raw_ts = pathinfo($raw_ts, PATHINFO_FILENAME);

    // Validate input
    if (!$raw_ts) {
        return null;
    }

    // Use regex to ensure correct format: YYYYMMDD_HH_MM_SS
    if (!preg_match('/^(\d{8})_(\d{2})_(\d{2})_(\d{2})$/', $raw_ts, $matches)) {
        return null; // invalid format
    }

    $date_part = $matches[1]; // YYYYMMDD
    $hour = $matches[2];
    $minute = $matches[3];
    $second = $matches[4];

    // Build MySQL TIMESTAMP string
    $mysql_ts = substr($date_part, 0, 4) . '-' . substr($date_part, 4, 2) . '-' . substr($date_part, 6, 2)
                . ' ' . $hour . ':' . $minute . ':' . $second;

    return $mysql_ts;
}

function get_mime_type($content) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimetype = finfo_buffer($finfo, $content);
    finfo_close($finfo);

    return $mimetype;
}

function process_file($dblink, $file) {

    $file_parts = explode('-', $file);

    // Validate filename format and type
    if (count($file_parts) !== 3 || !str_ends_with($file, '.pdf')) {
        log_message("Skipping invalid filename: $file");
        return;
    }

    [$loan_number, $docname, $timestamp] = $file_parts;

    // Update document_types table if necessary
    $doctype = get_doctype_from_filename($docname);
    $doctype_id = get_or_create_doctype($dblink, $doctype);

    // Update loans table if necessary
    $loan_id = get_or_create_loan($dblink, $loan_number);
    if ($loan_id === null) {
        log_message("[ERROR] Could not ensure loan exists for $loan_number");
        return;
    }

    // prepare the timestamp for insertion into the db
    $mysql_ts = get_mysql_ts($timestamp);

    // Update documents table with file metadata
    save_file_metadata($dblink, $loan_id, $doctype_id, $mysql_ts, $docname);
}
