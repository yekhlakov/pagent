<?php foreach ($jobList as $job): ?>
    <div class="job-item <?php echo ($selectedJob === $job['dir']) ? 'selected' : ''; ?>" 
         onclick="location.href='?job=<?php echo urlencode($job['dir']); ?>'">
        <div><?php echo htmlspecialchars($job['title']); ?></div>
        <small style="color: #666;"><?php echo htmlspecialchars($job['created_at']); ?></small>
    </div>
<?php endforeach; ?>