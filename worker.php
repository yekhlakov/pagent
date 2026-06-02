<?php

require __DIR__ . '/vendor/autoload.php';

use Yekhlakov\PAgent\Agent;

if ($argc < 2) {
    echo "Usage: php worker.php <job_directory>\n";
    exit(1);
}

$jobDir = $argv[1];
$jobJsonPath = $jobDir . '/job.json';

if (!file_exists($jobJsonPath)) {
    echo "Error: job.json not found in $jobDir\n";
    exit(1);
}

$jobData = json_decode(file_get_contents($jobJsonPath), true);
if (!$jobData) {
    echo "Error: Failed to decode job.json\n";
    exit(1);
}

// Set the current working directory to the script's directory to ensure relative paths like config/config.json resolve correctly.
chdir(__DIR__);
$llm = $jobData['llm'] ?? 'local';
$tools = $jobData['tools'] ?? [];
$prompt = $jobData['prompt'] ?? '';


// Use the timezone from config if possible, but Agent handles it.
// We just need to pass the llm and tools.

$agent = new Agent(llm: $llm);

// Load existing file cache if it exists
$agent->loadFileCache($jobDir . '/filecache.json');

// Enable tools/tags
if (!empty($tools)) {
    $agent->withTools(...$tools);
}

// Run the agent
$agent->handle($prompt);

// Store file cache
$agent->storeFileCache($jobDir . '/filecache.json');

// Update job.json to set is_running to false
$jobJsonPath = $jobDir . '/job.json';
$jobData = json_decode(file_get_contents($jobJsonPath), true);
if ($jobData) {
    $jobData['is_running'] = false;
    $jobData['result'] = $agent->getResult();
    file_put_contents($jobJsonPath, json_encode($jobData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
