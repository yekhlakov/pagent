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
                <!-- Tool Parameters Section -->
                <div class="form-group">
                    <label>Tool Parameters</label>
		    <div class="form-group">
			<label>Gitlab Project Id</label>
			<input type="text" name="tool_parameters.gitlab.project_id" id="tool_parameters.gitlab.project_id" value="<?php echo htmlspecialchars($config['gitlab']['project_id']); ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc;">
		    </div>
                </div>
                <!-- End Tool Parameters Section -->
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