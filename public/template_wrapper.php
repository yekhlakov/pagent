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
        <?php include 'main_page.php'; // Include job list ?>
    </div>
    <div class="right-column">
        <?php 
        // Determine which content template to include
        if ($selectedJob): 
            include 'job_view_form.php';
        elseif (isset($_GET['view']) && $_GET['view'] === 'settings'): 
            include 'settings_form.php';
        else: 
            include 'job_creation_form.php';
        endif; 
        ?>
    </div>
</body>
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