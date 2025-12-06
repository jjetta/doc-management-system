<?php
require_once 'log_helpers.php';
require_once __DIR__ . '/../../config/db.php';

$script_name = basename(__FILE__);

function total_number_of_loans($dblink) {
    $query = "
        SELECT COUNT(DISTINCT l.loan_number) as total_loans
        FROM loans l 
        JOIN documents d ON l.loan_id = d.loan_id 
        WHERE d.uploaded_at >= '2025-11-01 00:00:00' 
          AND d.uploaded_at < '2025-11-21 00:00:00';
    ";

    $stmt = $dblink->prepare($query);
    if (!$stmt) {
        log_message("[DB ERROR][total_number_of_loans] Failed to prepare INSERT - " . $dblink->error);
        return null;
    }

    try {
        if (!$stmt->execute()) {
            log_message("[DB ERROR][get_or_create_doctype] Failed to execute SELECT - " . $dblink->error);
            return null;
        }

        $result = $stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][total_number_of_loans] Failed to get result - " . $dblink->error);
            return null;
        }

        $row = $result->fetch_assoc();
        if ($result) {
            $result->free();
        }

        return $row ? $row['total_loans'] : 0;

    } finally {
        $stmt->close();
    }
}

function get_all_loan_numbers($dblink) {
    $query = "

    ";
}


