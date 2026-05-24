<?php

namespace Yekhlakov\Pagent\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class LlmTool
{
    public function __construct(public array $description) {}
}
