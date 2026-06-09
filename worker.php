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
$enabledTools = $jobData['enabled_tools'] ?? [];
$prompt = $jobData['prompt'] ?? '';

// --- START: Job Title Check and Naming Agent Call ---

// Check if the job title is empty
if (empty($jobData['title'] ?? '')) {
    echo "Job title is empty. Executing agent call to determine it...\n";
    
    // 1. Construct the naming prompt
    $namingPrompt = "Figure out the name for an AI agent job given the prompt to the agent.
The name must be short (single line, up to 10 words) but descriptive.
IF the prompt explicitly mentions the output language, the name must be in this language.
Return ONLY the name string with no additional data.
The prompt to be analyzed follows:
---
" . $prompt;

    // 2. Initialize a dedicated agent instance for naming (ensuring no destructive tools are available for this call)
    $namingAgent = new Agent(llm: $llm);
    $namingAgent->withTools('finish');
    $namingAgent->loadFileCache($jobDir . '/filecache.json');

    // 3. Execute the naming agent call (no tools enabled)
    $namingAgent->handle($namingPrompt);

    // 4. Store the agent-produced job name
    $jobName = $namingAgent->getResult();
    $jobData['title'] = $jobName;
    file_put_contents($jobJsonPath, json_encode($jobData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "Job title determined: " . $jobName . "\n";
}

// --- END: Job Title Check and Naming Agent Call ---


// Use the timezone from config if possible, but Agent handles it.
// We just need to pass the llm and tools.

$agent = new Agent(llm: $llm);
// Load existing file cache if it exists
$agent->loadFileCache($jobDir . '/filecache.json');

// Enable tools/tags (for the main job execution)
if (!empty($enabledTools)) {
    $agent->withTools(...$enabledTools);
}

// Run the agent
try
{
	$agent->handle($prompt);
} catch (\Throwable $t) {
	echo "--------- The agent failed with error: " . $t->getMessage() . " ------------\n\n";
}

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
