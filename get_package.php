<?php
// get_package.php
require 'backend/config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid package ID']);
    exit;
}

$package_id = (int)$_GET['id'];
$query = "SELECT * FROM packages WHERE id = $package_id";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Package not found']);
    exit;
}

echo json_encode($result->fetch_assoc());
?>
