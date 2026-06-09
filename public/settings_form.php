<?php
// Load configuration data
$config_json = file_get_contents('../config/config.json');

$config = json_decode($config_json, true);

// Helper function to generate input fields
function generate_input_field($label, $name, $value, $type = 'text', $placeholder = '') {
    $type_attr = ($type == 'boolean') ? 'checkbox' : $type;
    $checked = ($type == 'boolean' && $value) ? 'checked' : '';
    return "
        <div class='form-group'>
            <label for='{$name}'>{$label}</label>
            <input type='{$type_attr}' name='{$name}' id='{$name}' value='{$value}' placeholder='{$placeholder}' {$checked}>
        </div>";
}

// --- Agent Configuration ---
$agent_config = $config['agent'] ?? [];
$general_chapter_html = '
    <fieldset>
        <legend>Agent Settings</legend>
        <div class="form-group">
            <label for="agent.timezone">Timezone</label>
            <input type="text" name="agent.timezone" id="agent.timezone" value="' . htmlspecialchars($agent_config['timezone'] ?? '') . '" placeholder="e.g., Asia/Yekaterinburg">
        </div>
    </fieldset>
';

// --- LLM Configuration ---
$llm_config = $config['llm'] ?? [];
$llm_groups_html = '';
$llm_keys = array_keys($llm_config);

// Generate individual LLM groups (all visible by default)
foreach ($llm_keys as $key) {
    $llm_data = $llm_config[$key];
    // Removed style="display:none;" to make all groups visible
    $llm_groups_html .= '
    <div class="llm-group" id="llm_group_' . htmlspecialchars($key) . '">
        <h4 style="margin-top: 0;">LLM: ' . ucfirst($key) . ' 
            <button type="button" class="remove-llm-btn" data-llm-key="' . htmlspecialchars($key) . '">Remove</button>
        </h4>
        <div class="form-group">
            <label for="llm_key_' . htmlspecialchars($key) . '">LLM Name (Key)</label>
            <input type="text" name="llm_key_' . htmlspecialchars($key) . '" id="llm_key_' . htmlspecialchars($key) . '" value="' . htmlspecialchars($key) . '" placeholder="LLM Key">
        </div>
        <div class="form-group">
            <label for="llm.' . htmlspecialchars($key) . '.baseUrl">Base URL</label>
            <input type="text" name="llm.' . htmlspecialchars($key) . '.baseUrl" id="llm.' . htmlspecialchars($key) . '.baseUrl" value="' . htmlspecialchars($llm_data['baseUrl'] ?? '') . '" placeholder="Base URL">
        </div>
        <div class="form-group">
            <label for="llm.' . htmlspecialchars($key) . '.authToken">Auth Token</label>
            <input type="text" name="llm.' . htmlspecialchars($key) . '.authToken" id="llm.' . htmlspecialchars($key) . '.authToken" value="' . htmlspecialchars($llm_data['authToken'] ?? '') . '" placeholder="Auth Token">
        </div>
        <div class="form-group">
            <label for="llm.' . htmlspecialchars($key) . '.model">Model Name</label>
            <input type="text" name="llm.' . htmlspecialchars($key) . '.model" id="llm.' . htmlspecialchars($key) . '.model" value="' . htmlspecialchars($llm_data['model'] ?? '') . '" placeholder="Model Name">
        </div>
    </div>';
}

// Template for adding a new LLM group
$add_llm_group_html = '
<div class="llm-group" id="llm_group_add">
    <h4 style="margin-top: 0;">Add New LLM</h4>
    <div class="form-group">
        <label for="llm_key_new">LLM Name (Key)</label>
        <input type="text" name="llm_key_new" id="llm_key_new" placeholder="Enter new LLM key (e.g., my_openai)">
    </div>
    <div class="form-group">
        <label for="llm_new.baseUrl">Base URL</label>
        <input type="text" name="llm_new.baseUrl" id="llm_new.baseUrl" placeholder="Base URL">
    </div>
    <div class="form-group">
        <label for="llm_new.authToken">Auth Token</label>
        <input type="text" name="llm_new.authToken" id="llm_new.authToken" placeholder="Auth Token">
    </div>
    <div class="form-group">
        <label for="llm_new.model">Model Name</label>
        <input type="text" name="llm_new.model" id="llm_new.model" placeholder="Model Name">
    </div >
    <button type="button" class="add-llm-btn">Add LLM</button>
</div>';


// --- Filesystem Configuration ---
$filesystem_config = $config['filesystem'] ?? [];
$filesystem_chapter_html = '
    <fieldset>
        <legend>Filesystem</legend>
        <div class="form-group">
            <label for="filesystem.root_directory">Root Directory</label>
            <input type="text" name="filesystem.root_directory" id="filesystem.root_directory" value="' . htmlspecialchars($filesystem_config['root_directory'] ?? '') . '" placeholder="e.g., d:/dev/agent">
        </div>
    </fieldset>
';

// --- Complete HTML Structure ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        fieldset { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        legend { font-weight: bold; color: #0056b3; padding: 0 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-right: 10px; }
        button:hover { background-color: #0056b3; }
        .help-text { font-size: 0.9em; color: #666; margin-top: 10px; }
        .llm-group { border: 1px solid #e0e0e0; padding: 15px; margin-top: 15px; border-radius: 4px; background-color: #fafafa; position: relative; }
        .llm-group h4 { margin-top: 0; color: #555; display: flex; justify-content: space-between; align-items: center; }
        .llm-group.add-llm-group { background-color: #e9f5ff; border-color: #cce5ff; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="margin-top: 0;">System Settings</h2>
        <form method="POST">
            
            <!-- Agent Configuration -->
            <?php echo $general_chapter_html; ?>

            <!-- LLM Configuration -->
            <fieldset>
                <legend>LLM Service Configuration</legend>
                <div id="llm_config_container">
                    <?php echo $llm_groups_html; ?>
                    <?php echo $add_llm_group_html; ?>
                </div >
                <p class="help-text">All LLM configurations are displayed above. Use the "Add LLM" control group to add a new service.</p>
            </fieldset>

            <!-- Filesystem Configuration -->
            <?php echo $filesystem_chapter_html; ?>
            
            <button type="submit">Save Settings</button>
        </form>
    </div >

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('llm_config_container');

            // Function to add a new LLM group based on the template
            function addLLMGroup() {
                const templateGroup = document.getElementById('llm_group_add').cloneNode(true);
                templateGroup.id = 'llm_group_new_' + Date.now();
                templateGroup.classList.remove('add-llm-group');
                templateGroup.querySelector('h4').textContent = 'New LLM';
                templateGroup.querySelector('.add-llm-btn').style.display = 'none'; // Hide add button on new group
                
                // Clear placeholder values
                templateGroup.querySelectorAll('input').forEach(input => {
                    if (input.name !== 'llm_key_new' && input.id !== 'llm_key_new') {
                        input.value = '';
                    }
                });

                container.insertBefore(templateGroup, document.getElementById('llm_group_add'));
            }

            // Event listener for adding LLM
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('add-llm-btn')) {
                    addLLMGroup();
                }
            });

            // Event listener for removing LLM
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-llm-btn')) {
                    const key = e.target.dataset.llmKey;
                    const groupToRemove = document.getElementById('llm_group_' + key);
                    if (groupToRemove) {
                        groupToRemove.remove();
                    }
                }
            });
        });
    </script>
</body>
</html>