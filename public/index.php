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
        $title = $_POST['title'] ?? 'Untitled Job';
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Agent Web Interface</title>
    <!-- Include the marked library -->
    <script src="https://cdn.jsdelivr.net/npm/marked/lib/marked.umd.js"></script>
    <style>
        body { font-family: sans-serif; display: flex; margin: 0; height: 100vh; overflow: hidden; }
        .left-column { width: 300px; border-right: 1px solid #ccc; overflow-y: auto; padding: 10px; background: #f9f9f9; }
        .right-column { flex: 1; overflow-y: auto; padding: 20px; }
        .job-item { padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; }
        .job-item:hover { background-color: #e0e0e0; }
        .job-item.selected { background-color: #d0d0d0; font-weight: bold; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], select, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        textarea { height: 150px; }
        .tools-container { border: 1px solid #ccc; padding: 10px; max-height: 300px; overflow-y: auto; background: #fff; }
        .tag-group { margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .tag-title { font-weight: bold; color: #333; }
        .tool-item { margin-left: 20px; font-weight: normal; font-size: 0.9em; }
        pre { background: #222; color: #eee; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; margin-right: 10px; }
        button:hover { background: #0056b3; }
        .delete-button { background: red !important; }
        .result-display { margin-top: 20px; padding: 15px; border: 1px solid #007bff; background-color: #e6f0ff; border-radius: 5px; }
        .result-display h3 { margin-top: 0; }
        
        /* Collapsible Styles */
        .collapsible-container {
            cursor: pointer;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f0f0f0;
            transition: max-height 0.3s ease-in-out; /* Smooth height transition */
            overflow: hidden;
            /* Initial state: collapsed */
            max-height: 0; 
        }
        .collapsible-container.expanded {
            /* Set a large enough value to allow full expansion */
            max-height: 1000px; 
        }
        .collapsible-content {
            padding: 0; /* Padding handled by the container */
        }
        
        /* Prompt specific styling for truncation */
        .prompt-text {
            display: block;
            /* This ensures the prompt text itself is visible and truncated */
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
    <?php if ($selectedJob && $isJobRunning): ?>
    <script>
        // Requirement 4: Refresh only if is_running is true
        setTimeout(function() {
            location.reload();
        }, 5000);
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="left-column">
        <h3 style="display: flex; justify-content: space-between; align-items: center;">
            Jobs
            <!-- Requirement 1: New job button -->
            <button onclick="location.href='?job='">New job</button>
        </h3>
        <?php foreach ($jobList as $job): ?>
            <div class="job-item <?php echo ($selectedJob === $job['dir']) ? 'selected' : ''; ?>" 
                 onclick="location.href='?job=<?php echo urlencode($job['dir']); ?>'">
                <div><?php echo htmlspecialchars($job['title']); ?></div>
                <small style="color: #666;"><?php echo htmlspecialchars($job['created_at']); ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="right-column">
        <?php if (!$selectedJob): ?>
            <h2>Job Creation Form</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_job">
                <div class="form-group">
                    <label for="title">Job Title</label>
                    <input type="text" name="title" id="title" required>
                </div>
                <div class="form-group">
                    <label for="llm">LLM</label>
                    <select name="llm" id="llm">
                        <?php foreach ($config['llm'] as $llmName => $llmConfig): ?>
                            <option value="<?php echo htmlspecialchars($llmName); ?>"><?php echo htmlspecialchars($llmName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Enabled Tools & Tags</label>
                    <div class="tools-container">
                        <?php foreach ($toolsInfo['tags'] as $tag => $toolKeys): ?>
                            <div class="tag-group">
                                <label class="tag-title">
                                    <input type="checkbox" name="tools[]" value="<?php echo htmlspecialchars($tag); ?>">
                                    Tag: <?php echo htmlspecialchars($tag); ?>
                                </label>
                                <?php foreach ($toolKeys as $toolKey): ?>
                                    <label class="tool-item">
                                        <input type="checkbox" name="tools[]" value="<?php echo htmlspecialchars($toolKey); ?>">
                                        <?php echo htmlspecialchars($toolKey); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 10px;">
                            <p style="font-weight: bold; margin: 5px 0;">Other Tools:</p>
                            <?php foreach ($toolsInfo['tools'] as $toolKey => $toolName): ?>
                                <label class="tool-item">
                                    <input type="checkbox" name="tools[]" value="<?php echo htmlspecialchars($toolKey); ?>">
                                    <?php echo htmlspecialchars($toolKey); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="prompt">Prompt</label>
                    <textarea name="prompt" id="prompt" required></textarea>
                </div>
                <button type="submit">Create job</button>
            </form>
        <?php else: ?>
            <h2>Job Log: <?php echo htmlspecialchars($selectedJob); ?></h2>
            
            <!-- Requirement 2: Delete Job Button -->
            <form method="POST" style="margin-bottom: 15px;">
                <input type="hidden" name="action" value="delete_job">
                <input type="hidden" name="job_dir" value="<?php echo htmlspecialchars($selectedJob); ?>">
                <button type="submit" class="delete-button">Delete job</button>
            </form>

            <?php if ($jobPromptContent): ?>
                <!-- Requirement 1: Display Prompt above Result -->
                <div class="result-display" style="background-color: #fff3e6; border-color: #ff9800;">
                    <h3>📝 Job Prompt</h3>
                    <!-- Collapsible container for prompt -->
                    <div class="collapsible-container" id="prompt" onclick="togglePrompt()">
                        <!-- The prompt content itself, styled to truncate -->
                        <div class="collapsible-content">
                            <span class="prompt-text" id="promptDisplay">
                                <?php echo htmlspecialchars($jobPromptContent); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($jobResultContent): ?>
                <!-- Display Result (Requirement 1 & 2) -->
                <div class="result-display">
                    <h3>✅ Job Result</h3>
                    <!-- Markdown source container -->
                    <div id="markdownSource" style="display: none;">
                        <?php echo htmlspecialchars($jobResultContent); ?>
                    </div>
                    <!-- HTML output container -->
                    <div id="output">
                        <!-- Content will be injected here by JavaScript -->
                    </div>
                </div>
                <script>document.getElementById('output').innerHTML=marked.parse(document.getElementById('markdownSource').textContent)</script>
            <?php endif; ?>

            <!-- Display Log (Requirement 2: Collapsible for finished jobs) -->
            <h3 style="margin-top: 20px;">Job Log</h3>
            <?php if (!$isJobRunning): ?>
                <!-- Collapsible container for finished job log -->
                <div class="collapsible-container" id="log" onclick="toggleLog()">
                    <div class="collapsible-content">
                        <pre><?php echo htmlspecialchars($jobLogContent); ?></pre>
                    </div>
                </div>
            <?php else: ?>
                <!-- Log displayed directly if running -->
                <pre><?php echo htmlspecialchars($jobLogContent); ?></pre>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body >
</html >

<script>
    // Function to toggle the prompt display (Requirement 3)
    function togglePrompt() {
        const container = document.querySelector('.collapsible-container#prompt');
        const span = container.querySelector('.prompt-text');
        
        // Toggle the expanded class to trigger CSS transition
        container.classList.toggle('expanded');
        
        // Since we are only using max-height on the container, we must ensure the content is visible when expanded.
        // We simply rely on the CSS max-height: 1000px to expand the container and show the content.
    }

    // Function to toggle the job log display (Requirement 3)
    function toggleLog() {
        const container = document.querySelector('.collapsible-container#log');
        const content = container.querySelector('.collapsible-content');

        // Toggle the expanded class
        container.classList.toggle('expanded');
        
        // Note: Because the <pre> content inside is the element that needs to expand, 
        // setting max-height on the parent container is the correct way to achieve the effect.
    }
</script>
