<?php

if (php_sapi_name() !== 'cli' && !defined('DB_INITIALIZED')) {
    try {
        initDB();
        define('DB_INITIALIZED', true);
    } catch (Exception $e) {
        error_log("Database initialization error: " . $e->getMessage());
        // Don't die here, allow the script to continue
        // The application will display an appropriate error message
    }
}

/**
 * Initialize database connection
 */
function connectToDatabase() {
    return getDBConnection();
}

/**
 * Initialize database schema
 */
function initDB() {
    // Initialize database if needed
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    try {
        if (!$db->query("SELECT 1 FROM dual")) {
            if (!$db->query("CREATE DATABASE IF NOT EXISTS `".DB_NAME."`")) {
                throw new Exception("Could not create database: ".$db->errorInfo()[2]);
            }
            $db->query("USE `".DB_NAME."`");
        }
        
        // First check if tables exist
        $requiredTables = ['cryptocurrencies', 'price_history', 'system_logs', 'trades', 'coins', 'all_coingecko_coins'];
        $existingTables = [];
        
        // Get all tables in the database
        $result = $db->query("SHOW TABLES");
        $tables = [];
        if ($result) {
            while($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
        }
        
        // Check which required tables exist
        foreach ($requiredTables as $table) {
            if (in_array($table, $tables)) {
                $existingTables[] = $table;
            }
        }
        
        // If all tables exist, we're done
        if (count($existingTables) == count($requiredTables)) {
            return true;
        }
        
        // Fix cryptocurrencies table if it exists but has duplicates
        if (in_array('cryptocurrencies', $existingTables)) {
            try {
                // Check if there are duplicates
                $result = $db->query("SELECT id, COUNT(*) as count FROM cryptocurrencies GROUP BY id HAVING count > 1");
                if ($result && $result->rowCount() > 0) {
                    // Rename the table and create a new one with proper constraints
                    $db->query("RENAME TABLE cryptocurrencies TO cryptocurrencies_old");
                    $db->query("CREATE TABLE cryptocurrencies (
                        id varchar(50) NOT NULL PRIMARY KEY,
                        name varchar(100) NOT NULL,
                        symbol varchar(10) NOT NULL,
                        created_at datetime NOT NULL,
                        added_to_system datetime DEFAULT current_timestamp(),
                        age_hours int(11) DEFAULT 0,
                        market_cap decimal(30,2) DEFAULT 0,
                        volume decimal(30,2) DEFAULT 0,
                        price decimal(20,8) DEFAULT 0,
                        price_change_24h decimal(10,2) DEFAULT 0,
                        last_updated datetime DEFAULT NULL,
                        is_trending tinyint(1) DEFAULT 0,
                        volume_spike tinyint(1) DEFAULT 0
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    
                    // Copy data without duplicates
                    $db->query("INSERT INTO cryptocurrencies 
                        SELECT * FROM (
                            SELECT * FROM cryptocurrencies_old 
                            GROUP BY id
                        ) as temp");
                }
            } catch (Exception $e) {
                error_log("Error fixing cryptocurrencies table: " . $e->getMessage());
            }
        }
        
        // Apply schema for missing tables
        $sql = file_get_contents(__DIR__ . '/../install/schema.sql');
        if ($sql === false) {
            throw new Exception("Schema file not found");
        }
        
        // Split SQL by semicolons to execute statements individually
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            
            // Skip statements for tables that already exist
            $skipStatement = false;
            foreach ($existingTables as $table) {
                if (strpos($statement, "CREATE TABLE `$table`") !== false || 
                    strpos($statement, "ALTER TABLE `$table`") !== false) {
                    $skipStatement = true;
                    break;
                }
            }
            
            if ($skipStatement) {
                continue;
            }
            
            try {
                $db->query($statement);
            } catch (Exception $e) {
                // Log but continue if table already exists
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    error_log("Table already exists, continuing: " . $e->getMessage());
                    continue;
                }
                error_log("Database Error: " . $e->getMessage());
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Database Error: ".$e->getMessage());
        return false;
    }
}

/**
 * Get PDO database connection
 */
function getDBConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    return $db;
}

/**
 * Close database connection
 */
function closeDB($db) {
    if ($db) {
        $db = null;
    }
}
?>
