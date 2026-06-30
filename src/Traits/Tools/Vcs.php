<?php

namespace Yekhlakov\PAgent\Traits\Tools;

trait Vcs
{
    protected function getFileNameFromClassName(string $className)
    {
        $fileName = lcfirst(trim(str_replace('\\', '/', $className), ' /\\'));

        if (! str_ends_with($fileName, '.php')) {
            $fileName .= '.php';
        }

        return $fileName;
    }

    protected $projectIdOverride = null;

    public function withProjectId($id)
    {
        $this->projectIdOverride = $id;

        return $this;
    }

    public function getProjectId()
    {
        return $this->projectIdOverride ?? $this->projectId;
    }

    protected function getVcsFile($api, $name)
    {

        $fileName = $this->getFileNameFromClassName($name);

        $errorMessage = "!!! Source code for $className you requested is unavailable! Check if you have provided correct (fully qualified) class name with correct namespace !!!";
        $classMessage = "=== Source code for $className ===";
        $testMessage = "=== Source code of a test for $className ===";

        // Check if we've already loaded the file

        // We tried and failed, notify the model about that
        if (str_contains($this->current_context, $errorMessage)) {
            return "!!! You have already requested source code for $className and it could not be retrieved !!!\n\n";
        }

        // We did, refer to the previously attached file.
        if (str_contains($this->current_context, $classMessage)) {
            return "=== You have already requested source code for $className, see above ===\n\n";
        }

        $content = $api->getFile($this->getProjectId(), $fileName);

        if (empty($content)) {
            return "$errorMessage\n\n";
        }

        $content = "$classMessage\n$content\n\n";

        $testFileName = 'tests/Unit/'.str_replace('.php', 'Test.php', $fileName);

        $testContent = $api->getFile($this->getProjectId(), $testFileName);

        if (! empty($testContent)) {
            $content .= "$testMessage\n$testContent\n\n";
        }

        return $content;

    }
}
