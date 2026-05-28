<?php

namespace Yekhlakov\PAgent\Traits\Tools;

use Yekhlakov\PAgent\Attributes\LlmTool;

/**
 * Provides tools for interacting with the Mattermost API.
 */
trait Mattermost
{
    /**
     * Posts content to a specified Mattermost channel.
     *
     * @param string $channelId The ID of the channel where the message should be posted.
     * @param string $content The text content of the message.
     * @param bool $finish If true, the function returns false (signaling completion/stop).
     * @return bool|string Returns false if $finish is true, otherwise returns the API response body.
     *
     * @throws \Exception If the API call fails.
     */
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'Posts a message to a specific Mattermost channel.',
            'name' => 'mm_post',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'channelId' => ['type' => 'string', 'description' => 'The ID of the target Mattermost channel.'],
                    'content' => ['type' => 'string', 'description' => 'The message content to be posted.'],
                    'finish' => ['type' => 'boolean', 'description' => 'If set to true, the agent should stop after posting the message.'],
                ],
                'required' => ['channelId', 'content'],
            ],
        ],
    ])]
    public function mm_post(string $channelId, string $content, bool $finish = false)
    {
        try {
            $this->mmApi->postToMattermost($channelId, $content);

	    $this->current_context .= "--- The post was successfully posted to Mattermost channel $channelId ---\n";

            return !$finish;

        } catch (\Exception $e) {
            // Re-throw or handle the API exception
            throw $e;
        }
    }
}
