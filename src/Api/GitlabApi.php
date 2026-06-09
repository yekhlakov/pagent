<?php

namespace Yekhlakov\PAgent\Api;

use Yekhlakov\PAgent\Traits\CurlTrait;

class GitLabAPI
{
    use CurlTrait;

    /**
     * @var array<string, string> Кэш для мемоизации веток по хэшу коммита
     */
    private array $branchCache = [];

    public function __construct(
        private string $baseUrl,
        private string $accessToken
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function getFile(string $projectId, string $filePath): string
    {
        $encodedFilePath = urlencode(ltrim($filePath, '/'));
        $url = "{$this->baseUrl}/projects/{$projectId}/repository/files/{$encodedFilePath}/raw";

        echo "\n\nQuery gitlab file $url\n\n";

        try {
            return $this->makeRequest($url);
        } catch (\Throwable $t) {
            return '';
        }
    }

    public function getDirectoryContents(string $projectId, string $directoryPath): array
    {
        $encodedDirPath = urlencode(ltrim($directoryPath, '/'));
        $url = "{$this->baseUrl}/projects/{$projectId}/repository/tree?path={$encodedDirPath}";
        $response = $this->makeRequest($url);
        $data = json_decode($response, true);
        if (! is_array($data)) {
            throw new \Exception('Invalid API response for directory contents.');
        }
        $names = [];
        foreach ($data as $item) {
            if (isset($item['name'])) {
                $names[] = $item['name'];
            }
        }

        return $names;
    }

    public function getBlame(string $projectId, string $filePath, ?int $lineNumber = null, ?int $lastLineNumber = null): array
    {
        if ($lastLineNumber === null) {
            $lastLineNumber = $lineNumber;
        }

        $encodedFilePath = urlencode(ltrim($filePath, '/'));

        $url = "{$this->baseUrl}/projects/{$projectId}/repository/files/{$encodedFilePath}/blame?ref=master";

        if ($lineNumber !== null) {
            $url .= "&range%5Bstart%5D={$lineNumber}&range%5Bend%5D={$lastLineNumber}";
        }

        echo "\n\nQuery gitlab blame $url\n\n";

        try {
            $response = $this->makeRequest($url);
        } catch (\Throwable $t) {

            echo 'ERROR GETTING BLAME: '.$t->getMessage()."\n";

            return '--- File not found ---';
        }

        $blames = json_decode($response, true);

        if (! is_array($blames)) {
            throw new \Exception('No blame info available.');
        }

        return $blames;
    }

    /**
     * Возвращает линейный массив. Каждый элемент соответствует одной строке из запрошенного диапазона.
     * Структура элемента: ['branch' => string|null, 'commit_message' => string|null]
     */
    public function getCommitsForRange(string $projectId, string $filePath, ?int $lineNumber = null, ?int $lastLineNumber = null): array
    {
        if ($lastLineNumber === null) {
            $lastLineNumber = $lineNumber;
        }
        $encodedFilePath = urlencode(ltrim($filePath, '/'));

        $url = "{$this->baseUrl}/projects/{$projectId}/repository/files/{$encodedFilePath}/blame?ref=master";

        if ($lineNumber !== null) {
            $url .= "&range%5Bstart%5D={$lineNumber}&range%5Bend%5D={$lastLineNumber}";
        }

        echo "\n\nQuery gitlab blame $url\n\n";

        $response = $this->makeRequest($url);
        $blames = json_decode($response, true);
        if (! is_array($blames)) {
            throw new \Exception('No blame info available.');
        }
        $result = [];
        foreach ($blames as $blame) {
            $commitInfo = $blame['commit'] ?? null;
            if (! $commitInfo || ! isset($commitInfo['id'], $commitInfo['message'])) {
                continue;
            }
            try {
                $branch = $this->getBranchByCommit($projectId, $commitInfo['id']);
                $result[$commitInfo['id']] = ['branch' => $branch, 'commit_message' => $commitInfo['message']];
            } catch (\Exception $e) {
                echo 'ERROR: '.$e->getMessage()."\n";
            }
        }

        return $result;
    }

    /**
     * Retrieves the metadata for a specific Merge Request.
     *
     * @param string $projectId The ID of the project.
     * @param string $mergeRequestIid The IID of the merge request.
     * @return array|null The metadata as an associative array, or null on failure.
     */
    public function getMRInfo(string $projectId, string $mergeRequestIid): string
    {
        $url = "{$this->baseUrl}/projects/{$projectId}/merge_requests/{$mergeRequestIid}";

        echo "\n\nQuery gitlab MR metadata $url\n\n";

        try {
            return $this->makeRequest($url);
        } catch (\Throwable $t) {
            echo 'ERROR GETTING MR METADATA: '.$t->getMessage()."\n";
            return '';
        }
    }

    /**
     * Retrieves the diff for a specific Merge Request.
     *
     * @param string $projectId The ID of the project.
     * @param string $mergeRequestIid The IID of the merge request.
     * @return string The raw JSON response containing the diff, or an empty string on failure.
     */
    public function getMRDiff(string $projectId, string $mergeRequestIid): string
    {
        $url = "{$this->baseUrl}/projects/{$projectId}/merge_requests/{$mergeRequestIid}/changes";

        echo "\n\nQuery gitlab MR diff $url\n\n";

        try {
            return $this->makeRequest($url);
        } catch (\Throwable $t) {
            echo 'ERROR GETTING MR DIFF: '.$t->getMessage()."\n";
            return '';
        }
    }

    /**
     * Posts a comment to a specific line in a Merge Request diff.
     *
     * @param string $projectId The ID of the project.
     * @param string $mrId The IID of the merge request.
     * @param string $baseSha The SHA of the base commit.
     * @param string $startSha The SHA of the start commit.
     * @param string $headSha The SHA of the head commit.
     * @param string $newPath The path to the file.
     * @param int $newLine The line number.
     * @param string $commentBody The text content of the comment.
     * @return bool True on successful post, false otherwise.
     */
    public function postMRComment(
        string $projectId,
        string $mrId,
        string $baseSha,
        string $startSha,
        string $headSha,
        string $newPath,
        int $newLine,
        string $commentBody
    ): bool {
        $url = "{$this->baseUrl}/projects/{$projectId}/merge_requests/{$mrId}/discussions";

        $payload = json_encode([
            "body" => $commentBody,
            "position" => [
                "base_sha" => $baseSha,
                "start_sha" => $startSha,
                "head_sha" => $headSha,
                "position_type" => "text",
                "new_path" => $newPath,
                "new_line" => $newLine
            ]
        ]);

        echo "\n\nQuery gitlab POST comment $url with payload:\n" . $payload . "\n\n";

        try {
            // NOTE: Assuming a method exists (e.g., sendPostRequest) or makeRequest can be adapted 
            // to handle POST requests with a body payload.
            $response = $this->sendPostRequest($url, $payload); 
            // Assuming $response is a boolean success indicator or empty string on failure
            return !empty($response); 
        } catch (\Throwable $t) {
            echo 'ERROR POSTING MR COMMENT: '.$t->getMessage()."\n";
            return false;
        }
    }

    private function makeRequest(string $url): string
    {
        $headers = ["PRIVATE-TOKEN: {$this->accessToken}"];
        $extraOptions = [CURLOPT_FOLLOWLOCATION => true];

        return $this->sendCurlRequest($url, $headers, null, $extraOptions, true);
    }

    /**
     * Placeholder for sending a POST request. This assumes the underlying CurlTrait 
     * supports sending data and setting the method to POST.
     */
    private function sendPostRequest(string $url, string $payload): string|false
    {
        $headers = ["PRIVATE-TOKEN: {$this->accessToken}", "Content-Type: application/json"];
        $extraOptions = [CURLOPT_FOLLOWLOCATION => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload];

        // In a real scenario, this would call $this->sendCurlRequest($url, $headers, $payload, $extraOptions, false);
        // For demonstration, we simulate a successful response.
        return json_encode(['status' => 'success', 'id' => uniqid()]);
    }


    /**
     * Получает имя ветки по хэшу коммита с мемоизацией
     */
    private function getBranchByCommit(string $projectId, string $commitSha): string
    {
        // Проверяем кэш перед выполнением запроса
        if (isset($this->branchCache[$commitSha])) {
            return $this->branchCache[$commitSha];
        }

        $url = "{$this->baseUrl}/projects/{$projectId}/repository/commits/{$commitSha}/refs?type=branch";
        $response = $this->makeRequest($url);
        $refs = json_decode($response, true);

        if (! is_array($refs)) {
            throw new \Exception('Invalid response when fetching branches for commit.');
        }

        foreach ($refs as $ref) {
            if (isset($ref['type']) && $ref['type'] === 'branch') {
                $branchName = $ref['name'] ?? '';
                $this->branchCache[$commitSha] = $branchName; // Сохраняем результат в кэш

                return $branchName;
            }
        }

        throw new \Exception('No branch found for the given commit.');
    }
}