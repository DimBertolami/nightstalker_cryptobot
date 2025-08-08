<?php
header('Content-Type: application/json');

$reportsDir = __DIR__ . '/../reports';
$reports = [];

if (is_dir($reportsDir)) {
    $files = scandir($reportsDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'html') {
            $reports[] = $file;
        }
    }
    echo json_encode(['status' => 'success', 'reports' => $reports]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Reports directory not found.']);
}
?>