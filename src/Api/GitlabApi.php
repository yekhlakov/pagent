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

    private function makeRequest(string $url): string
    {
        $headers = ["PRIVATE-TOKEN: {$this->accessToken}"];
        $extraOptions = [CURLOPT_FOLLOWLOCATION => true];

        return $this->sendCurlRequest($url, $headers, null, $extraOptions, true);
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
