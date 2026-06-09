<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Agent Web Interface</title>
    <!-- Include the marked library -->
    <script src="https://cdn.jsdelivr.net/npm/marked/lib/marked.umd.js"></script>
    <style>
        /* The CSS is now in index.css, but we keep the link here */
        @import url('index.css');
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
<body >
    <div class="left-column">
        <h3 style="display: flex; justify-content: space-between; align-items: center;">
            Jobs
            <!-- Requirement 1: New job button -->
            <button onclick="location.href='?job='">New job</button>
            <!-- Requirement 1: Settings button -->
            <button onclick="location.href='?view=settings'">Settings</button>
        </h3 >
        <?php foreach ($jobList as $job): ?>
            <div class="job-item <?php echo ($selectedJob === $job['dir']) ? 'selected' : ''; ?>" 
                 onclick="location.href='?job=<?php echo urlencode($job['dir']); ?>'">
                <div><?php echo htmlspecialchars($job['title']); ?></div>
                <small style="color: #666;"><?php echo htmlspecialchars($job['created_at']); ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="right-column">
        <?php if ($selectedJob): ?>
            <h2 style="margin-top: 0;">Job Log: <?php echo htmlspecialchars($selectedJob); ?></h2>
            
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
			<div id="promptSource" style="display:none"><?php echo htmlspecialchars($jobPromptContent); ?></div>
                        <div class="collapsible-content" id="promptMarkdown">
                        </div>
                    </div>
                <script>
			document.getElementById('promptMarkdown').innerHTML=marked.parse(document.getElementById('promptSource').textContent)
		</script>
                </div>
            <?php endif; ?>

            <?php if ($jobResultContent): ?>
                <!-- Display Result (Requirement 1 & 2) -->
                <div class="result-display">
                    <h3>✅ Job Result</h3>
                    <!-- Markdown source container -->
                    <div id="outputSource" style="display: none;"><?php echo htmlspecialchars($jobResultContent); ?></div>
                    <!-- HTML output container -->
                    <div id="outputMarkdown">
                        <!-- Content will be injected here by JavaScript -->
                    </div>
                </div>
                <script>
			document.getElementById('outputMarkdown').innerHTML=marked.parse(document.getElementById('outputSource').textContent)
		</script>
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

        <?php elseif (isset($_GET['view']) && $_GET['view'] === 'settings'): ?>
            <!-- NEW: Settings Form Stub -->
            <h2 style="margin-top: 0;">System Settings</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="setting_key">Setting Key</label>
                    <input type="text" name="setting_key" id="setting_key" placeholder="e.g., timezone">
                </div>
                <div class="form-group">
                    <label for="setting_value">Value</label>
                    <input type="text" name="setting_value" id="setting_value">
                </div>
                <button type="submit">Save Settings</button>
            </form>

        <?php else: ?>
            <h2 style="margin-top: 0;">Job Creation Form</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_job">
                <div class="form-group">
                    <label for="title">Job Title</label>
                    <input type="text" name="title" id="title">
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
                    </div>
                </div>
                <div class="form-group">
                    <label for="prompt">Prompt</label>
                    <textarea name="prompt" id="prompt" required></textarea>
                </div>
                <button type="submit">Create job</button>
            </form>
        <?php endif; ?>
    </div>
</body >
</html>

<script>
    // Function to toggle the prompt display (Requirement 3)
    function togglePrompt() {
        const container = document.querySelector('#prompt');

        // Toggle the expanded class to trigger CSS transition
        container.classList.toggle('expanded');
        
        // Since we are only using max-height on the container, we must ensure the content is visible when expanded.
        // We simply rely on the CSS max-height: 1000px to expand the container and show the content.
    }

    // Function to toggle the job log display (Requirement 3)
    function toggleLog() {
        const container = document.querySelector('.collapsible-container#log');

        // Toggle the expanded class
        container.classList.toggle('expanded');
        
        // Note: Because the <pre> content inside is the element that needs to expand, 
        // setting max-height on the parent container is the correct way to achieve the effect.
    }
</script>