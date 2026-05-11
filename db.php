<?php
require_once __DIR__ . '/app/helpers/error_handler.php';

function getConnection(): mysqli {
    $conn = new mysqli('localhost', 'root', '', 'lumina_pos');

    if ($conn->connect_error) {
        exit('Database connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8');

    return $conn;
}

// VERIFICATION:
// 1. Include this file: require_once 'db.php';
// 2. Call: $conn = getConnection();
// 3. Run a test query: $result = $conn->query('SELECT 1');
// 4. Check: var_dump($result); — expects object(mysqli_result)
// 5. Confirm charset: $conn->query("SHOW VARIABLES LIKE 'character_set_client'")->fetch_assoc()
// 6. Close when done: $conn->close();
