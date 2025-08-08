<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Please log in to access this tool.</div>';
    exit;
}

$message = '';
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldCoinId = $_POST['old_coin_id'] ?? '';
    $newCoinId = $_POST['new_coin_id'] ?? '';

    if (empty($oldCoinId) || empty($newCoinId)) {
        $message = 'Both Old Coin ID and New Coin ID are required.';
        $success = false;
    } else {
        try {
            $db = getDBConnection();
            if (!$db) {
                throw new Exception('Database connection failed.');
            }

            $stmt = $db->prepare("UPDATE portfolio SET coin_id = ? WHERE coin_id = ? AND user_id = 1");
            $stmt->execute([$newCoinId, $oldCoinId]);

            $affectedRows = $stmt->rowCount();

            $message = "Updated portfolio entries: $affectedRows rows changed from '$oldCoinId' to '$newCoinId'.";
            $success = true;
        } catch (Exception $e) {
            $message = 'Error updating portfolio: ' . $e->getMessage();
            $success = false;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Fix Portfolio Coin ID</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-dark text-light p-4">
    <div class="container">
        <h1 class="mb-4">
            Fix Portfolio Coin ID
            <small class="text-muted fs-6 ms-3">Update incorrect coin IDs in portfolio</small>
        </h1>

        <form method="post" class="mb-4">
            <div class="mb-3">
                <label for="old_coin_id" class="form-label">Old Coin ID (incorrect)</label>
                <input type="text" id="old_coin_id" name="old_coin_id" class="form-control" required value="<?php echo htmlspecialchars($_POST['old_coin_id'] ?? ''); ?>" />
            </div>
            <div class="mb-3">
                <label for="new_coin_id" class="form-label">New Coin ID (correct)</label>
                <input type="text" id="new_coin_id" name="new_coin_id" class="form-control" required value="<?php echo htmlspecialchars($_POST['new_coin_id'] ?? ''); ?>" />
            </div>
            <button type="submit" class="btn btn-primary">Update Portfolio</button>
        </form>

        <?php if ($message !== ''): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
