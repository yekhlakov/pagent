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
            'description' => 'Lists all files and subdirectories within a specified directory.',
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
            $this->current_context .= "!!! Error: Directory '$dirName' does not exist or is not a directory. !!!\n";

            return false;
        }

        $entries = scandir($absolutePath);
        $directories = [];
        $files = [];

        if ($entries === false) {
            $this->current_context .= "!!! Error: Could not read contents of directory '$dirName'. Check permissions. !!!\n";

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
        $output = "\n=== Directory contents for '$dirName' ===\n";

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
     * Reads the content of a file from the local filesystem.
     *
     * @param  string  $fileName  The relative file path.
     * @return string|null The content of the file, or null if the file is not found.
     */
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'Reads the content of a specified file from the local filesystem.',
            'name' => 'fread',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fileName' => ['type' => 'string', 'description' => 'The relative path to the file (e.g., "data/file.txt").'],
                ],
                'required' => ['fileName'],
            ],
        ],
    ])]
    public function executeFread(string $fileName): ?string
    {
        $absolutePath = $this->getAbsoluteFilePath($fileName);

        if (! file_exists($absolutePath)) {
            $this->current_context .= "!!! Error: File '$fileName' does not exist at the specified path. !!!\n";

            return null;
        }

        try {
            $content = file_get_contents($absolutePath);
            if ($content === false) {
                $this->current_context .= "!!! Error: Could not read the content of file '$fileName'. !!!\n";

                return null;
            }

            $this->current_context .= "=== The file '$fileName' contents follow ===\n".$content."\n";

            return true;
        } catch (\Exception $e) {
            $this->current_context .= "!!! Exception occurred while reading '$fileName': ".$e->getMessage()." !!!\n";

            return null;
        }
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

                return false;
            }
        } catch (\Exception $e) {
            $this->current_context .= "!!! Exception occurred while writing to '$fileName': ".$e->getMessage()." !!!\n";

            return false;
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
            'description' => 'Modifies a segment of an existing file. The replacement starts at startLine and ends at endLine. If content is empty, the segment is deleted.',
            'name' => 'fpatch',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fileName' => ['type' => 'string', 'description' => 'The relative path to the file.'],
                    'startLine' => ['type' => 'integer', 'description' => 'The first line number to replace (default is 1).'],
                    'endLine' => ['type' => 'integer', 'description' => 'The last line number to replace (default is the last line).'],
                    'content' => ['type' => 'string', 'description' => 'The new content to write over the replaced fragment. Use an empty string ("") to delete the fragment.'],
                    'finish' => ['type' => 'boolean', 'description' => 'If you have nothing to do after the write, set this task to finish your work'],
                ],
                'required' => ['fileName', 'content'],
            ],
        ],
    ])]
    public function executeFpatch(string $fileName, int $startLine = 1, ?int $endLine = null, string $content = ''): bool
    {
        $absolutePath = $this->getAbsoluteFilePath($fileName);

        if (! file_exists($absolutePath)) {
            $this->current_context .= "!!! Error: File '$fileName' does not exist. Cannot patch. !!!\n";

            return false;
        }

        try {
            $originalContent = file_get_contents($absolutePath);
            if ($originalContent === false) {
                $this->current_context .= "!!! Error: Could not read file '$fileName' for patching. !!!\n";

                return false;
            }

            // Split into lines, preserving the structure
            // Note: Using "\n" for splitting and "\n" for joining assumes standard Unix line endings.
            $lines = explode("\n", $originalContent);
            $totalLines = count($lines);

            // Handle default endLine
            if ($endLine === null) {
                $endLine = $totalLines;
            }

            // Ensure line numbers are within bounds and positive
            $startLine = max(1, min($startLine, $totalLines));
            $endLine = max(1, min($endLine, $totalLines));

            if ($startLine > $endLine) {
                $this->current_context .= "!!! Error: Invalid line range specified for patching (startLine > endLine). !!!\n";

                return false;
            }

            // The lines array is 0-indexed, so we adjust indices.
            $startIndex = $startLine - 1;
            $endIndex = $endLine; // PHP slice is exclusive of the end index

            // Get the new lines
            $newLines = explode("\n", $content);

            // Perform the patch
            $newLinesArray = array_slice($lines, 0, $startIndex); // Lines before patch
            $newLinesArray = array_merge($newLinesArray, $newLines); // Insert new content
            $newLinesArray = array_merge($newLinesArray, array_slice($lines, $endIndex)); // Lines after patch

            // Rejoin the content
            $newContent = implode("\n", $newLinesArray);

            // Write the patched content back
            $success = file_put_contents($absolutePath, $newContent);

            if ($success !== false) {
                $this->current_context .= "=== Successfully patched file '$fileName' from line $startLine to $endLine. ===\n\n";

                return ! $finish;
            } else {
                $this->current_context .= "!!! Error: Failed to write patched content to '$fileName'. !!!\n";

                return false;
            }

        } catch (\Exception $e) {
            $this->current_context .= "!!! Exception occurred while patching '$fileName': ".$e->getMessage()." !!!\n";

            return false;
        }
    }
}
