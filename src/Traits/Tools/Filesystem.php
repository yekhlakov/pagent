<?php

namespace Yekhlakov\PAgent\Traits\Tools;

use Yekhlakov\PAgent\Attributes\LlmTool;

trait Filesystem
{
    /**
     * Internal helper to sanitize and resolve a relative path against the root directory.
     *
     * Ensures path uses forward slashes, resolves '..', removes duplicates, and removes trailing slashes.
     *
     * @param  string  $fileName  The relative file name provided by the LLM.
     * @return string The fully resolved and sanitized absolute path.
     */
    protected function getAbsoluteFilePath(string $fileName): string
    {
        // 1. Normalize slashes and remove duplicates
        $normalized = str_replace('\\', '/', $fileName);
        $normalized = preg_replace('/\/+/', '/', $normalized);

        // 2. Resolve '..' and '.' components
        $parts = array_filter(explode('/', $normalized));
        $resolvedParts = [];
        $currentPath = '';

        foreach ($parts as $part) {
            if ($part === '..') {
                // Go up one directory, unless we are already at the root
                if (! empty($resolvedParts)) {
                    array_pop($resolvedParts);
                }
            } elseif ($part === '.') {
                // Ignore current directory reference
                continue;
            } else {
                // Add the valid directory/file name
                $resolvedParts[] = $part;
            }
        }

        // Rebuild the path
        $relativePath = implode('/', $resolvedParts);

        // 3. Add the root directory and ensure proper trailing slash handling
        $rootDir = $this->config['filesystem']['root_directory'] ?? getcwd();
        $fullPath = rtrim($rootDir, '/').'/'.ltrim($relativePath, '/');

        return $fullPath;
    }

    /**
     * Lists the contents of a specified directory.
     *
     * @param  string  $dirName  The relative path to the directory.
     * @return bool True on success, false on failure.
     */
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'Lists all files and subdirectories within a specified directory in the local file system. Don`t query directory names like `.` and `..`!',
            'name' => 'fdir',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'dirName' => ['type' => 'string', 'description' => 'The path to the directory to list (e.g., "data").'],
                ],
                'required' => ['dirName'],
            ],
        ],
    ])]
    public function executeFdir(string $dirName): bool
    {
        $absolutePath = $this->getAbsoluteFilePath($dirName);

        if (! is_dir($absolutePath)) {
            $this->current_context .= "!!! Error: Directory '$dirName' does not exist in the local file system or is not a directory. !!!\n";

            return false;
        }

        $entries = scandir($absolutePath);
        $directories = [];
        $files = [];

        if ($entries === false) {
            $this->current_context .= "!!! Error: Could not read contents of directory '$dirName' in local file system. Check permissions. !!!\n";

            return false;
        }

        // Filter out '.' and '..' and categorize entries
        $filteredEntries = array_diff($entries, ['.', '..']);

        foreach ($filteredEntries as $entryName) {
            $fullPath = $absolutePath.'/'.$entryName;
            if (is_dir($fullPath)) {
                $directories[] = $fullPath;
            } else {
                $files[] = $fullPath;
            }
        }

        // Format output: Directories first, then Files
        $output = "\n=== Directory contents for '$dirName' (in local file system) ===\n";

        // 1. Directories
        $output .= "\n--- Directories ---\n";
        if (empty($directories)) {
            $output .= "No subdirectories found.\n";
        } else {
            foreach ($directories as $dir) {
                $output .= "DIRECTORY: $dir\n";
            }
        }

        // 2. Files
        $output .= "\n--- Files ---\n";
        if (empty($files)) {
            $output .= "No files found.\n";
        } else {
            foreach ($files as $file) {
                $output .= "FILE: $file\n";
            }
        }

        $this->current_context .= $output."\n";

        return true;
    }

    /**
     * Reads the content of one or more files from the local filesystem sequentially.
     *
     * @param  array  $fileNames  An array of relative file paths.
     * @return bool True if all files were read successfully, false otherwise.
     */
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'Reads the content of one or more specified files from the local filesystem sequentially.',
            'name' => 'fread',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fileNames' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'An array of relative paths to the files (e.g., ["data/file1.txt", "data/file2.txt"]).',
                    ],
                ],
                'required' => ['fileNames'],
            ],
        ],
    ])]
    public function executeFread(array $fileNames): bool
    {
        echo 'FREAD called with '.json_encode($fileNames)."\n";

        foreach ($fileNames as $fileName) {
            $absolutePath = $this->getAbsoluteFilePath($fileName);

            if (! file_exists($absolutePath)) {
                $this->current_context .= "!!! Error: File '$fileName' does not exist at the specified path. !!!\n";

                continue;
            }

            try {
                $content = file_get_contents($absolutePath);
                if ($content === false) {
                    $this->current_context .= "!!! Error: Could not read the content of file '$fileName'. !!!\n";

                    continue;
                }

                $this->current_context .= "=== The file '$fileName' contents follow ===\n".$content."\n";
            } catch (\Exception $e) {
                $this->current_context .= "!!! Exception occurred while reading '$fileName': ".$e->getMessage()." !!!\n";
            }
        }

        return true;
    }

    /**
     * Writes content to a file, completely replacing its existing content.
     *
     * @param  string  $fileName  The relative file path.
     * @param  string  $content  The data to write.
     * @return bool True on success, false on failure.
     */
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'Writes content to a file in the local file system, completely overwriting any existing content. If the file does not exist, it will be created.',
            'name' => 'fwrite',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fileName' => ['type' => 'string', 'description' => 'The relative path to the file.'],
                    'content' => ['type' => 'string', 'description' => 'The complete content to write to the file.'],
                    'finish' => ['type' => 'boolean', 'description' => 'If you have nothing to do after the write, set this task to finish your work'],
                ],
                'required' => ['fileName', 'content'],
            ],
        ],
    ])]
    public function executeFwrite(string $fileName, string $content, bool $finish = false): bool
    {
        $absolutePath = $this->getAbsoluteFilePath($fileName);

        try {
            $success = file_put_contents($absolutePath, $content);

            if ($success !== false) {
                $this->current_context .= "=== Successfully wrote content to file '$fileName'. ===\n\n";

                return ! $finish;
            } else {
                $this->current_context .= "!!! Error: Failed to write content to file '$fileName'. Check permissions or path. !!!\n";

                return false; // Fatal error, cannot proceed
            }
        } catch (\Exception $e) {
            $this->current_context .= "!!! Exception occurred while writing to '$fileName': ".$e->getMessage()." !!!\n";

            return false; // Fatal error, cannot proceed
        }
    }

    /**
     * Patches a portion of a file by replacing lines between startLine and endLine.
     *
     * @param  string  $fileName  The relative file path.
     * @param  int  $startLine  The first line of the fragment to replace (default: 1).
     * @param  int  $endLine  The last line of the fragment to replace (default: last line).
     * @param  string  $content  The new text to replace the fragment with. Can be empty for deletion.
     * @return bool True on success, false on failure.
     */
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'Modifies a range of lines of an existing file in the local file system. The replacement starts after the `startLine` and ends just before `endLine`. If `content` is empty, the segment is deleted. Lines are numbered starting from 1.',
            'name' => 'fpatch',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fileName' => ['type' => 'string', 'description' => 'The relative path to the file.'],
                    'prevLine' => ['type' => 'integer', 'description' => 'The line number immediately BEFORE the part to replace (default is 0 which is before the first line). The file from the beginning to this line will not be affected by patch.'],
                    'nextLine' => ['type' => 'integer', 'description' => 'The line number immediately AFTER the part to replace (default is the last line + 1). The file from this line to the end will not be affected by patch.'],
                    'content' => ['type' => 'string', 'description' => 'The new content to write over the replaced fragment. Use an empty string ("") to delete the fragment.'],
                    'finish' => ['type' => 'boolean', 'description' => 'If you have nothing to do after the write, set this task to finish your work'],
                ],
                'required' => ['fileName', 'content'],
            ],
        ],
    ])]
    public function executeFpatch(string $fileName, int $prevLine = 0, ?int $nextLine = null, string $content = '', bool $finish = false): bool
    {
        $absolutePath = $this->getAbsoluteFilePath($fileName);

        if (! file_exists($absolutePath)) {
            $this->current_context .= "!!! Error: File '$fileName' does not exist. Cannot patch. !!!\n";

            return false; // Fatal error, cannot proceed
        }

        try {
            $originalContent = file_get_contents($absolutePath);
            if ($originalContent === false) {
                $this->current_context .= "!!! Error: Could not read file '$fileName' for patching. !!!\n";

                return false; // Fatal error, cannot proceed
            }

            // Split into lines, preserving the structure
            $lines = explode("\n", $originalContent);
            $totalLines = count($lines);

            // Handle default endLine
            if ($nextLine === null) {
                $nextLine = $totalLines + 1;
            }

            // Ensure line numbers are within bounds and positive
            $startLine = max(0, min($prevLine, $totalLines));
            $endLine = max($startLine + 1, min($nextLine, $totalLines + 1)) - 1;

            // Get the new lines
            $newLines = explode("\n", $content);

            // Perform the patch
            $newLinesArray = array_slice($lines, 0, $startLine); // Lines before patch
            $newLinesArray = array_merge($newLinesArray, $newLines); // Insert new content
            $newLinesArray = array_merge($newLinesArray, array_slice($lines, $endLine)); // Lines after patch

            // Rejoin the content
            $newContent = implode("\n", $newLinesArray);

            // Write the patched content back
            $success = file_put_contents($absolutePath, $newContent);

            if ($success !== false) {
                $this->current_context .= "=== Successfully patched file '$fileName' from line $startLine to $endLine. ===\n\n";

                return ! $finish; // Return false if we're requested to finish work and true otherwise
            } else {
                $this->current_context .= "!!! Error: Failed to write patched content to '$fileName'. !!!\n";

                return false; // Fatal error, cannot proceed
            }

        } catch (\Exception $e) {
            $this->current_context .= "!!! Exception occurred while patching '$fileName': ".$e->getMessage()." !!!\n";

            return false; // Fatal error, cannot proceed
        }
    }
}
