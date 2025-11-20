<?php
define('CRON_LOG', '/var/log/loan_system/cron.log');
define('ALL_LOANS', '/var/log/loan_system/all_loans.log');
define('ALL_DOCS', '/var/log/loan_system/all_docs.log');

function log_message($message) {
    global $script_name;

    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp][$script_name] $message\n";
    file_put_contents(CRON_LOG, $formatted,  FILE_APPEND);
}

function audit_message($message, $loans = false, $docs = false) {
    global $script_name;

    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp][$script_name] $message\n";

    if ($loans) {
        file_put_contents(ALL_LOANS, $formatted);
    }

    if ($docs) {
        file_put_contents(ALL_DOCS, $formatted);
    }
}

