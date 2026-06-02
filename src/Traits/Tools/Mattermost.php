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
                ],
                'required' => ['channelId'],
            ],
        ],
    ])]
    public function mm_channel_posts(string $channelId, int $page = 0, int $perPage = 100)
    {
        try {
            // 1. Retrieve posts using the MattermostApi helper
            $postsData = $this->mmApi->getChannelPosts($channelId, $page, $perPage, null, null, null, true);

            // 2. Format the output using the helper method
            $output = $this->formatPostsOutput(
                $postsData['posts'] ?? [],
                $channelId,
                $postsData['has_more'] ?? false,
                "Posts for page {$page} in mattermost channel {$channelId} (Chronological order)"
            );

            // 3. Add to context
            $this->current_context .= $output;

            return true;

        } catch (\Exception $e) {
            // Re-throw or handle the API exception
            throw $e;
        }
    }

    /**
     * Retrieves all chronological posts (comments) from a specific thread.
     *
     * @param string $postId The ID of the root post (the thread initiator).
     * @param bool $filterPosts Whether to filter and simplify the post object.
     * @param bool $finish If true, the function returns false (signaling completion/stop).
     * @return bool|string Returns false if $finish is true, otherwise returns null.
     *
     * @throws \Exception If the API call fails.
     */
    #[LlmTool([
        'type' => 'function',
        'function' => [
            'description' => 'Retrieves all chronological replies/comments within a specific Mattermost thread.',
            'name' => 'mm_thread_posts',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'postId' => ['type' => 'string', 'description' => 'The ID of the root post that started the thread.'],
                    'filterPosts' => ['type' => 'boolean', 'description' => 'If true, the post objects will be simplified to include only essential fields (ID, user_id, message, timestamps). Default is true.'],
                ],
                'required' => ['postId'],
            ],
        ],
    ])]
    public function mm_thread_posts(string $postId, bool $filterPosts = true)
    {
        try {
            // 1. Retrieve posts using the MattermostApi helper
            $postsData = $this->mmApi->getThreadPosts($postId, $filterPosts);

            // 2. Format the output using the helper method
            $output = $this->formatPostsOutput(
                $postsData, // PostsData is already the array of posts in thread context
                $postId,
                false, // Threads don't have 'has_more' in the same sense
                "Posts in Mattermost Thread {$postId} (Chronological Order)"
            );

		echo $output;


            // 3. Add to context
            $this->current_context .= $output;

            return true;

        } catch (\Exception $e) {
            // Re-throw or handle the API exception
            throw $e;
        }
    }

    /**
     * Helper method to format an array of posts into a readable string output block.
     *
     * @param array $posts The array of post objects.
     * @param string $identifier The ID of the channel or thread.
     * @param bool $hasMore Whether there are more results (relevant for channels).
     * @param string $title The title of the output block.
     * @return string The formatted string block.
     */
    private function formatPostsOutput(array $posts, string $identifier, bool $hasMore, string $title): string
    {
        $output = "=== $title ===\n";

        if (empty($posts)) {
            $output .= "No posts found.\n";
        } else {
            // Format each post
            foreach ($posts as $post) {
                $output .= "---
post_id: {$post['post_id']}
user_id: {$post['user_id']}
create_at: {$post['create_at']}

{$post['message']}
---\n";
            }

            // Add pagination footer only if relevant (for channels)
            if ($hasMore) {
                $output .= "=== There are more posts in {$identifier} ===\n";
            } else {
                $output .= "=== There are no more posts in {$identifier} ===\n";
            }
        }
        return $output;
    }
}
