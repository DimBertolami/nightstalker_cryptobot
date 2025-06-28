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
    static $db = null;
    
    if ($db === null) {
        require_once __DIR__.'/config.php';
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            die("Database connection failed: " . $db->connect_error);
        }
        $db->set_charset("utf8mb4");
    }
    return $db;
}

/**
 * Initialize database schema
 */
function initDB() {
    try {
        $db = getDBConnection();
        
        if (!$db || !$db->ping()) {
            throw new Exception("Database connection failed");
        }

        if (!$db->select_db(DB_NAME)) {
            if (!$db->query("CREATE DATABASE IF NOT EXISTS `".DB_NAME."`")) {
                throw new Exception("Could not create database: ".$db->error);
            }
            $db->select_db(DB_NAME);
        }
        
        // First check if tables exist
        $requiredTables = ['cryptocurrencies', 'price_history', 'system_logs', 'trades', 'coins', 'all_coingecko_coins'];
        $existingTables = [];
        
        // Get all tables in the database
        $result = $db->query("SHOW TABLES");
        $tables = [];
        if ($result) {
            while($row = $result->fetch_row()) {
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
                if ($result && $result->num_rows > 0) {
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
                            SELECT * FROM cryptocurrencies_old ORDER BY last_updated DESC
                        ) as latest 
                        GROUP BY id");
                    
                    // Remove cryptocurrencies from the list of existing tables so schema gets applied
                    $existingTables = array_diff($existingTables, ['cryptocurrencies']);
                }
            } catch (Exception $e) {
                error_log("Error fixing cryptocurrencies table: " . $e->getMessage());
                // Continue with initialization
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
 * Database Connection (Singleton Pattern)
 */
function getDBConnection() {
    static $connection = null;
    
    if ($connection === null) {
        require_once __DIR__.'/config.php';
        $connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($connection->connect_error) {
            error_log("[DB] Connection failed: " . $connection->connect_error);
            return null;
        }
        $connection->set_charset("utf8mb4");
    }
    
    return $connection;
}

/**
 * Close database connection
 */
function closeDB($db) {
    if ($db) {
        $db->close();
    }
}
?>
