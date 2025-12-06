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
        SELECT DISTINCT l.loan_number
        FROM loans l 
        JOIN documents d ON l.loan_id = d.loan_id 
        WHERE d.uploaded_at >= '2025-11-01 00:00:00' 
          AND d.uploaded_at < '2025-11-21 00:00:00'
        ORDER BY l.loan_number;
    ";

    $stmt = $dblink->prepare($query);
    if (!$stmt) {
        log_message("[DB ERROR][get_all_loan_numbers] Failed to prepare - " . $dblink->error);
        return null;
    }

    try {
        if (!$stmt->execute()) {
            log_message("[DB ERROR][get_all_loan_numbers] Failed to execute - " . $dblink->error);
            return null;
        }

        $result = $stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_all_loan_numbers] Failed to get result - " . $dblink->error);
            return null;
        }

        $loans = [];
        while ($row = $result->fetch_assoc()) {
            $loans[] = $row['loan_number'];
        }

        if ($result) {
            $result->free();
        }

        return $loans;

    } finally {
        $stmt->close();
    }
}

function get_size_across_loans($dblink) {
    $query = "
    SELECT 
        SUM(loan_total_size) as total_size,
        AVG(loan_total_size) as avg_size_per_loan
    FROM (
        SELECT 
            l.loan_id,
            SUM(dc.size) as loan_total_size
        FROM loans l 
        JOIN documents d ON l.loan_id = d.loan_id 
        JOIN document_contents dc ON d.document_id = dc.document_id
        WHERE d.uploaded_at >= '2025-11-01 00:00:00' 
          AND d.uploaded_at < '2025-11-21 00:00:00'
        GROUP BY l.loan_id
    ) as loan_totals;
    ";

    $stmt = $dblink->prepare($query);
    if (!$stmt) {
        log_message("[DB ERROR][get_all_loan_numbers] Failed to prepare - " . $dblink->error);
        return null;
    }

    try {
        if (!$stmt->execute()) {
            log_message("[DB ERROR][get_all_loan_numbers] Failed to execute - " . $dblink->error);
            return null;
        }

        $result = $stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_all_loan_numbers] Failed to get result - " . $dblink->error);
            return null;
        }

        $row = $result->fetch_assoc();

        if ($result) {
            $result->free();
        }

        return $row;

    } finally {
        $stmt->close();
    }
}

function format_bytes($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $power = floor(log($bytes, 1024));
    
    return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
}
