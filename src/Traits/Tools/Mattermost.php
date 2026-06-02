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

    /**
     * Extracts a range of posts from a specified Mattermost channel.
     *
     * @param string $channelId The ID of the target Mattermost channel.
     * @param int $page The page number to retrieve (default 0).
     * @param int $perPage The number of posts per page to retrieve (default 100).
     * @param bool $finish If true, the function returns false (signaling completion/stop).
     * @return bool|string Returns false if $finish is true, otherwise returns null.
     *
     * @throws \Exception If the API call fails.
     */
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'Retrieves a paginated list of posts from a specific Mattermost channel.',
            'name' => 'mm_channel_posts',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'channelId' => ['type' => 'string', 'description' => 'The ID of the target Mattermost channel.'],
                    'page' => ['type' => 'integer', 'description' => 'The page number of the paginated list to retrieve (default 0).'],
                    'perPage' => ['type' => 'integer', 'description' => 'How many posts to retrieve per page (default 100).'],
                    'finish' => ['type' => 'boolean', 'description' => 'If set to true, the agent should stop after retrieving the posts.'],
                ],
                'required' => ['channelId'],
            ],
        ],
    ])]
    public function mm_channel_posts(string $channelId, int $page = 0, int $perPage = 100, bool $finish = false)
    {
        try {
            // 1. Retrieve posts using the MattermostApi helper
            $postsData = $this->mmApi->getChannelPosts($channelId, $page, $perPage, null, null, null, true);

            // 2. Start building the output string
            $output = "=== Posts for page {$page} in mattermost channel {$channelId} ===\n";

            if (empty($postsData['posts'])) {
                $output .= "No posts found on this page.\n";
            } else {
                // 3. Format each post
                foreach ($postsData['posts'] as $post) {
                    $output .= "---
post_id: {$post['post_id']}
user_id: {$post['user_id']}

{$post['message']}
---\n";
                }

                // 4. Add pagination footer
                if (isset($postsData['has_more']) && $postsData['has_more']) {
                    $output .= "=== There are more posts in channel {$channelId} ===\n";
                } else {
                    $output .= "=== There are no more posts in {$channelId} ===\n";
                }
            }

            // 5. Add to context
            $this->current_context .= $output;

            return !$finish;

        } catch (\Exception $e) {
            // Re-throw or handle the API exception
            throw $e;
        }
    }
}
