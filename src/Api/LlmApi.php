<?php

namespace Yekhlakov\PAgent\Api;

use Yekhlakov\PAgent\Traits\CurlTrait;

class LlmApi
{
    use CurlTrait;

    public function __construct(
        private string $baseUrl,
        private string $authToken,
        private string $model
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function reset(): void
    {
        $this->chatId = null;
        $this->chatMessages = [];
    }

    public $timings = [];

    public $usage = [];

	public function getTokens(string $text): array
	{
	        $url = $this->baseUrl.'/tokenize';

		$payload = ["content" => $text];

	        $headers = [
        	    'Authorization: Bearer '.$this->authToken,
	            'Content-Type: application/json',
        	];

	        $response = $this->sendCurlRequest($url, $headers, $payload);

        	$data = json_decode($response, true);

	        echo "\n\nLLM RESPONSE: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";

		return $data;
	}
	

	public function getEmbeddings(string $text): array
	{
	        $url = $this->baseUrl.'/embeddings';

		$payload = ["input" => $text];

	        $headers = [
        	    'Authorization: Bearer '.$this->authToken,
	            'Content-Type: application/json',
        	];

	        $response = $this->sendCurlRequest($url, $headers, $payload);

        	$data = json_decode($response, true);

	        echo "\n\nLLM RESPONSE: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";

		return $data;
	}


    /**
     * Отправляет запрос в LLM.
     *
     * @param  string  $query  Основной текст запроса пользователя.
     * @return string Ответ LLM.
     */
    public function send(array $context, array $tools = []): array
    {
        $url = $this->baseUrl.'/chat/completions';
        // // echo "URL $url\n";

        $messages = [];
        foreach ($context as $key => $contextElement) {
            $messages[] = ['role' => $key ? 'user' : 'system', 'content' => $contextElement];
        }

        // 2. Формирование полезной нагрузки (payload)
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'seed' => 666,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $headers = [
            'Authorization: Bearer '.$this->authToken,
            'Content-Type: application/json',
        ];

        // echo "\n\nLLM REQUEST: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";

        // 3. Выполнение запроса
        $response = $this->sendCurlRequest($url, $headers, $payload);
        $data = json_decode($response, true);

        echo "\n\nLLM RESPONSE: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";

        // 4. Обновление состояния и истории

        $this->timings = $data['timings'] ?? [];
        $this->usage = $data['usage'] ?? [];

        return $data['choices'][0]['message'] ?? null;
    }
}
