<?php

namespace Yekhlakov\PAgent\Traits\Tools;

/**
 * Trait BitbucketTools
 *
 * Provides helper methods for interacting with the Bitbucket API.
 */
trait Bitbucket
{
    #[
        LlmTool(
            [
                'type' => 'function',
                'function' => [
                    'name' => 'bitbucket_file',
                    'description' => 'Retrieves source code for php files (*.php) or php entities (class, attribute, interface, trait) from the Bitbucket project. The content of the files (and associated test, if there\'s one) will be appended to your current context. Only entities under the \App namespace will be returned.',
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
    public function executeBitbucketFile(array $names)
    {
        foreach ($names as $name) {
            $this->current_context .= $this->getVcsFile($this->bitbucketApi, $name);
        }

        return true;
    }
}
