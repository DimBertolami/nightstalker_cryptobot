<?php
header('Content-Type: application/json');

// Get the raw POST data
$input_json = file_get_contents('php://input');

// Log the received JSON input
error_log("Received JSON input: " . $input_json);

// Path to your Python interpreter and script
$python_interpreter = '/opt/lampp/htdocs/NS/backend/venv/bin/python3'; // Path to the virtual environment's python3
$script_path = __DIR__ . '/../crypto_selector.py';

// Command to execute the Python script
// We use proc_open to pass data via stdin and capture stdout/stderr
$command = "$python_interpreter $script_path";

$descriptorspec = array(
   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
   2 => array("pipe", "w")   // stderr is a pipe that the child will write to
);

$process = proc_open($command, $descriptorspec, $pipes);

$output = null;
$error_output = null;

if (is_resource($process)) {
    // Write the input JSON to stdin of the Python script
    fwrite($pipes[0], $input_json);
    fclose($pipes[0]);

    // Read the output from stdout and stderr
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $error_output = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    // Close the process
    $return_value = proc_close($process);

    if ($return_value !== 0) {
        // Python script returned an error
        echo json_encode([
            'status' => 'error',
            'message' => 'Python script execution failed.',
            'python_error' => $error_output,
            'return_code' => $return_value
        ]);
    } else {
        // Attempt to decode the JSON output from Python
        $decoded_output = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode([
                'status' => 'success',
                'data' => $decoded_output
            ]);
        } else {
            error_log("Python script raw output: " . $output);
            error_log("Python script raw error output: " . $error_output);
            error_log("JSON decode error: " . json_last_error_msg());
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to decode JSON from Python script output.',
                'python_output' => $output,
                'json_error' => json_last_error_msg()
            ]);
        }
    }
} else {
    error_log("Failed to open process to Python script.");
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to open process to Python script.'
    ]);
}

?>