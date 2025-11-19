<?php
require_once __DIR__ . '/helpers/api_helpers.php';
require_once __DIR__ . '/helpers/db_helpers.php';
require_once __DIR__ . '/../config/db.php';

$script_name = basename(__FILE__);

$dblink = get_dblink();

create_session($dblink);

echo str_repeat("-", 100) . "\n";
