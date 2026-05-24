<?php

namespace Yekhlakov\PAgent\Traits\Tools;

use Yekhlakov\PAgent\Attributes\LlmTool;

trait Finish
{
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'name' => 'finish',
            'description' => 'If you have nothing more to do, call this function to stop the operation',
        ],
    ])]
    public function executeFinish()
    {
        return false;
    }
}
