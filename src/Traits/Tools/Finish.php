<?php

namespace Yekhlakov\PAgent\Traits\Tools;

use Yekhlakov\PAgent\Attributes\LlmTool;

trait Finish
{
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'name' => 'finish',
            'description' => 'If you have finished your task, call this function to stop the operation',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'result' => ['type' => 'string', 'desription' => 'Put your task results here (if you are not required to deliver results otherwise, eg write to a file etc)'],
                ],
                'required' => ['result'],
            ],

        ],
    ])]
    public function executeFinish(string $result)
    {
        $this->result = $result;

        return false;
    }
}
