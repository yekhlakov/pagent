<?php

namespace Yekhlakov\PAgent;

use Yekhlakov\PAgent\Api\BitbucketApi;
use Yekhlakov\PAgent\Api\GitlabApi;
use Yekhlakov\PAgent\Api\JiraApi;
use Yekhlakov\PAgent\Api\LlmApi;
use Yekhlakov\PAgent\Api\MattermostApi;
use Yekhlakov\PAgent\Traits\ToolCallRouterTrait;
use Yekhlakov\PAgent\Traits\Tools\Bitbucket;
use Yekhlakov\PAgent\Traits\Tools\Cache;
use Yekhlakov\PAgent\Traits\Tools\Filesystem;
use Yekhlakov\PAgent\Traits\Tools\Finish;
use Yekhlakov\PAgent\Traits\Tools\Gitlab;
use Yekhlakov\PAgent\Traits\Tools\Jira;
use Yekhlakov\PAgent\Traits\Tools\Mattermost;
use Yekhlakov\PAgent\Traits\Tools\Vcs;

class Agent
{
    use Bitbucket;
    use Cache;
    use Filesystem;
    use Finish;
    use Gitlab;
    use Jira;
    use Mattermost;
    use ToolCallRouterTrait;
    use Vcs;

    private string $id;

    private string $system_prompt = 'Hello';

    private array $memory = [];

    private string $user_task = '';

    private string $current_context = '';

    private array $context = [];

    private ?string $result = '';

    public function getResult()
    {
        return $this->result;
    }

    private BitbucketApi $bitbucketApi;

    private GitlabApi $gitlabApi;

    private string $projectId;

    private LlmApi $llmApi;

    private JiraApi $jiraApi;

    private MattermostApi $mmApi;

    public readonly \DateTimeZone $dateTimeZone;

    private array $config;

    public function getDateTimeString(): string
    {
        return (new \DateTime('now', $this->dateTimeZone))->format('Y-m-d_H-i-s.u');
    }

    public $outputDirectory;

    public function init_dir()
    {
        $this->id = sprintf('%08x', random_int(0x80000000, 0xFFFFFFFF));

        $this->outputDirectory = 'output/'.$this->getDateTimeString().'-'.$this->id.'-'.$this->llm.'/';

        mkdir($this->outputDirectory, 0777, true);
    }

    /**
     * @param  string  $configFile  Path to the configuration JSON file.
     *
     * @throws \Exception If config file cannot be read or decoded.
     */
    public function __construct(
        public readonly string $configFile = 'config/config.json',
        public readonly string $llm = 'local',
    ) {

        $this->config = json_decode(file_get_contents($configFile), true);

        $this->dateTimeZone = new \DateTimeZone($this->config['agent']['timezone'] ?? 'UTC');

        $this->init_dir();

        $systemPromptFile = $this->config['agent']['system-prompt-file'] ?? 'config/system-prompt.txt';
        if (is_file($systemPromptFile)) {
            $this->system_prompt = file_get_contents($systemPromptFile);
        } else {
            $this->system_prompt = $this->config['agent']['system-prompt']
                ?? 'You are an AI agent acting as an experienced programmer / system analyst / business analyst / technical writer.
If the information provided to you by the user is insufficient to perform your task, you MUST use tools to retrieve additional information.
';
        }

        // 1. Initialize APIs

        $this->bitbucketApi = new BitbucketApi(
            $this->config['bitbucket']['baseUrl'],
            $this->config['bitbucket']['accessToken']
        );

        $this->gitlabApi = new GitlabApi(
            $this->config['gitlab']['baseUrl'],
            $this->config['gitlab']['accessToken']
        );

        echo 'Agent is using LLM: '.$this->llm.' ['.$this->config['llm'][$llm]['model']."]\n";

        $this->llmApi = new LlmApi(
            $this->config['llm'][$this->llm]['baseUrl'],
            $this->config['llm'][$this->llm]['authToken'],
            $this->config['llm'][$this->llm]['model'],
        );

        $this->jiraApi = new JiraApi(
            $this->config['jira']['apiUrl'],
            $this->config['jira']['apiToken'],
            $this->config['jira']['customFieldMap'] ?? []
        );

        $this->mmApi = new MattermostApi(
            $this->config['mattermost']['apiUrl'],
            $this->config['mattermost']['apiToken'],
        );

        // 2. Initialize Query Routing

        $this->projectId = $this->config['gitlab']['project_id'];

        $this->initRouter();

        echo "--------------- Agent {$this->id} Initialized ---------------\n";
    }

    public function loadFileCache($name)
    {
        if (is_file($name)) {
            $this->saved_files = json_decode(file_get_contents($name), true) ?? [];

            echo '---------------- Recovered file cache with '.count($this->saved_files)." file(s) ---------------\n";
        }
    }

    public function storeFileCache($name)
    {
        // Store file cache index in order to be able to continue later
        file_put_contents($name, json_encode($this->saved_files, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function duplicate(): self
    {
        $copy = clone $this;

        $copy->id = sprintf('%08x', random_int(0x80000000, 0xFFFFFFFF));
        $copy->result = '';
        $copy->current_context = '';

        return $copy;
    }

    public function compactJson($unpacked): string
    {
        foreach ($unpacked as $k => $v) {
            if (\is_string($v) && \strlen($v) > 30) {
                $v = '(The content was truncated)';
            }
        }

        return json_encode($unpacked, JSON_UNESCAPED_UNICODE);
    }

    public function add_context_item(string $role, string $content)
    {
        $this->context[] = ['role' => $role, 'content' => $content];
    }

    public function add_context_item_ext(array $item)
    {
        $this->context[] = $item;
    }

    /**
     * Executes the agent's logic loop based on the initial query.
     *
     * @param  string  $query  The starting query from the user.
     * @return string The final response from the agent.
     */
    public function handle(string $query)
    {
        $this->user_task = $query;

        // Reset the context
        $this->current_context = '';
        $this->context = [
            ['role' => 'system', 'content' => $this->system_prompt],
            ['role' => 'user', 'content' => $this->user_task],
        ];

        $queryCount = 0;

        echo "--------------- Agent {$this->id} Started ---------------\n";

        $localToolSet = $this->getToolSet();

        $localTools = array_map(fn ($x) => $x['function']['name'], $localToolSet);

        echo '--------------- '.count($localTools)." tools are enabled for session ---------------\n";
        echo '['.implode(', ', $localTools)."]\n\n";

        // The loop continues as long as the command router returns a non-empty result.
        while (true) {

            echo "--------------- Agent {$this->id} is issuing query to LLM ---------------\n";

            // 1. Call LLM API
            $result = $this->llmApi->send($this->context, $localToolSet);

            $queryCount++;

            echo "--------------- Agent {$this->id} is processing LLM response ---------------\n";

            // 2. Add the message to context
            $this->add_context_item_ext($result);

            // 3. Process the response
            $routerResult = $this->parseLlmResponse($result);

            // 4. Check exit condition
            if (empty($routerResult)) {
                echo "--------------- Agent {$this->id} Finished Task (LLM query count = $queryCount) ---------------\n";

                return '';
            }
        }

        echo "--------------- Agent {$this->id} Terminated Abnormally ---------------\n";

        return '';
    }
}
