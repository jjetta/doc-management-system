<?php
require_once 'log_helpers.php';
require_once __DIR__ . '/../../config/db.php';

$script_name = basename(__FILE__);

function db_write_doc($dblink, $document_id, $content) {
    $size = strlen($content);

    $insert_query = "INSERT INTO document_contents (document_id, content, size) VALUES (?, ?, ?)";
    $insert_stmt = $dblink->prepare($insert_query);
    if (!$insert_stmt) {
        log_message("[DB ERROR][db_write_doc] Failed to prepare INSERT statement - " . $dblink->error);
        return false;
    }

    try {
        $null = null;
        $insert_stmt->bind_param("ibi", $document_id, $null, $size);
        $insert_stmt->send_long_data(1, $content);
        if (!$insert_stmt->execute()) {
            log_message("[DB ERROR][db_write_doc] Failed to execute INSERT statement - " . $dblink->error);
            return false;
        }

        log_message("[db_write_doc] Successfully downloaded document #$document_id.");
        return true;
    } finally {
        $insert_stmt->close();
    }
}

function get_or_create_doctype($dblink, $doctype) {

    // Normalize the doctype: remove trailing "_{number}" if present
    $doctype = get_doctype_from_filename($doctype);

    // Check if the doctype exists
    $select_query = "SELECT doctype_id FROM document_types WHERE doctype = ?";
    $select_stmt = $dblink->prepare($select_query);
    if (!$select_stmt) {
        log_message("[DB ERROR][get_or_create_doctype] Failed to prepare SELECT statement - " . $dblink->error);
        return null;
    }

    try {
        $select_stmt->bind_param("s", $doctype);
        if (!$select_stmt->execute()) {
            log_message("[DB ERROR][get_or_create_doctype] Failed to execute SELECT statement - " . $select_stmt->error);
            return null;
        }

        $doctype_id = null;
        $select_stmt->bind_result($doctype_id);
        if ($select_stmt->fetch()) {
            return $doctype_id;
        }
    } finally {
        $select_stmt->close();
    }

    // Insert new doctype
    $insert_stmt = $dblink->prepare("INSERT INTO document_types (doctype) VALUES (?)");
    if (!$insert_stmt) {
        log_message("[DB ERROR][get_or_create_doctype] Failed to prepare INSERT statement - " . $dblink->error);
        return null;
    }

    try {
        $insert_stmt->bind_param("s", $doctype);
        if ($insert_stmt->execute()) {
            log_message("[get_or_create_doctype] Added new doctype: $doctype");
            return $dblink->insert_id;
        } else {
            log_message("[DB ERROR][get_or_create_doctype] Failed to insert $doctype: " . $insert_stmt->error);
            return null;
        }
    } finally {
        $insert_stmt->close();
    }
}

function save_file_metadata($dblink, $loan_id, $doctype_id, $mysql_ts, $docname) {

    $insert_query = "INSERT INTO documents (loan_id, doctype_id, uploaded_at, doc_name) VALUES (?, ?, ?, ?)";
    $insert_stmt = $dblink->prepare($insert_query);
    if (!$insert_stmt) {
        log_message("[DB ERROR][save_file_metadata] Failed to prepare INSERT statement - " . $dblink->error);
        return null;
    }

    try {
        $insert_stmt->bind_param("iiss", $loan_id, $doctype_id, $mysql_ts, $docname);
        if (!$insert_stmt->execute()) {
            log_message("[DB ERROR][save_file_metadata] Failed to execute INSERT - " . $dblink->error);
            return null;
        }

        log_message("[save_file_metadata] Metadata saved for document #$dblink->insert_id");
        return $dblink->insert_id;
    } finally {
            $insert_stmt->close();
    }
}

function get_or_create_loan($dblink, $loan_number) {

    // Check if the loan already exists
    $select_query = "SELECT loan_id FROM loans WHERE loan_number = ?";
    $select_stmt = $dblink->prepare($select_query);
    if (!$select_stmt) {
        log_message("[DB ERROR][get_or_create_loan] Failed to prepare SELECT statement - " . $dblink->error);
        return null;
    }

    try {
        $select_stmt->bind_param("s", $loan_number);
        if (!$select_stmt->execute()) {
            log_message("[DB ERROR][get_or_create_loan] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $result = $select_stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_or_create_loan] Failed to get result - " . $dblink->error);
        }

        if ($row = $result->fetch_assoc()) {
            return $row['loan_id'];
        }

    } finally {
        if (isset($select_stmt) && $select_stmt instanceof mysqli_stmt) {
            $select_stmt->close();
        }
    }

    // Insert the loan if not found
    $insert_query = "INSERT INTO loans (loan_number) VALUES (?)";
    $insert_stmt = $dblink->prepare($insert_query);
    if (!$insert_stmt) {
        log_message("[DB ERROR] Failed to prepare INSERT - " . $dblink->error);
        return null;
    }

    try {
        $insert_stmt->bind_param("s", $loan_number);
        if (!$insert_stmt->execute()) {
            log_message("[DB ERROR][get_or_create_loan] Failed to execute INSERT -  " . $dblink->error);
            return null;
        }

        log_message(str_repeat('-', 75));
        log_message("[INFO] New loan inserted: $loan_number");
        return $insert_stmt->insert_id;

    } finally {
        if (isset($insert_stmt) && $insert_stmt instanceof mysqli_stmt) {
            $insert_stmt->close();
        }
    }
}

function get_pending_docs($dblink) {

    $select_query = "
        SELECT 
            d.document_id, 
            l.loan_number,
            d.doc_name,
            d.uploaded_at
        FROM documents d
        JOIN loans l ON d.loan_id = l.loan_id
        JOIN document_statuses s ON d.document_id = s.document_id
        WHERE s.status IN ('pending', 'failed')
        ORDER BY d.document_id
        LIMIT 100
    ";

    $select_stmt = $dblink->prepare($select_query);
    if (!$select_stmt) {
        log_message("[DB ERROR][get_pending_docs] Failed to prepare SELECT - " . $dblink->error);
        return null;
    }

    try {
        if (!$select_stmt->execute()) {
            log_message("[DB ERROR][get_pending_docs] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $result = $select_stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_pending_docs] Failed to get result - " . $dblink->error);
            return null;
        }

        $pending_docs = [];
        while ($row = $result->fetch_assoc()) {
            $timestamp = date('Ymd_H_i_s', strtotime($row['uploaded_at']));
            $filename = "{$row['loan_number']}-{$row['doc_name']}-$timestamp.pdf";
            $pending_docs[$row['document_id']] = $filename;
        }

        return $pending_docs;

    } finally {
        $select_stmt->close();
    }
}

function get_by_loan_number($dblink, $loan_number) {

    $select_query = "
        SELECT
            d.document_id,
            l.loan_number,
            d.doc_name,
            d.uploaded_at,
            dc.size,
            dt.doctype,
            dal.last_accessed_at
        FROM documents d
        JOIN loans l ON d.loan_id = l.loan_id
        JOIN document_types dt ON d.doctype_id = dt.doctype_id
        LEFT JOIN document_contents dc ON d.document_id = dc.document_id 
        LEFT JOIN document_access_log dal ON d.document_id = dal.document_id
        WHERE l.loan_number = ?
    ";

    $select_stmt = $dblink->prepare($select_query);
    if (!$select_stmt) {
        log_message("[DB ERROR][get_by_loan_id] Failed to prepare SELECT - " . $dblink->error);
        return null;
    }

    try {
        $select_stmt->bind_param('s', $loan_number);
        if (!$select_stmt->execute()) {
            log_message("[DB ERROR][get_by_loan_id] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $result = $select_stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_by_loan_id] Failed to get result - " . $dblink->error);
            return null;
        }

        $docs = $result->fetch_all(MYSQLI_ASSOC);

        if ($result) {
            $result->free();
        }

        return $docs;

    } finally {
        $select_stmt->close();
    }
}

function get_by_date($dblink, $date1, $date2) {
    $query = "
        SELECT
            d.document_id,
            l.loan_number,
            d.doc_name,
            d.uploaded_at,
            dc.size,
            dt.doctype,
            dal.last_accessed_at
        FROM documents d
        JOIN loans l ON d.loan_id = l.loan_id
        JOIN document_types dt ON d.doctype_id = dt.doctype_id
        LEFT JOIN document_contents dc ON d.document_id = dc.document_id 
        LEFT JOIN document_access_log dal ON d.document_id = dal.document_id
        WHERE d.uploaded_at BETWEEN ? AND ?
    ";

    $stmt = $dblink->prepare($query);
    if (!$stmt) {
        log_message("[DB ERROR][get_by_date] Failed to prepare statement - " . $dblink->error);
        return null;
    }

    try {
        $stmt->bind_param('ss', $date1, $date2);
        if (!$stmt->execute()) {
            log_message("[DB ERROR][get_by_date] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $result = $stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_by_date] Failed to get result - " . $dblink->error);
            return null;
        }

        $docs = $result->fetch_all(MYSQLI_ASSOC);

        if ($result) {
            $result->free();
        }

        return $docs;

    } finally {
        $stmt->close();
    }
}

function get_by_doctype($dblink, $doctype_id) {

    $select_query = "
        SELECT
            d.document_id,
            l.loan_number,
            d.doc_name,
            d.uploaded_at,
            dc.size,
            dt.doctype,
            dal.last_accessed_at
        FROM documents d
        JOIN loans l ON d.loan_id = l.loan_id
        JOIN document_types dt ON d.doctype_id = dt.doctype_id
        LEFT JOIN document_contents dc ON d.document_id = dc.document_id 
        LEFT JOIN document_access_log dal ON d.document_id = dal.document_id
        WHERE dt.doctype_id = ?
    ";

    $select_stmt = $dblink->prepare($select_query);
    if (!$select_stmt) {
        log_message("[DB ERROR][get_by_doctype_id] Failed to prepare SELECT - " . $dblink->error);
        return null;
    }

    try {
        $select_stmt->bind_param('i', $doctype_id);
        if (!$select_stmt->execute()) {
            log_message("[DB ERROR][get_by_doctype_id] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $result = $select_stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_by_doctype_id] Failed to get result - " . $dblink->error);
            return null;
        }

        $docs = $result->fetch_all(MYSQLI_ASSOC);

        if ($result) {
            $result->free();
        }

        return $docs;

    } finally {
        $select_stmt->close();
    }
}

function get_current_docs($dblink) {

    $select_query = "
        SELECT d.document_id, l.loan_number, d.doc_name, d.uploaded_at
        FROM documents d
        JOIN loans l ON d.loan_id = l.loan_id
        ORDER BY d.document_id
    ";

    $select_stmt = $dblink->prepare($select_query);
    if (!$select_stmt) {
        log_message("[DB ERROR][get_current_docs] Failed to prepare SELECT - " . $dblink->error);
        return null;
    }

    try {
        if (!$select_stmt->execute()) {
            log_message("[DB ERROR][get_current_docs] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $result = $select_stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_current_docs] Failed to get result - " . $dblink->error);
            return null;
        }

        $current_docs = [];
        while ($row = $result->fetch_assoc()) {
            $uploaded_at = date('Ymd_H_i_s', strtotime($row['uploaded_at']));
            $filename = "{$row['loan_number']}-{$row['doc_name']}-{$uploaded_at}.pdf";
            $current_docs[$row['document_id']] = $filename ;
        }

        return $current_docs;

    } finally {
        $select_stmt->close();
    }
}

function get_current_loans($dblink) {
    $select_query = 'SELECT loan_number FROM loans';
    $select_stmt = $dblink->prepare($select_query);
    if (!$select_stmt) {
        log_message("[DB ERROR][get_all_loans] Failed to prepare SELECT statement - " . $dblink->error);
        return null;
    }

    try {
        if (!$select_stmt->execute()) {
            log_message("[DB ERROR][get_all_loans] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $loan_number = null;
        $select_stmt->bind_result($loan_number);

        $loans = [];
        while ($select_stmt->fetch()) {
            $loans[] = $loan_number;
        }

        return $loans;
    } finally {
        $select_stmt->close();
    }

}

function mark_as_failed($dblink, $document_id) {

    $update_query = "UPDATE document_statuses SET status = 'failed' WHERE document_id = ?";
    $update_stmt = $dblink->prepare($update_query);
    if (!$update_stmt) {
        log_message("[DB ERROR][fail_file_status] Failed to prepare UPDATE statement - " . $dblink->error);
    }

    try {
        $update_stmt->bind_param("i", $document_id);
        if (!$update_stmt->execute()) {
            log_message("[DB ERROR][fail_file_status] Failed to execute UPDATE statement - " . $dblink->error);
        }
    } finally {
        $update_stmt->close();
    }
}

function db_save_session($dblink, $sid) {

    log_message("Saving session...");

    $stmt = $dblink->prepare("INSERT INTO api_sessions (session_id) VALUES (?)");
    if (!$stmt) {
        log_message("[DB ERROR][db_save_session] Failed to prepare statement - " . $dblink->error);
        return false;
    }

    try {
        $stmt->bind_param("s", $sid);
        if (!$stmt->execute()) {
            log_message("[DB ERROR][db_save_session] Failed to save session $sid - " . $stmt->error);
            return false;
        } else {
            log_message("Session saved: $sid");
            return true;
        }
    } finally {
        $stmt->close();
    }
}

function db_close_session($dblink, $sid) {

    log_message("Updating session status in db...");

    $update_query = "UPDATE api_sessions SET closed_at = NOW() WHERE session_id = ?";
    $update_stmt = $dblink->prepare($update_query);
    if (!$update_stmt) {
        log_message("[DB ERROR][db_close_session] Failed to prepare update statement - " . $dblink->error);
        return false;
    }

    try {
        $update_stmt->bind_param("s", $sid);
        if (!$update_stmt->execute()) {
            log_message("[DB ERROR][db_close_session] Failed to update session $sid close - " . $update_stmt->error);
            return false;
        } else {
            log_message("Session closed: $sid");
            return true;
        }
    } finally {
        $update_stmt->close();
    }

}

function get_session($dblink) {

    log_message("Fetching latest session id...");

    $select_query = "SELECT session_id FROM api_sessions ORDER BY created_at DESC LIMIT 1";
    $select_stmt = $dblink->prepare($select_query);
    if (!$select_stmt) {
        log_message("[DB ERROR][get_session] Failed to prepare SELECT statement - " . $dblink->error);
        return null;
    }

    try {
       if (!$select_stmt->execute()) {
            log_message("[DB ERROR][get_session] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $latest_session_id = null;
        $select_stmt->bind_result($latest_session_id);
        if (!$select_stmt->fetch()) {
            log_message("[get_session] No sessions found in api_sessions table.");
            return null;
        }

        log_message("[get_session] Latest session ID found: $latest_session_id");
        return $latest_session_id;
    } finally {
        $select_stmt->close();
    }
}

function get_file_content($dblink, $document_id) {
    $query = "
        SELECT
            CONCAT(l.loan_number, '-', d.doc_name, '-', DATE_FORMAT(d.uploaded_at, '%Y%m%d_%H_%i_%s'), '.pdf') AS filename,
            l.loan_number,
            dc.content,
            dc.size,
            dal.last_accessed_at
        FROM documents d
        JOIN loans l ON d.loan_id = l.loan_id
        LEFT JOIN document_contents dc ON d.document_id = dc.document_id 
        LEFT JOIN document_access_log dal ON d.document_id = dal.document_id
        WHERE d.document_id = ?;
    ";
    $stmt = $dblink->prepare($query);
    if (!$stmt) {
        log_message("[DB ERROR][get_file_content] Failed to prepare SELECT statement - " . $dblink->error);
        return null;
    }

    try {
        $stmt->bind_param('i', $document_id);
        if (!$stmt->execute()) {
            log_message("[DB ERROR][get_file_content] Failed to execute SELECT statement - " . $dblink->error);
            return null;
        }

        $result = $stmt->get_result();
        if (!$result) {
            log_message("[DB ERROR][get_file_content] Failed to get result - " . $dblink->error);
            return null;
        }

        $document = $result->fetch_assoc();

        if ($result) {
            $result->free();
        }

        return $document;
    } finally {
        $stmt->close();
    }
}

function update_last_access($dblink, $document_id) {
    $query = "
        UPDATE document_access_log
        SET last_accessed_at = NOW()
        WHERE document_id = ?
    ";

    $stmt = $dblink->prepare($query);
    if (!$stmt) {
        log_message("[DB ERROR][update_last_access] Failed to prepare SELECT statement - " . $dblink->error);
    }

    try {
        $stmt->bind_param('i', $document_id);
        if (!$stmt->execute()) {
            log_message("[DB ERROR][update_last_access] Failed to execute SELECT statement - " . $dblink->error);
        }
    } finally {
        $stmt->close();
    }
}
