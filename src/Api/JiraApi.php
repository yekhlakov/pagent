<?php

namespace Yekhlakov\PAgent\Api;

use Yekhlakov\PAgent\Traits\CurlTrait;

class JiraApi
{
    use CurlTrait;

    public function __construct(
        private string $apiUrl,
        private string $apiToken,
        private array $customFieldMap = [])
    {
        $this->apiUrl = rtrim($this->apiUrl, '/');
    }

    public function getIssue($issueKey)
    {
        $url = $this->apiUrl.'/rest/api/2/issue/'.trim($issueKey);

        echo "Querying jira $url \n";

        $headers = [
            'Authorization: Bearer '.$this->apiToken,
        ];

        // GET-запрос, payload = null
        $response = $this->sendCurlRequest($url, $headers);
        $issueData = json_decode($response, true);

        $return = [
            'issue_key' => $issueKey,
            'title' => $issueData['fields']['summary'] ?? '',
            'description' => $issueData['fields']['description'] ?? '',
            'comments' => array_map(fn ($c) => $c['body'], $issueData['fields']['comment']['comments'] ?? []),
        ];

        foreach ($this->customFieldMap as $dstKey => $srcKey) {
            $return[$dstKey] = $issueData['fields'][$srcKey] ?? '';
        }

        return $return;
    }
}
