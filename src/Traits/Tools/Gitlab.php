<?php

namespace Yekhlakov\PAgent\Traits\Tools;

use Yekhlakov\PAgent\Attributes\LlmTool;

trait Gitlab
{
    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gitlab_file',
                    'description' => 'If you need source code for a php entity (class, attribute, interface, trait) in a Gitlab project, use this function to get it by its fully qualified name.
The content of the file containing this entity (and associated test, if there\'s one) will be appended to your current context.
Only entities under the \App namespace will be returned. Always check that className contains namespace.
',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'className' => ['type' => 'string', 'desription' => 'Fully qualified name of the class (interface etc), including full namespace, eg `App\Handlers\DefaultHandler`'],
                        ],
                        'required' => ['className'],
                    ],
                ],
            ]
        )
    ]
    public function executeGitlabFile(string $className)
    {
        $this->current_context .= $this->getGitlabFile($className);

        return true;
    }

    protected function getFileNameFromClassName(string $className)
    {
        $fileName = lcfirst(trim(str_replace('\\', '/', $className), ' /\\'));

        return $fileName.'.php';
    }

    protected function getGitlabFile(string $className)
    {
        $fileName = $this->getFileNameFromClassName($className);

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

        $content = $this->gitlabApi->getFile($this->projectId, $fileName);

        if (empty($content)) {
            return "$errorMessage\n\n";
        }

        $content = "$classMessage\n$content\n\n";

        $testFileName = 'tests/Unit/'.str_replace('.php', 'Test.php', $fileName);

        $testContent = $this->gitlabApi->getFile($this->projectId, $testFileName);

        if (! empty($testContent)) {
            $content .= "$testMessage\n$testContent\n\n";
        }

        return $content;

    }

    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gitlab_blame',
                    'description' => 'If you need `git blame` info for a php entity (class, attribute, interface, trait) in the Gitlab project, use this function to get it.
The blame content for the entity will be appended to your current context.
Only entities under the \App namespace will be analyzed. Always check that className contains namespace.
',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'className' => ['type' => 'string', 'desription' => 'Fully qualified name of the class (interface etc), including full namespace, eg `App\Handlers\DefaultHandler`'],
                            'from' => ['type' => 'integer', 'description' => 'First line of the analyzed part of the file'],
                            'to' => ['type' => 'integer', 'description' => 'Last line of the analyzed part of the file'],
                        ],
                        'required' => ['className'],
                    ],
                ],
            ]
        )
    ]
    public function executeGitlabBlame(string $className, int $from = 1, int $to = 1000)
    {
        $this->current_context .= $this->getGitlabBlame($className, $from, $to);

        return true;
    }

    protected function getGitlabBlame(string $className, int $from, int $to)
    {
        $successMessage = "=== Commits for $className ===";
        $errorMessage = "!!! No commit data available for $className !!!";

        if (str_contains($this->current_context, $successMessage)) {
            return "=== You already requested blame for $className, see above ===\n";
        }

        if (str_contains($this->current_context, $errorMessage)) {
            return "!!! You already requested blame for $className and it was not available !!!\n";
        }

        $blames = $this->gitlabApi->getCommitsForRange($this->projectId, $this->getFileNameFromClassName($className), $from, $to);

        if (empty($blames)) {
            return "$errorMessage\n";
        }

        return "$successMessage\n".
            "| Branch name | Commit message |\n".
            "| --- | --- |\n".
            implode("\n", array_map(fn ($blame) => '| '.$blame['branch'].' | '.$blame['commit_message'].' |', $blames)).
            "\n";
    }

    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gitlab_mr',
                    'description' => 'Retrieves the full diff content for a specific Merge Request (MR) in a Gitlab project. The diff content will be appended to your current context.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mrId' => ['type' => 'string|integer', 'description' => 'The ID of the Merge Request (MR ID).'],
                        ],
                        'required' => ['mrId'],
                    ],
                ],
            ]
        )
    ]
    public function executeGitlabMr(string|int $mrId)
    {
        $diff = $this->getMrDiff($mrId);
        $this->current_context .= "=== Merge Request Diff for MR ID $mrId ===\n$diff\n\n";
        return true;
    }

    protected function getMrDiff(string|int $mrId): string
    {
        return $this->gitlabApi->getMRDiff($this->projectId, (string)$mrId);
    }

    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gitlab_ls',
                    'description' => 'If you need to list of all php entities in a namespace in Gitlab project, use this function.
The list will be appended to your context.
Only namespaces under \App will be available.
',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'namespace' => ['type' => 'string', 'desription' => 'Fully qualified name of the namespace, eg `App\Handlers`'],
                        ],
                        'required' => ['namespace'],
                    ],
                ],
            ]
        )
    ]
    public function executeGitlabLs(string $namespace)
    {
        $dirName = lcfirst(trim(str_replace('\\', '/', $namespace), ' /\\'));
        if (! str_starts_with($dirName, 'app')) {
            $this->current_context .= "!!! You requested $namespace outside of \\App, it cannot be retrieved !!!\n";

            return true;
        }

        $contents = $this->listNamespaceFiles($namespace);

        if (empty($contents)) {
            $this->current_context .= "=== $namespace has no php entities ===\n";

            return true;
        }

        $this->current_context .=
            "=== Php entities in the namespace $namespace ===".
            implode("\n", $contents).
            "\n";

        return true;
    }

    protected function listNamespaceFiles($namespace): array
    {
        $dirName = lcfirst(trim(str_replace('\\', '/', $namespace), ' /\\'));
        $contents = $this->gitlabApi->getDirectoryContents($this->projectId, $dirName);

        $classes = [];

        foreach ($contents as $item) {
            if (str_ends_with($item, '.php')) {
                $classes[] = $namespace.'\\'.str_replace('.php', '', $item);
            } else {
                $classes = array_merge($classes, $this->listNamespaceFiles($namespace.'\\'.$item));
            }
        }

        return $classes;
    }
}