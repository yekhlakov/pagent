<?php

namespace Yekhlakov\PAgent\Traits;

use Yekhlakov\PAgent\Attributes\LlmTool;

trait ToolCallRouterTrait
{
    // This will be sent to llm
    public array $tools = [];

    // This maps tool name to handler method
    public array $tool_handlers = [];

    public function initRouter(): void
    {
        // 1. Reflect $this
        $reflection = new \ReflectionClass($this);

        // 2. Iterate over all public methods
        // We use getMethods() and filter for public visibility
        foreach ($reflection->getMethods() as $method) {

            // Skip private/protected methods (though reflection usually handles visibility checks)
            if (! $method->isPublic()) {
                continue;
            }

            // 3. Check for the QueryHandler attribute
            $attributes = $method->getAttributes(LlmTool::class);

            if (! empty($attributes)) {
                // We assume only one QueryHandler attribute per method for simplicity
                $attribute = $attributes[0];

                // Instantiate the attribute to access its properties
                $handler = $attribute->newInstance();

                $description = $handler->description;

                // Extract KEY from the attribute object
                $key = $description['function']['name'];

                // Get the method name
                $methodName = $method->getName();

                if (! empty($this->tool_handlers[$key])) {
                    echo "!!!!!!!!!!!!!!!!!!! Duplicate tools detected !!!!!!!!!!!!!!!!!!!!!!\n\ttool\tmethod\n\t----\t------\n";

                    foreach ($this->tool_handlers as $k => $v) {
                        echo "\t$k\t$v".($k == $key ? ' <------ ' : '')."\n";
                    }

                    echo "\t$key\t$methodName <------\n";

                    exit();
                }

                $this->tools[] = $description;
                $this->tool_handlers[$key] = $methodName;
            }
        }

        echo '--------------- '.count($this->tools)." tools are available ---------------\n";
        echo '['.implode(', ', array_keys($this->tool_handlers))."]\n\n";
    }

    public function cleanValue($value): string
    {
        return trim($value, " \t\n\r\b\0'`\".,;:)");
    }

    public function parseLlmResponse(array $response)
    {
        $this->result = $response['content'];

        if (empty($response['tool_calls'])) {
            echo "\n\n----------------- No tool calls are returned, which means it has finished the task. ----------------------\n";
            echo "\n\n----------------- Here is the result: ----------------------\n";

            echo $response['content']."\n\n";

            return false;
        }

        echo "--------------- Agent processes tool calls ---------------\n";

        foreach ($response['tool_calls'] as $toolCall) {
            $function = $toolCall['function']['name'];
            $args = json_decode($toolCall['function']['arguments'], true);

            echo "------------------------ Processing tool call `$function` ------------------------\n";

            $method = $this->tool_handlers[$function] ?? null;

            if (empty($method)) {
                echo "------------------------ Handler for `$function` is unavailable, finishing --------------------\n\n";

                return false;
            }

            if (! $this->$method(...$args)) {
                return false;
            }
        }

        return true;
    }
}
