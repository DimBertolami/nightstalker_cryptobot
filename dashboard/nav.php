<?php
// Define navigation items
$navItems = [
    [
        'title' => 'Dashboard',
        'url' => 'http://localhost/NS/dashboard/index.php',
        'icon' => 'bi-speedometer2'
    ],
    [
        'title' => 'Trading Log',
        'url' => '/NS/dashboard/logs.php',
        'icon' => 'bi-journal-text'
    ],
    [
        'title' => 'Settings',
        'url' => '/NS/dashboard/settings.php',
        'icon' => 'bi-gear'
    ]
];

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="http://localhost/NS/dashboard/index.php">
            <i class="bi bi-graph-up me-2"></i>
            Night Stalker
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = ($currentPage == basename($item['url'])) || ($currentPage == 'index.php' && $item['url'] == '/NS/dashboard/index.php'); ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo $item['url']; ?>">
                            <i class="bi <?php echo $item['icon']; ?> me-1"></i>
                            <?php echo $item['title']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    <i class="bi bi-circle-fill <?php echo isset($testMode) && $testMode ? 'text-warning' : 'text-success'; ?> me-1"></i>
                    <?php echo isset($testMode) && $testMode ? 'Simulation Mode' : 'Live Trading'; ?>
                </span>
                <span class="navbar-text">
                    <?php echo date('Y-m-d H:i:s'); ?>
                </span>
            </div>
        </div>
    </div>
</nav>
