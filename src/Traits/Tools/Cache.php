<?php

namespace Yekhlakov\PAgent\Traits\Tools;

use Yekhlakov\PAgent\Attributes\LlmTool;

trait Cache
{
    /**
     * Sanitize a string to make it safe for use as a Windows filename.
     *
     * This function removes illegal characters, normalizes whitespace,
     * and attempts to prevent the name from matching reserved DOS system names.
     *
     * @param  string  $inputString  The raw string to be sanitized.
     * @param  bool  $allowDots  Whether to allow periods (.) in the filename.
     * @return string The sanitized filename.
     */
    protected function makeSafeWindowsFilename(string $inputString, bool $allowDots = true): string
    {
        // 1. Define Illegal Characters (Windows Restrictions)
        // These characters are forbidden in standard Windows filenames.
        $illegalChars = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];

        // 2. Define Reserved DOS Names (Must handle these separately)
        // These are names that the OS reserves for system functions.
        $reservedNames = [
            'CON', 'PRN', 'AUX', 'NUL',
            'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
            'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
        ];

        // --- Sanitization Steps ---

        // 3. Remove Control Characters (e.g., null bytes, tabs)
        // \x00 is the null byte, which is highly problematic.
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $inputString);

        // 4. Replace illegal characters with an underscore
        $sanitized = str_replace($illegalChars, '_', $sanitized);

        // 5. Normalize Whitespace and Punctuation
        // Replace multiple spaces or underscores with a single underscore
        $sanitized = preg_replace('/[\s_]+/', '_', $sanitized);
        $sanitized = preg_replace('/-+/', '-', $sanitized);

        // 6. Handle periods/dots based on configuration
        if (! $allowDots) {
            $sanitized = str_replace('.', '', $sanitized);
        }

        // 7. Trim leading/trailing underscores, dots and hyphens
        $sanitized = trim($sanitized, '_.-');

        // 8. Handle Reserved Names Check
        // Check if the resulting name (case-insensitive) matches a reserved name.
        $upperName = strtoupper($sanitized);
        if (in_array($upperName, $reservedNames)) {
            // If it matches a reserved name, append a unique identifier to make it safe.
            $sanitized = $sanitized.'_file';
        }

        // 9. Ensure the string is not empty after all replacements
        if (empty($sanitized)) {
            return 'untitled'; // Fallback name
        }

        return $sanitized;
    }

    /**
     * Removes a prefix from a string if the string begins with that prefix.
     *
     * @param  string  $string  The original string.
     * @param  string  $pfx  The prefix to remove.
     * @return string The string with the prefix removed, or the original string if no match.
     */
    protected function removePrefix(string $string, string $pfx): string
    {
        if (str_starts_with($string, $pfx)) {
            // Use substr to return the rest of the string, starting after the prefix length
            return substr($string, strlen($pfx));
        }

        return $string;
    }

    protected function prepareFileName(string $fileName)
    {
        $fileName = preg_replace('/[^a-zA-Z0-9_\\:]/', '', $fileName);
        $fileName = preg_replace('/\\+/', '\\', $fileName);

        return $fileName;
    }

    public array $saved_files = [];

    protected function writeTextToFile($tag, $content)
    {
        $filename = $this->outputDirectory.$this->makeSafeWindowsFilename($this->getDateTimeString().'-output-'.$tag, false).'.md';

        file_put_contents($filename, $content);

        $this->current_context .= "\nThe '$tag' was successfully written out.\n\n";

        $this->saved_files[] = [
            'tag' => $tag,
            'filename' => $filename,
            'content' => $content,
        ];

        // UPDATE PERSISTENT FILE CACHE IMMEDIATELY
        $this->storeFileCache('file-cache.json');
    }

    protected function getSavedFileList()
    {
        if (empty($this->saved_files)) {
            return '';
        }

        $list = "=== **Cached files** ===\n";

        foreach ($this->saved_files as $file) {
            $list .= '* '.$file['tag']."\n";
        }

        return "$list\n";
    }

    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'If you need to save something to a temp file, call this function. The file is put to a cache and can be retrieved later.',
            'name' => 'cache_save',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fileName' => ['type' => 'string', 'desription' => 'Name of the file to be cached (should be short and concise)'],
                    'content' => ['type' => 'string', 'desription' => 'Content to be stored to the file'],
                    'finish' => ['type' => 'boolean', 'description' => 'Set this to true, if you don\'t need to do anything more after the saving of the file'],
                ],
                'required' => ['fileName', 'content'],
            ],
        ],
    ])]
    public function executeCacheSave(string $fileName, string $content, bool $finish = false)
    {
        $fileName = $this->prepareFileName($fileName);

        $this->writeTextToFile($fileName, $content);

        return ! $finish;
    }

    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'If you need to read your most recently cached file, call this tool',
            'name' => 'cache_read_latest',
            'parameters' => [
                'type' => 'object',
                'properties' => (object) [],
                'required' => [],
            ],
        ],
    ])]
    public function executeFreadLatest()
    {
        if (! count($this->saved_files)) {
            $this->current_context .= "!!! There are no cached files, so the latest file could not be retrieved. !!!\n";
        } else {
            $latestFilenameString = '=== The latest cached file with a name "'.$this->saved_files[count($this->saved_files) - 1]['tag'].'" follows ===';

            if (str_contains($this->current_context, $latestFilenameString)) {
                $this->current_context .= "=== You have already requested the latest file with a name '$fileName', see above ===\n\n";

                return true;
            }

            $this->current_context .= "$latestFilenameString\n".$this->saved_files[count($this->saved_files) - 1]['content'];

            return true;
        }

        return true;

    }

    public function getFileTextForContext(string $fileName)
    {
        $fileName = $this->prepareFileName($fileName);
        $filenameString = "=== The saved file with a name '$fileName' follows ===";

        foreach ($this->saved_files as $savedFile) {
            if ($savedFile['tag'] == $fileName) {
                return "$filenameString\n".
                    $savedFile['content']."\n\n";

            }
        }

        throw new \Exception('No file named '.$fileName.' found!');
    }

    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'If you need to read a file you have previously written to file cache, call this function',
            'name' => 'cache_read',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fileName' => ['type' => 'string', 'desription' => 'Name of the file to be read (provide empty string to read the latest file)'],
                ],
                'required' => ['fileName'],
            ],
        ],
    ])]
    public function executeCacheRead(?string $fileName = '')
    {
        $fileName = $this->prepareFileName($fileName);

        if (empty($fileName)) {
            // Try to get latest file
            return $this->executeFreadLatest();
        }

        $filenameString = "=== The cached file with a name '$fileName' follows ===";

        if (str_contains($this->current_context, $filenameString)) {
            $this->current_context .= "=== You have already requested a cached file with a name '$fileName', see above ===\n\n";

            return true;
        }

        foreach ($this->saved_files as $savedFile) {
            if ($savedFile['tag'] == $fileName) {
                $this->current_context .= "$filenameString\n".
                    $savedFile['content']."\n\n";

                return true;
            }
        }

        $this->current_context .= "!!! The file '$fileName' was not cached and could not be retrieved. !!!\n";

        return true;
    }
}
