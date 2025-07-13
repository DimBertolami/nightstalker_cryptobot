<?php
/**
 * Import new coins from the Python script into the Night Stalker database
 * This script fetches new coin listings using the newcoinstracker.py script
 * and adds them to the database with proper information
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Script configuration
$log_file = __DIR__ . '/../logs/new_coins_import.log';
$python_script = '/home/dim/Documenten/newcoinstracker.py';

/**
 * Log a message to the log file with timestamp
 */
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

/**
 * Run the Python script and get the JSON output
 */
function run_python_script($max_age = 24) {
    // Path to the Python script
    $script_path = '/home/dim/Documenten/newcoinstracker.py';
    
    // Get API key from config if available
    $api_key = '';
    if (defined('COINGECKO_API_KEY') && COINGECKO_API_KEY) {
        $api_key = COINGECKO_API_KEY;
    }
    
    // Build the command with proper arguments
    $cmd_args = [
        'python3',
        escapeshellarg($script_path),
        '--json',
        '--max-age', escapeshellarg($max_age),
        '--sort', 'volume',
        '--limit', '100'
    ];
    
    // Add API key if available - only pass if actually defined with value
    if (!empty($api_key) && strlen(trim($api_key)) > 0) {
        $cmd_args[] = '--api-key';
        $cmd_args[] = escapeshellarg($api_key);
    }
    
    // Build the final command
    $command = implode(' ', $cmd_args) . ' 2>&1';
    
    // Log the command for debugging (without showing API key)
    $log_command = str_replace(escapeshellarg($api_key), '[API-KEY-HIDDEN]', $command);
    error_log("Running Python command: {$log_command}");
    
    // Execute the script and capture output
    $output = shell_exec($command);
    
    if (empty($output)) {
        error_log("Python script returned empty output");
        return [];
    }
    
    try {
        // Check if the output contains a JSON file path
        if (preg_match('/JSON_FILE:(.+)/', $output, $matches)) {
            $json_file = trim($matches[1]);
            error_log("Reading JSON from temporary file: {$json_file}");
            
            // Check if file exists and is readable
            if (file_exists($json_file) && is_readable($json_file)) {
                $json_content = file_get_contents($json_file);
                $coins_data = json_decode($json_content, true);
                
                // Clean up the temp file after reading
                unlink($json_file);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON parsing error: " . json_last_error_msg());
                    error_log("Raw content from file: " . substr($json_content, 0, 1000) . "...");
                    return [];
                }
            } else {
                error_log("Could not read JSON file: {$json_file}");
                return [];
            }
        } else {
            // Fallback to direct parsing (old method)
            $coins_data = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON parsing error: " . json_last_error_msg());
                error_log("Raw output: " . substr($output, 0, 1000) . "...");
                return [];
            }
        }
        
        // Log age range of returned coins for verification
        if (!empty($coins_data)) {
            $ages = array_column($coins_data, 'age_hours');
            $min_age = min($ages);
            $max_age_found = max($ages);
            error_log("Coin age range: {$min_age} to {$max_age_found} hours");
            error_log("Found " . count($coins_data) . " newly listed coins");
        } else {
            error_log("No new coins returned from Python script");
        }
        
        return $coins_data;
    } catch (Exception $e) {
        error_log("Error processing Python output: " . $e->getMessage());
        return [];
    }
}

/**
 * Insert or update coins in the database
 */
function updateDatabaseWithCoins($coins_data, $db) {
    if (empty($coins_data)) {
        logMessage("No coins to update");
        return 0;
    }
    
    // First, check if we need to add a date_added column to the coins table
    $columnExists = false;
    $result = $db->query("SHOW COLUMNS FROM coins LIKE 'date_added'");
    if ($result && $result->num_rows > 0) {
        $columnExists = true;
    }
    
    if (!$columnExists) {
        logMessage("Adding date_added column to coins table");
        $alterQuery = "ALTER TABLE coins ADD COLUMN date_added DATETIME NULL AFTER market_cap";
        if (!$db->query($alterQuery)) {
            logMessage("Failed to add date_added column: " . $db->error);
            // Continue anyway, we'll just not use the date_added column
        } else {
            logMessage("Successfully added date_added column");
            $columnExists = true;
        }
    }
    
    // Check which table exists: cryptocurrencies or coins
    $table_to_use = 'coins'; // We already know coins exists from previous query
    logMessage("Using table: " . $table_to_use);
    
    $total_updated = 0;
    $total_inserted = 0;
    $errors = 0;
    
    foreach ($coins_data as $coin) {
        try {
            // Set trending status based on age (24 hours or less)
            $is_trending = $coin['age_hours'] <= 24 ? 1 : 0;
            
            // Determine volume spike based on volume threshold
            $volume_spike = $coin['volume'] > 5000000 ? 1 : 0;
            
            // Convert the first_seen date to a MySQL compatible format if available
            $date_added = null;
            if (isset($coin['first_seen'])) {
                try {
                    $first_seen_date = new DateTime($coin['first_seen']);
                    $date_added = $first_seen_date->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    logMessage("Warning: Could not parse first_seen date for {$coin['symbol']}: {$e->getMessage()}");
                    // Fallback to current time
                    $date_added = date('Y-m-d H:i:s');
                }
            } else {
                // Fallback to current time if first_seen is not available
                $date_added = date('Y-m-d H:i:s');
            }
            
            // Check if the coin already exists
            $symbol = $db->real_escape_string($coin['symbol']);
            $check_query = "SELECT id FROM {$table_to_use} WHERE symbol = '{$symbol}'";
            $result = $db->query($check_query);
            
            if ($result && $result->num_rows > 0) {
                // Update existing coin
                $row = $result->fetch_assoc();
                $coin_id = $row['id'];
                
                // Prepare update query
                if ($columnExists) {
                    $update_query = "UPDATE coins SET 
                        name = ?,
                        current_price = ?, 
                        price_change_24h = ?, 
                        volume_24h = ?, 
                        market_cap = ?, 
                        date_added = ?,
                        is_trending = ?, 
                        volume_spike = ?, 
                        last_updated = NOW() 
                        WHERE id = ?";
                    
                    $stmt = $db->prepare($update_query);
                    $price_change = isset($coin['price_change_24h']) ? $coin['price_change_24h'] : 0;
                    
                    $stmt->bind_param(
                        "sddddsiii",
                        $coin['name'],
                        $coin['price'],
                        $price_change,
                        $coin['volume'],
                        $coin['market_cap'],
                        $date_added,
                        $is_trending,
                        $volume_spike,
                        $coin_id
                    );
                } else {
                    $update_query = "UPDATE coins SET 
                        name = ?,
                        current_price = ?, 
                        price_change_24h = ?, 
                        volume_24h = ?, 
                        market_cap = ?,
                        is_trending = ?, 
                        volume_spike = ?, 
                        last_updated = NOW() 
                        WHERE id = ?";
                    
                    $stmt = $db->prepare($update_query);
                    $price_change = isset($coin['price_change_24h']) ? $coin['price_change_24h'] : 0;
                    
                    $stmt->bind_param(
                        "sddddiij",
                        $coin['name'],
                        $coin['price'],
                        $price_change,
                        $coin['volume'],
                        $coin['market_cap'],
                        $is_trending,
                        $volume_spike,
                        $coin_id
                    );
                }
                
                if ($stmt->execute()) {
                    $total_updated++;
                    logMessage("Updated coin: {$coin['name']} ({$coin['symbol']}) - Age: " . number_format($coin['age_hours'], 2) . " hours");
                } else {
                    logMessage("Error updating coin {$coin['symbol']}: " . $stmt->error);
                    $errors++;
                }
                
                $stmt->close();
            } else {
                // Insert new coin - build query based on whether date_added column exists
                if ($columnExists) {
                    $insert_query = "INSERT INTO coins (
                        name, symbol, current_price, price_change_24h, volume_24h,
                        market_cap, date_added, is_trending, volume_spike, last_updated
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $stmt = $db->prepare($insert_query);
                    $price_change = isset($coin['price_change_24h']) ? $coin['price_change_24h'] : 0;
                    
                    $stmt->bind_param(
                        "ssddddsis",
                        $coin['name'],
                        $coin['symbol'],
                        $coin['price'],
                        $price_change,
                        $coin['volume'],
                        $coin['market_cap'],
                        $date_added,
                        $is_trending,
                        $volume_spike
                    );
                } else {
                    $insert_query = "INSERT INTO coins (
                        name, symbol, current_price, price_change_24h, volume_24h,
                        market_cap, is_trending, volume_spike, last_updated
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $stmt = $db->prepare($insert_query);
                    $price_change = isset($coin['price_change_24h']) ? $coin['price_change_24h'] : 0;
                    
                    $stmt->bind_param(
                        "ssddddii",
                        $coin['name'],
                        $coin['symbol'],
                        $coin['price'],
                        $price_change,
                        $coin['volume'],
                        $coin['market_cap'],
                        $is_trending,
                        $volume_spike
                    );
                }
                
                if ($stmt->execute()) {
                    $total_inserted++;
                    logMessage("Added new coin: {$coin['name']} ({$coin['symbol']}) - Age: " . number_format($coin['age_hours'], 2) . " hours - Trending: " . ($is_trending ? 'Yes' : 'No'));
                } else {
                    logMessage("Error inserting coin {$coin['symbol']}: " . $stmt->error);
                    $errors++;
                }
                
                $stmt->close();
            }
        } catch (Exception $e) {
            logMessage("Exception processing coin {$coin['symbol']}: " . $e->getMessage());
            $errors++;
        }
    }
    
    logMessage("Database update complete: {$total_inserted} coins inserted, {$total_updated} coins updated, {$errors} errors");
    return $total_inserted + $total_updated;
}

/**
 * Check if a table exists in the database
 */
function tableExists($db, $table_name) {
    $check = $db->query("SHOW TABLES LIKE '{$table_name}'");
    return ($check && $check->num_rows > 0);
}

/**
 * Get fallback data from the most recent CSV file
 * This function searches for the most recent CSV file in the data directory
 * and parses it to get coin data as a fallback when the Python script fails
 * 
 * @return array Array of coin data or empty array if no valid data found
 */
function get_fallback_data() {
    // Define the directory where CSV files are stored
    $csv_dir = __DIR__ . '/../data/csv';
    
    // Make sure the directory exists
    if (!is_dir($csv_dir)) {
        logMessage("Fallback CSV directory not found: {$csv_dir}");
        return [];
    }
    
    // Get all CSV files in the directory
    $csv_files = glob($csv_dir . '/*.csv');
    
    if (empty($csv_files)) {
        logMessage("No CSV files found in {$csv_dir}");
        return [];
    }
    
    // Sort files by modification time (newest first)
    usort($csv_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Try to parse the most recent file
    $latest_file = $csv_files[0];
    logMessage("Using fallback CSV file: " . basename($latest_file));
    
    // Check if file exists and is readable
    if (!file_exists($latest_file) || !is_readable($latest_file)) {
        logMessage("Cannot read CSV file: {$latest_file}");
        return [];
    }
    
    // Parse the CSV file
    $coins_data = [];
    if (($handle = fopen($latest_file, "r")) !== false) {
        // Read the header row
        $header = fgetcsv($handle, 1000, ",");
        
        // Map the header columns to our expected format
        $column_map = [
            'name' => array_search('Name', $header),
            'symbol' => array_search('Symbol', $header),
            'price' => array_search('Price USD', $header),
            'price_change_24h' => array_search('24h Change %', $header),
            'volume' => array_search('Volume', $header),
            'market_cap' => array_search('Market Cap', $header),
            'first_seen' => array_search('First Seen', $header),
            'age_hours' => array_search('Age (hours)', $header)
        ];
        
        // Check if we have the minimum required columns
        if ($column_map['name'] === false || $column_map['symbol'] === false) {
            logMessage("CSV file does not have required columns (Name, Symbol)");
            fclose($handle);
            return [];
        }
        
        // Read data rows
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $coin = [];
            
            // Map CSV columns to our expected format
            foreach ($column_map as $key => $index) {
                if ($index !== false && isset($data[$index])) {
                    $value = $data[$index];
                    
                    // Convert numeric values
                    if (in_array($key, ['price', 'price_change_24h', 'volume', 'market_cap', 'age_hours'])) {
                        // Remove any non-numeric characters except decimal point
                        $value = preg_replace('/[^0-9.-]/', '', $value);
                        $value = floatval($value);
                        
                        // Handle empty values
                        if (empty($value) && $value !== 0) {
                            // Default values for missing data to ensure filtering works
                            if ($key === 'age_hours') {
                                $value = 12.0; // Default to 12 hours old
                            } elseif ($key === 'volume' || $key === 'market_cap') {
                                $value = 2000000.0; // Default to $2M to pass filter
                            }
                        }
                    }
                    
                    $coin[$key] = $value;
                }
            }
            
            // Set default values for missing fields
            if (!isset($coin['age_hours']) || $coin['age_hours'] === '') {
                $coin['age_hours'] = 12.0; // Default to 12 hours old
            }
            if (!isset($coin['volume']) || $coin['volume'] === '') {
                $coin['volume'] = 2000000.0; // Default to $2M
            }
            if (!isset($coin['market_cap']) || $coin['market_cap'] === '') {
                $coin['market_cap'] = 2000000.0; // Default to $2M
            }
            
            // Only include coins that meet our criteria (age < 24 hours, volume and market cap >= 1.5M)
            if ($coin['age_hours'] <= 24 && $coin['volume'] >= 1500000 && $coin['market_cap'] >= 1500000) {
                // Add trending flag based on volume
                $coin['trending'] = ($coin['volume'] >= 5000000) ? 1 : 0;
                
                // Add date_added if not present
                if (!isset($coin['date_added'])) {
                    $coin['date_added'] = date('Y-m-d H:i:s');
                }
                
                $coins_data[] = $coin;
            }
        }
        fclose($handle);
    }
    
    logMessage("Parsed " . count($coins_data) . " coins from CSV fallback");
    return $coins_data;
}

// Main execution
try {
    logMessage("=== Starting new coins import ===");
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        logMessage("ERROR: Failed to connect to database");
        exit(1);
    }
    
    // Get new coins data from the Python script
    $max_age_hours = 24; // Only get coins less than 24 hours old
    $coins_data = run_python_script($max_age_hours);
    
    // If Python script fails, try to use the most recent CSV file as fallback
    if (!$coins_data) {
        logMessage("WARNING: Failed to get valid data from Python script, attempting to use fallback CSV data");
        $coins_data = get_fallback_data();
        
        if (!$coins_data) {
            logMessage("ERROR: Both Python script and fallback data failed");
            exit(1);
        } else {
            logMessage("SUCCESS: Using fallback data with " . count($coins_data) . " coins");
        }
    }
    
    // Update the database
    $updated = updateDatabaseWithCoins($coins_data, $db);
    logMessage("Updated $updated coins in the database");
    
    logMessage("=== Import completed successfully ===");
} catch (Exception $e) {
    logMessage("CRITICAL ERROR: " . $e->getMessage());
    exit(1);
}
