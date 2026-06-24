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
                    'description' => 'Retrieves source code for php files (*.php) or php entities (class, attribute, interface, trait) from a Gitlab project. The content of the files (and associated test, if there\'s one) will be appended to your current context. Only entities under the \App namespace will be returned.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'names' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Array of fully qualified names of the classes (interface etc) or file paths to retrieve, e.g., ["App\\Handlers\\DefaultHandler", "app/Services/AnotherService.php"]',
                            ],
                        ],
                        'required' => ['names'],
                    ],
                ],
            ]
        )
    ]
    public function executeGitlabFile(array $names)
    {
        foreach ($names as $name) {
            $this->current_context .= $this->getVcsFile($this->gitlabApi, $name);
        }

        return true;
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

        $blames = $this->gitlabApi->getCommitsForRange($this->getProjectId(), $this->getFileNameFromClassName($className), $from, $to);

        if (empty($blames)) {
            return "$errorMessage\n";
        }

        return "$successMessage\n".
            "| Branch name | Commit message |\n".
            "| --- | --- |\n".
            implode("\n", array_map(fn ($blame) => '| '.$blame['branch'].' | '.$blame['commit_message'].' |', $blame)).
            "\n";
    }

    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gitlab_mr_diff',
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
    public function executeGitlabMrDiff(string|int $mrId)
    {
        $diff = $this->getMrDiffContent($mrId);
        $this->current_context .= "=== Merge Request Diff for MR ID $mrId ===\n$diff\n\n";

        return true;
    }

    protected function getMrDiffContent(string|int $mrId): string
    {
        return $this->gitlabApi->getMRDiff($this->getProjectId(), (string) $mrId);
    }

    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gitlab_mr',
                    'description' => 'Retrieves detailed information about a specific Merge Request (MR) in a Gitlab project. The MR details will be appended to your current context.',
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
    public function executeGitlabMrInfo(string|int $mrId)
    {
        $info = $this->getMrInfo($mrId);
        $this->current_context .= "=== Merge Request Info for MR ID $mrId ===\n$info\n\n";

        return true;
    }

    protected function getMrInfo(string|int $mrId): string
    {
        // Assuming gitlabApi has a method to get MR info
        return $this->gitlabApi->getMRInfo($this->getProjectId(), (string) $mrId);
    }

    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gitlab_mr_comment',
                    'description' => 'Posts a detailed comment on a specific line in a Merge Request (MR) in a Gitlab project. If line-specific details (baseSha, startSha, headSha, newPath, newLine) are not provided, it falls back to posting a general note.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mrId' => ['type' => 'string|integer', 'description' => 'The ID of the Merge Request (MR ID).'],
                            'commentBody' => ['type' => 'string', 'description' => 'The content of the comment to be posted.'],
                            'baseSha' => ['type' => 'string', 'description' => 'The SHA of the base commit (required for line commenting).'],
                            'startSha' => ['type' => 'string', 'description' => 'The SHA of the start commit (required for line commenting).'],
                            'headSha' => ['type' => 'string', 'description' => 'The SHA of the head commit (required for line commenting).'],
                            'newPath' => ['type' => 'string', 'description' => 'The path to the file (required for line commenting).'],
                            'newLine' => ['type' => 'integer', 'description' => 'The line number (required for line commenting).'],
                        ],
                        'required' => ['mrId', 'commentBody', 'baseSha', 'startSha', 'headSha', 'newPath', 'newLine'],
                    ],
                ],
            ]
        )
    ]
    public function executeGitlabMrComment(
        string|int $mrId,
        string $commentBody,
        ?string $baseSha = null,
        ?string $startSha = null,
        ?string $headSha = null,
        ?string $newPath = null,
        ?int $newLine = null
    ) {
        // Check if all line-specific arguments are present
        $isLineComment = $baseSha !== null && $startSha !== null && $headSha !== null && $newPath !== null && $newLine !== null;

        if ($isLineComment) {
            // Post detailed line comment
            $success = $this->gitlabApi->postMRComment(
                $this->getProjectId(),
                (string) $mrId,
                $baseSha,
                $startSha,
                $headSha,
                $newPath,
                $newLine,
                $commentBody
            );

            if ($success) {
                $this->current_context .= "=== Line comment posted successfully to MR ID $mrId at line $newLine ===\n";
            } else {
                $this->current_context .= "=== Failed to post line comment to MR ID $mrId. Check API connection/permissions. ===\n";
            }
        } else {
            // Fallback to general note
            $this->executeGitlabMrNote($mrId, $commentBody);
        }

        return true;
    }

    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gitlab_mr_note',
                    'description' => 'Posts a general, non-line-specific note/comment on a specific Merge Request (MR) in a Gitlab project. The outcome (success or failure) is added to the context.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mrId' => ['type' => 'string|integer', 'description' => 'The ID of the Merge Request (MR ID).'],
                            'commentBody' => ['type' => 'string', 'description' => 'The content of the note/comment to be posted.'],
                        ],
                        'required' => ['mrId', 'commentBody'],
                    ],
                ],
            ]
        )
    ]
    public function executeGitlabMrNote(string|int $mrId, string $commentBody)
    {
        $success = $this->gitlabApi->postGeneralMRComment($this->getProjectId(), (string) $mrId, $commentBody);

        if ($success) {
            $this->current_context .= "=== General note posted successfully to MR ID $mrId ===\n";
        } else {
            $this->current_context .= "=== Failed to post general note to MR ID $mrId. Check API connection/permissions. ===\n";
        }

        return true;
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
        $contents = $this->gitlabApi->getDirectoryContents($this->getProjectId(), $dirName);

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
