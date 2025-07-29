<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Performance Dashboard</title>
    <!-- Include Tailwind CSS for styling -->
     <link href="/NS/dist/output.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.0.2"></script>

    <style>
        /* A little extra style for a dark theme */
        body {
            background-color: #111827; /* gray-900 */
            color: #f3f4f6; /* gray-100 */
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="container mx-auto">
        <h1 class="text-3xl font-bold mb-4">My Trading Bot Dashboard</h1>
        <?php
            // 1. Include the PHP component file
            require_once __DIR__.'/../backend/frontend/src/components/BotPerformanceCard.php';
 
            // 2. Render the performance card
            echo renderBotPerformanceCard();
        ?>
    </div>
    <!-- 3. Include the JavaScript file for the refresh functionality -->
    <script src="../assets/js/BotPerformanceCard.js"></script>
    <script src="../assets/js/BotPerformanceCharts.js"></script>
</body>
</html>
?>
