<?php

namespace Yekhlakov\PAgent\Api;

/**
 * A client for interacting with the Mattermost API.
 *
 * Uses constructor parameter promotion and reuses cURL logic internally.
 */
class MattermostApi
{
    /**
     * @param  string  $baseUrl  The base URL of the Mattermost instance (e.g., https://mm.example.com).
     * @param  string  $accessToken  Your Bearer token.
     */
    public function __construct(
        private string $baseUrl,
        private string $accessToken
    ) {}

    /**
     * Internal helper function derived from CurlTrait to execute HTTP requests.
     * This method handles the cURL setup and execution without explicitly calling curl_close().
     *
     * @param  string  $endpoint  The specific API endpoint path (e.g., /posts).
     * @param  array  $headers  Custom headers (including Authorization).
     * @param  array|string|null  $payload  The request body data.
     * @param  array  $extraOptions  Additional cURL options.
     * @param  bool  $isPost  Whether this is a POST request.
     * @return string The raw response body.
     *
     * @throws \Exception If a cURL or HTTP error occurs.
     */
    protected function sendCurlRequest(
        string $endpoint,
        array $headers = [],
        array|string|null $payload = null,
        array $extraOptions = [],
        bool $isPost = false
    ): string {
        $url = $this->baseUrl.$endpoint;

        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POST] = true;
            // Ensure payload is JSON encoded if it's an array
            $options[CURLOPT_POSTFIELDS] = is_array($payload) ? json_encode($payload) : $payload;
        }

        // Apply extra options and replace defaults
        $finalOptions = array_replace($options, $extraOptions);
        curl_setopt_array($ch, $finalOptions);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || ! empty($error)) {
            throw new \Exception("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \Exception("HTTP error: {$httpCode}, response: {$response}");
        }

        return $response;
    }

    /**
     * Performs a Mattermost API POST request and returns the raw response body.
     *
     * @param  string  $channelId  The ID of the channel to post to.
     * @param  string  $message  The content of the message.
     * @return string The raw response body from the server.
     *
     * @throws \Exception On API failure.
     */
    public function postToMattermost(string $channelId, string $message): string
    {
        // 1. Construct the JSON payload
        $payload = [
            'channel_id' => $channelId,
            'message' => $message,
        ];

        // 2. Define headers
        $headers = [
            'Authorization: Bearer '.$this->accessToken,
            'Content-Type: application/json',
        ];

        // 3. Execute the request
        // Endpoint: /posts
        return $this->sendCurlRequest(
            '/posts',
            $headers,
            $payload,
            [],
            true // isPost = true
        );
    }

    /**
     * Retrieves unread messages and channel memberships for all teams,
     * utilizing sendCurlRequest for all API calls.
     *
     * @return string The raw response body from the initial request (list of teams).
     *
     * @throws \Exception On API failure.
     */
    public function getUnreadMessages(): string
    {
        // Define common headers for all requests
        $headers = [
            'Authorization: Bearer '.$this->accessToken,
            'Content-Type: application/json',
        ];

        // 1. Get the initial list of teams using sendCurlRequest
        $teamListResponse = $this->sendCurlRequest(
            '/users/me/teams/unread',
            $headers,
            null,
            [],
            false
        );

        $teams = json_decode($teamListResponse, true);

        if (! is_array($teams)) {
            return $teamListResponse; // Return raw response if decoding fails
        }

        // Process each team
        foreach ($teams as $team) {
            $teamId = $team['team_id'];

            // A. Get Unread Messages for the specific team
            $unreadMessagesEndpoint = "/users/me/teams/{$teamId}/unread";
            try {
                $response = $this->sendCurlRequest(
                    $unreadMessagesEndpoint,
                    $headers,
                    null,
                    [],
                    false
                );
                echo "/teams/{$teamId}/unread ==> ".$response."\n\n";
            } catch (\Exception $e) {
                echo "Error fetching unread messages for team {$teamId}: ".$e->getMessage()."\n";
            }

            // B. Get Memberships for the specific team
            $membershipsEndpoint = "/users/me/teams/{$teamId}/channels/members";
            try {
                $response = $this->sendCurlRequest(
                    $membershipsEndpoint,
                    $headers,
                    null,
                    [],
                    false
                );

                $memberships = json_decode($response, true);

                if (is_array($memberships)) {
                    echo count($memberships)." memberships\n";

                    // Filter using PHP 7.4+ arrow function syntax
                    $filteredMemberships = array_values(array_filter($memberships, fn ($membership) => $membership['last_viewed_at'] > 0 && $membership['last_viewed_at'] < $membership['last_update_at']
                    ));

                    echo count($filteredMemberships)." unread channels\n";

                    if (! empty($filteredMemberships)) {
                        echo json_encode($filteredMemberships[0])."\n\n";
                    }
                }
            } catch (\Exception $e) {
                echo "Error fetching memberships for team {$teamId}: ".$e->getMessage()."\n";
            }
        }

        return $teamListResponse;
    }

    /**
     * Searches messages within a specific team.
     *
     * @param  string  $teamId  The ID of the team to search within.
     * @param  string  $query  The search terms.
     * @return string The raw response body from the server.
     *
     * @throws \Exception On API failure.
     */
    public function searchMessages(string $teamId, string $query): string
    {
        // 1. Construct the JSON payload
        $payload = [
            'terms' => $query,
            'is_or_search' => false,
        ];

        // 2. Define headers
        $headers = [
            'Authorization: Bearer '.$this->accessToken,
            'Content-Type: application/json',
        ];

        // 3. Execute the request
        // Endpoint: /teams/{$teamId}/posts/search
        $endpoint = "/teams/{$teamId}/posts/search";

        return $this->sendCurlRequest(
            $endpoint,
            $headers,
            $payload,
            [],
            true // isPost = true
        );
    }

    /**
     * Retrieves a paginated list of posts from a specific channel, reordering them
     * according to the API's 'order' array, and returning them in reverse chronological order.
     * Each post is filtered to include only specific fields.
     *
     * @param  string  $channelId  The ID of the channel.
     * @param  int  $page  The page number to fetch (default 0).
     * @param  int  $perPage  The number of posts per page (default 60).
     * @param  int|null  $since  Unix timestamp in milliseconds to retrieve posts after.
     * @param  string|null  $before  Post ID to retrieve posts before.
     * @param  string|null  $after  Post ID to retrieve posts after.
     * @return array ["has_more" => bool, "posts" => An array of filtered post objects keyed by post ID, in reverse chronological order].
     *
     * @throws \Exception On API failure.
     */
    public function getChannelPosts(
        string $channelId,
        int $page = 0,
        int $perPage = 100,
        ?int $since = null,
        ?string $before = null,
        ?string $after = null,
        bool $filterPosts = true,
    ): array {
        $headers = [
            'Authorization: Bearer '.$this->accessToken,
            'Content-Type: application/json',
        ];

        // Build the endpoint and query parameters
        $endpoint = "/channels/{$channelId}/posts";
        $queryParams = [];

        if ($page > 0) {
            $queryParams['page'] = $page;
        }
        if ($perPage > 0) {
            $queryParams['per_page'] = $perPage;
        }
        if ($since !== null) {
            $queryParams['since'] = $since;
        }
        if ($before !== null) {
            $queryParams['before'] = $before;
        }
        if ($after !== null) {
            $queryParams['after'] = $after;
        }

        $fullEndpoint = $endpoint.(empty($queryParams) ? '' : '?'.http_build_query($queryParams));

        echo "Calling MM API $fullEndpoint\n";

        // Execute the GET request
        $responseBody = $this->sendCurlRequest(
            $fullEndpoint,
            $headers,
            null, // No payload for GET request
            [],
            false // Not a POST request
        );

        // Decode the response
        $data = json_decode($responseBody, true);

        if (! is_array($data) || ! isset($data['posts']) || ! is_array($data['posts']) || ! isset($data['order']) || ! is_array($data['order'])) {
            // Return empty array if the response structure is unexpected
            return [];
        }

        $posts = $data['posts'];
        $order = $data['order'];
        $orderedPosts = [];

        // 1. Reorder posts based on the 'order' array and filter fields
        foreach ($order as $postId) {
            if (empty($posts[$postId])) {
                continue;
            }

            $post = $posts[$postId];

            if (! empty($post['root_id']) || ! empty($post['type'])) {
                // Skip non-root posts
                // Skip system posts
                continue;
            }

            // 2. Recode/Filter the post object
            if ($filterPosts) {
                $filteredPost = [
		    'post_id' => $postId,
                    'create_at' => $post['create_at'] ?? null,
                    'update_at' => $post['update_at'] ?? null,
                    'user_id' => $post['user_id'] ?? null,
                    'message' => $post['message'] ?? null,
                ];
            } else {
                $filteredPost = $post;
            }

            $orderedPosts[$postId] = $filteredPost;
        }

        // 3. Return the result in reverse order
        return ['has_more' => ! empty($data['order']), 'posts' => array_reverse($orderedPosts)];
    }

    /**
     * Retrieves a specific post by its ID.
     *
     * @param  string  $postId  The ID of the post to retrieve.
     * @return array The post information as a PHP array.
     *
     * @throws \Exception On API failure or decoding error.
     */
    public function getPost(string $postId): array
    {
        // 1. Define headers
        $headers = [
            'Authorization: Bearer '.$this->accessToken,
            'Content-Type: application/json',
        ];

        // 2. Construct the endpoint
        $endpoint = "/posts/{$postId}";

        // 3. Execute the GET request
        $responseBody = $this->sendCurlRequest(
            $endpoint,
            $headers,
            null, // No payload for GET request
            [],
            false // Not a POST request
        );

        // 4. Decode and return
        $postInfo = json_decode($responseBody, true);

        if (! is_array($postInfo)) {
            throw new \Exception("Failed to decode post information for ID {$postId}.");
        }

        return $postInfo;
    }

    /**
     * Retrieves detailed information for a list of users by their IDs.
     *
     * @param  string[]  $userIds  An array of user IDs.
     * @return array An array containing user info structs (decoded JSON response).
     *
     * @throws \Exception On API failure or decoding error.
     */
    public function getUserList(array $userIds): array
    {
        // 1. Define headers
        $headers = [
            'Authorization: Bearer '.$this->accessToken,
            'Content-Type: application/json',
        ];

        // 2. Execute the POST request
        // Endpoint: /users/ids
        $responseBody = $this->sendCurlRequest(
            '/users/ids',
            $headers,
            $userIds, // The payload is the array of user IDs
            [],
            true // isPost = true
        );

        // 3. Decode and return
        $userList = json_decode($responseBody, true);

        if (! is_array($userList)) {
            throw new \Exception('Failed to decode user list information.');
        }

        return $userList;
    }
}
