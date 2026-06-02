<?php

namespace Yekhlakov\PAgent\Traits;

use ReflectionClass;
use Yekhlakov\PAgent\Attributes\LlmTool;

trait ToolCallRouterTrait
{
    /**
     * @var array<string, array{key: string, description: array, method: string, tag: string}>
     *                                                                                         This set holds all tool metadata, keyed by tool name.
     */
    public array $toolSet = [];

    /**
     * @var array<string>|null
     *                         List of tool names or tags enabled for use.
     */
    public ?array $enabledTools = null;

    /**
     * Determines the name of the trait this method is declared in (converted to snake_case).
     */
    protected function getTraitForMethod(string $methodName): ?string
    {
        // Note: We must use $this->getTraitForMethod() inside a class context,
        // but since this is a trait, we rely on the class that uses it.
        // For simplicity and adherence to the prompt, we assume $this context is available.
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getTraits() as $trait) {
            if ($trait->hasMethod($methodName)) {
                return $trait->getName();
            }
        }

        return null;
    }

    /**
     * Converts a CamelCase trait name to snake_case.
     * e.g., ToolCallRouterTrait -> tool_call_router_trait
     */
    protected function toSnakeCase(string $className): string
    {
        // Remove Trait suffix if present
        $baseName = str_replace('Trait', '', $className);

        // Convert CamelCase to snake_case
        return strtolower(preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', $baseName));
    }

    public function initRouter(): void
    {
        // 1. Reflect $this
        $reflection = new ReflectionClass($this);

        // 2. Iterate over all methods
        foreach ($reflection->getMethods() as $method) {

            // Skip private/protected methods
            if (! $method->isPublic()) {
                continue;
            }

            // 3. Check for the LlmTool attribute
            $attributes = $method->getAttributes(LlmTool::class);

            if (! empty($attributes)) {
                // We assume only one LlmTool attribute per method
                $attribute = $attributes[0];

                // Instantiate the attribute to access its properties
                $handler = $attribute->newInstance();

                $description = $handler->description;

                // Extract KEY from the attribute object
                $key = $description['function']['name'];

                // Get the method name
                $methodName = $method->getName();

                // Determine the trait name and convert it to snake_case
                $traitName = $this->getTraitForMethod($methodName);
                $tag = $traitName ? $this->toSnakeCase(basename($traitName)) : 'unknown';

                // Check for duplicates
                if (isset($this->toolSet[$key])) {
                    echo "!!!!!!!!!!!!!!!!!!! Duplicate tools detected !!!!!!!!!!!!!!!!!!!!!!\n\ttool\tmethod\n\t----\t------\n";

                    foreach ($this->toolSet as $k => $v) {
                        echo "\t$k\t{$v['method']}".($k == $key ? ' <------ ' : '')."\n";
                    }

                    echo "\t$key\t$methodName <------\n";

                    exit();
                }

                // Populate the new $toolSet structure
                $this->toolSet[$key] = [
                    'key' => $key,
                    'description' => $description,
                    'method' => $methodName,
                    'tag' => $tag,
                ];
            }
        }

        echo '--------------- '.count($this->toolSet)." tools are available ---------------\n";
        echo '['.implode(', ', array_keys($this->toolSet))."]\n\n";
    }

    public function cleanValue($value): string
    {
        return trim($value, " \t\n\r\b\0'`\".,;:)");
    }

    public function parseLlmResponse(array $response)
    {
        $this->result = $response['content'] ?? null;

        if (empty($response['tool_calls'])) {
            echo "\n\n----------------- No tool calls are returned, which means it has finished the task. ----------------------\n";
            echo "\n\n----------------- Here is the result: ----------------------\n";

            echo $response['content'] ?? "\n";
            $this->result = $response['content'] ?? "\n";

            return false;
        }

        echo "--------------- Agent processes tool calls ---------------\n";

        foreach ($response['tool_calls'] as $toolCall) {
            $function = $toolCall['function']['name'];
            $args = json_decode($toolCall['function']['arguments'], true);

            echo "------------------------ Processing tool call `$function` ------------------------\n";

            // Retrieve the method name from the new $toolSet structure
            $toolMetadata = $this->toolSet[$function] ?? null;
            $method = $toolMetadata['method'] ?? null;

            if (empty($method) || ! method_exists($this, $method)) {
                echo "------------------------ Handler for `$function` is unavailable, finishing --------------------\n\n";

                return false;
            }

            echo "------------------ Calling `$method` ---------------\n";

            $this->current_context .= "** You have issued a tool call $function with args {".$this->compactJson($args)."}**\n";

            // Execute the handler method
            if (! $this->$method(...$args)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Stores the list of enabled tool names or tags.
     *
     * @param  array<string>  $tools
     * @return $this
     */
    public function withTools(...$tools): self
    {
        if (empty($tools) || ! is_array($tools[0])) {
            $tools = func_get_args();
        }

        $this->enabledTools = array_filter($tools);

        return $this;
    }

    /**
     * Returns an array of tool descriptions suitable for sending to the LLM.
     */
    public function getToolset(): array
    {
        if (! empty($this->enabledTools)) {
            $toolDescriptions = [];
            $enabledTools = array_flip($this->enabledTools); // For O(1) lookups

            foreach ($this->toolSet as $metadata) {
                $key = $metadata['key'];
                $tag = $metadata['tag'];

                // Check if the tool name or the tag is present in the enabled list
                if (isset($enabledTools[$key]) || isset($enabledTools[$tag])) {
                    $toolDescriptions[] = $metadata['description'];
                }
            }

            return $toolDescriptions;
        }

        $toolDescriptions = [];
        foreach ($this->toolSet as $metadata) {
            // The 'description' key already holds the array structure needed for the LLM.
            $toolDescriptions[] = $metadata['description'];
        }

        return $toolDescriptions;
    }
}
