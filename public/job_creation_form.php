<?php
// =========================================================================
// START RERUN JOB LOGIC (Requirement 2)
// NOTE: This assumes that a function or external logic has retrieved the job
// data based on the rerun_job parameter and made it available in $jobToRerun.
// For demonstration, we assume $jobToRerun holds the source job data.
// If $rerunJobName is set, we attempt to populate the form.
$rerunJobName = $_GET['rerun_job'] ?? null;
$jobToRerun = null;

if ($rerunJobName) {
    // Placeholder: In a real application, this would fetch job data from DB/Cache
    // For this solution, we assume $jobToRerun is populated here if $rerunJobName is valid.
    // Example structure for $jobToRerun:
    // [
    //     'title' => 'My Source Job Title',
    //     'prompt' => 'The prompt content...',
    //     'tools' => ['git', 'llm_tool'], // Array of tool keys
    //     'tool_parameters' => [
    //         'gitlab' => ['project_id' => '12345']
    //     ]
    // ]
    // Since we lack the fetch logic, we rely on the assumption that $jobToRerun is available.
    // If you are running this, ensure $jobToRerun is populated when rerun_job is present.


    $jobToRerun = json_decode (file_get_contents ($jobsDir . '/' . $rerunJobName . '/job.json'), true);

}
// =========================================================================
?>
            <h2 style="margin-top: 0;">Job Creation Form</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_job">
                
                <!-- Job Title -->
                <div class="form-group">
                    <label for="title">Job Title</label>
                    <input type="text" name="title" id="title" 
                           value="<?php echo htmlspecialchars($jobToRerun['title'] ?? ''); ?>"
                    <?php if ($rerunJobName): ?>
                        <small style="color: gray;">(Prefilled from source job)</small>
                    <?php endif; ?>
                </div>
                
                <!-- LLM -->
                <div class="form-group">
                    <label for="llm">LLM</label>
                    <select name="llm" id="llm">
                        <?php foreach ($config['llm'] as $llmName => $llmConfig): ?>
                            <option value="<?php echo htmlspecialchars($llmName); ?>" 
                                <?php echo ($jobToRerun['llm'] ?? '') === $llmName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($llmName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Tool Parameters Section -->
                <div class="form-group">
                    <label>Tool Parameters</label>
		    <div class="form-group">
			<label>Gitlab Project Id</label>
			<input type="text" name="tool_parameters.gitlab.project_id" id="tool_parameters.gitlab.project_id" 
                   value="<?php echo htmlspecialchars($jobToRerun['tool_parameters']['gitlab']['project_id'] ?? $config['gitlab']['project_id']); ?>" 
                   style="width: 100%; padding: 8px; border: 1px solid #ccc;">
		    </div>
                </div>
                <!-- End Tool Parameters Section -->
                
                <!-- Enabled Tools & Tags -->
                <div class="form-group">
                    <label>Enabled Tools & Tags</label>
                    <div class="tools-container">
                        <?php foreach ($toolsInfo['tags'] as $tag => $toolKeys): ?>
                            <div class="tag-group">
                                <label class="tag-title">
                                    <input type="checkbox" name="tools[]" value="<?php echo htmlspecialchars($tag); ?>"
                                           <?php 
                                           // Check if the tag itself was used in the source job
                                           $tagUsed = false;
                                           if (isset($jobToRerun['enabled_tools']) && is_array($jobToRerun['enabled_tools'])) {
                                               foreach ($toolKeys as $toolKey) {
                                                   if (in_array($toolKey, $jobToRerun['enabled_tools'])) {
                                                       $tagUsed = true;
                                                       break;
                                                   }
                                               }
                                           }
                                           echo $tagUsed ? 'checked' : '';
                                           ?>
                                    >
                                    Tag: <?php echo htmlspecialchars($tag); ?>
                                </label>
                                <?php foreach ($toolKeys as $toolKey): ?>
                                    <label class="tool-item">
                                        <input type="checkbox" name="tools[]" value="<?php echo htmlspecialchars($toolKey); ?>"
                                               <?php 
                                               // Check if the specific tool was used in the source job
                                               $isChecked = in_array($toolKey, $jobToRerun['enabled_tools'] ?? []) ? 'checked' : '';
                                               echo $isChecked;
                                               ?>
                                        >
                                        <?php echo htmlspecialchars($toolKey); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Prompt -->
                <div class="form-group">
                    <label for="prompt">Prompt</label>
                    <textarea name="prompt" id="prompt" required><?php echo htmlspecialchars($jobToRerun['prompt'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit">Create job</button>
            </form>
