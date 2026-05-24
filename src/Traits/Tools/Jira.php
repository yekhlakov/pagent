<?php

namespace Yekhlakov\PAgent\Traits\Tools;

use Yekhlakov\PAgent\Attributes\LlmTool;

trait Jira
{
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'If you need to get a Jira task text, use this tool. 
The task text (along with several additional fields and comments) will be added to your context.',
            'name' => 'jira_task',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'taskKey' => ['type' => 'string', 'desription' => 'Jira task key (a string in the format LL-NNNN, eg ID-1234, OKR-12, HELP-666'],
                ],
                'required' => ['taskKey'],
            ],
        ],
    ])]
    public function executeJiraTask(string $taskKey)
    {
        $this->current_context .= $this->getFormattedJiraTaskText($taskKey)."\n";
    }

    public function getFormattedJiraTaskText(string $taskKey): string
    {
        $issue = $this->jiraApi->getIssue($taskKey);

        if (empty($issue)) {
            return "!!! Jira task $taskKey is unavailable !!!";
        }

        $formatted = "=== Jira task $taskKey text follows ===\n";

        foreach ($issue as $key => $value) {
            if ($key == 'issue_key') {
                continue;
            }

            $offset = function ($s) {
                $lines = explode("\n", $s);

                return implode("\n", array_map(fn ($s) => '    '.$s, $lines));
            };

            if (is_array($value)) {
                $value = implode("\n", $value);
            }

            $formatted .= '**'.$key."**:\n".$offset($value)."\n\n";
        }

        return $formatted;
    }
}
