<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Yekhlakov\PAgent\Agent;

$configPath = __DIR__ . '/../config/config.json';
$jobsDir = __DIR__ . '/../jobs';
if (!is_dir($jobsDir)) {
    mkdir($jobsDir, 0777, true);
}

// --- Helper function for recursive directory deletion ---
function deleteDir($dir) {
    if (!is_dir($dir)) return false;
    // Fix typo: scandir
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? deleteDir("$dir/$file") : unlink("$dir/$file");
    }
    // Fix typo: rmdir
    return rmdir($dir);
}
// --------------------------------------------------------


// 1. Load Config
if (!file_exists($configPath)) {
    die("Config file not found: $configPath");
}
$config = json_decode(file_get_contents($configPath), true);

// 2. Handle Job Creation and Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_job') {
        // MODIFICATION: Use isset() to ensure an empty string is accepted if posted, 
        // and only default to 'Untitled Job' if the field is entirely missing.
        $title = isset($_POST['title']) ? $_POST['title'] : 'Untitled Job'; 
        $llm = $_POST['llm'] ?? 'local';
        $prompt = $_POST['prompt'] ?? '';
        $enabledTools = $_POST['tools'] ?? [];

        // Create job directory
        $timezone = $config['agent']['timezone'] ?? 'UTC';
        $dateStr = (new DateTime('now', new DateTimeZone($timezone)))->format('Y-m-d_H-i-s.u');
        $jobDirName = $dateStr;
        $jobDirPath = $jobsDir . '/' . $jobDirName;
        
        if (!mkdir($jobDirPath, 0777, true)) {
            die("Failed to create job directory: $jobDirPath");
        }

        // Create files (Requirement 3: Add is_running)
        $jobJson = [
            'title' => $title,
            'llm' => $llm,
            'prompt' => $prompt,
            'enabled_tools' => $enabledTools,
            'created_at' => $dateStr,
            'is_running' => true // Set to true on creation
        ];
        file_put_contents($jobDirPath . '/job.json', json_encode($jobJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($jobDirPath . '/job.log', "");
        file_put_contents($jobDirPath . '/filecache.json', "{}");
        
        // Start worker process
        $workerPath = __DIR__ . '/../worker.php';
        $cmd = sprintf('php %s %s', escapeshellarg($workerPath), escapeshellarg($jobDirPath));
        
        $descriptorspec = [
           0 => ["pipe", "r"], // stdin
           1 => ["file", $jobDirPath . '/job.log', "a"], // stdout
           2 => ["file", $jobDirPath . '/job.log', "a"]  // stderr
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            // On Windows, to truly detach, one might use 'start /B' in the command.
            // But we follow the instruction to use proc_open.
        }
        // Redirect to the new job
        header("Location: index.php?job=" . urlencode($jobDirName));
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_job') {
        $jobDirName = $_POST['job_dir'] ?? null;
        if ($jobDirName) {
            $jobDirPath = $jobsDir . '/' . $jobDirName;
            
            // Perform deletion (Requirement 2)
            if (deleteDir($jobDirPath)) {
                // Successfully deleted, redirect to unselect job
                header("Location: index.php");
                exit;
            } else {
                // Handle deletion failure (optional: show an error message)
                echo "Error deleting job: $jobDirName";
            }
        }
    }
}

// 3. Get Jobs List
$jobs = array_diff(scandir($jobsDir), array('.', '..'));
$jobList = [];
foreach ($jobs as $jobDir) {
    $jsonPath = $jobsDir . '/' . $jobDir . '/job.json';
    if (file_exists($jsonPath)) {
        $jobData = json_decode(file_get_contents($jsonPath), true);
        $jobList[] = [
            'dir' => $jobDir,
            'title' => $jobData['title'] ?? $jobDir,
            'created_at' => $jobData['created_at'] ?? ''
        ];
    }
}
// Sort latest to oldest
usort($jobList, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});

// 4. Get Selected Job and Metadata
$selectedJob = $_GET['job'] ?? null;
$jobLogContent = "";
$jobResultContent = null; 
$isJobRunning = false; 
$jobPromptContent = ''; // New variable for prompt
if ($selectedJob) {
    $jobPath = $jobsDir . '/' . $selectedJob;
    $jobJsonPath = $jobPath . '/job.json';
    $logPath = $jobPath . '/job.log';
    
    if (file_exists($logPath)) {
        $jobLogContent = file_get_contents($logPath);
    }

    if (file_exists($jobJsonPath)) {
        $jobData = json_decode(file_get_contents($jobJsonPath), true);
        // Requirement 4: Check is_running status
        $isJobRunning = $jobData['is_running'] ?? false; 
        
        // Requirement 1: Get prompt and result
        $jobPromptContent = $jobData['prompt'] ?? '';
        $result = $jobData['result'] ?? null;
        if (!empty($result)) {
            $jobResultContent = $result; // Store the Markdown string
        }
    }
}

// 5. Get Tools and Tags for the form
$toolsInfo = ['tools' => [], 'tags' => []];
ob_start(); // Start output buffering
try {
    $agent = new Agent($configPath, 'local');
    foreach ($agent->toolSet as $key => $metadata) {
        $toolsInfo['tools'][$key] = $key;
        $tag = $metadata['tag'];
        $toolsInfo['tags'][$tag][] = $key;
    }
    // Deduplicate tags
    foreach ($toolsInfo['tags'] as $tag => $toolKeys) {
        $toolsInfo['tags'][$tag] = array_unique($toolKeys);
    }
} catch (\Exception $e) {
    // If Agent fails to initialize (e.g. due to missing config or other), we just have empty tools.
}
ob_end_clean(); // Discard buffered output

// Include the template
include 'template.php';