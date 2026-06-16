<?php if ($selectedJob): ?>
    <h2 style="margin-top: 0;">Job Log: <?php echo htmlspecialchars($selectedJob); ?></h2>

    <!-- Requirement 1: Add Rerun Button for finished jobs -->
    <?php if (!$isJobRunning): ?>
    <form method="GET" action="index.php" style="margin-bottom: 15px;">
        <input type="hidden" name="rerun_job" value="<?php echo htmlspecialchars($selectedJob); ?>">
        <button type="submit" class="rerun-button" style="padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">Rerun Job</button>
    </form>
    <?php endif; ?>

    <!-- Display Project ID -->
    <?php if (!empty($jobToolParameters['gitlab']['project_id'])): ?>
        <div class="job-metadata" style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background-color: #f9f9f9;">
            <p><strong style="margin-right: 10px;">Project ID:</strong> <?php echo htmlspecialchars($jobToolParameters['gitlab']['project_id']); ?></p>
        </div>
    <?php endif; ?>
    
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
                // Ensure marked.js is loaded before running this
                if (typeof marked !== 'undefined') {
                    document.getElementById('promptMarkdown').innerHTML=marked.parse(document.getElementById('promptSource').textContent)
                }
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
            // Ensure marked.js is loaded before running this
            if (typeof marked !== 'undefined') {
                document.getElementById('outputMarkdown').innerHTML=marked.parse(document.getElementById('outputSource').textContent)
            }
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
<?php endif; ?>
