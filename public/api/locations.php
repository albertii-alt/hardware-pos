<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? '';
$conn   = getConnection();

if ($action === 'municipalities') {
    $province = 'Bohol';
    $stmt = $conn->prepare(
        'SELECT id, municipality AS name FROM municipalities
         WHERE province = ? ORDER BY municipality ASC'
    );
    $stmt->bind_param('s', $province);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    echo json_encode($rows);
    exit;
}

if ($action === 'barangays') {
    $mid = filter_input(INPUT_GET, 'municipality_id', FILTER_VALIDATE_INT);
    if (!$mid || $mid <= 0) {
        $conn->close();
        echo json_encode([]);
        exit;
    }
    $stmt = $conn->prepare(
        'SELECT id, name FROM barangays
         WHERE municipality_id = ? ORDER BY name ASC'
    );
    $stmt->bind_param('i', $mid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    echo json_encode($rows);
    exit;
}

$conn->close();
echo json_encode([]);
