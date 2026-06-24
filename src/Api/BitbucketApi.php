<?php

namespace Yekhlakov\PAgent\Api;

/**
 * BitbucketApi class provides methods to interact with Bitbucket API.
 */
class BitbucketApi
{
    private string $baseUrl;

    private string $accessToken;

    /**
     * BitbucketApi constructor.
     *
     * @param  string  $baseUrl  The base URL of the Bitbucket API (e.g., https://bitbucket.example.com/rest/api/1.0/)
     * @param  string  $accessToken  The access token for authentication.
     */
    public function __construct(string $baseUrl, string $accessToken)
    {
        // Ensure no trailing slash on the base URL for consistent path building
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->accessToken = $accessToken;
    }

    /**
     * Retrieves a file from a Bitbucket repository.
     *
     * The projectId parameter is expected to be in a format like:
     * projectKey/repos/repositorySlug
     *
     * @param  string  $projectId  The identifier for the project and repository.
     * @param  string  $file  The path to the file to retrieve.
     * @return string|null The content of the file, or null on failure.
     */
    public function getFile(string $projectId, string $file): ?string
    {
        // 1. URL decode the projectId
        $decodedProjectId = urldecode($projectId);

        // 2. Explode by "/"
        $parts = explode('/', $decodedProjectId);

        // 3. Validate structure: must have at least 2 parts, and the second must be 'repos'
        if (count($parts) < 3 || $parts[0] !== 'projects' || $parts[2] !== 'repos') {
            // Invalid projectId format
            return null;
        }

        // 4. Extract projectKey (1st part)
        $projectKey = $parts[1];

        // 5. Get remaining parts (excluding 'projects', projectKey and 'repos')
        $remainingParts = array_slice($parts, 3);

        // 6. Implode back with '/' and urlencode to form repositorySlug
        $repositorySlug = urlencode(implode('/', $remainingParts));

        // Construct the full API endpoint URL
        // Endpoint: {baseUrl}/projects/{projectKey}/repos/{repositorySlug}/raw/{pathToFile}
        $url = $this->baseUrl."/projects/{$projectKey}/repos/{$repositorySlug}/raw/{$file}";

        // Initialize cURL
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Set Authorization header
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$this->accessToken,
        ]);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Return content if the request was successful (2xx status code)
        if ($httpCode >= 200 && $httpCode < 300) {
            return $response;
        }

        // Return null if the request failed
        return null;
    }
}
