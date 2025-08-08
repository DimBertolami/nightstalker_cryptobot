<?php
require_once '../includes/functions.php';

if (isset($_POST['message'])) {
    logEvent($_POST['message']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No message provided.']);
}
