<?php

namespace Yekhlakov\PAgent\Traits;

trait CurlTrait
{
    /**
     * Выполняет HTTP-запрос с использованием cURL.
     */
    protected function sendCurlRequest(
        string $url,
        array $headers = [],
        $payload = null,
        array $extraOptions = [],
        bool $throwOnError = false
    ): string {
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
            $options[CURLOPT_POSTFIELDS] = is_array($payload) ? json_encode($payload) : $payload;
        }

        // array_replace сохраняет числовые ключи (CURLOPT_* константы),
        // в отличие от array_merge, который сбрасывает их в 0,1,2...
        $finalOptions = array_replace($options, $extraOptions);
        curl_setopt_array($ch, $finalOptions);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || ! empty($error)) {
            throw new \Exception("cURL error: {$error}");
        }

        if ($throwOnError && $httpCode >= 400) {
            throw new \Exception("HTTP error: {$httpCode}, response: {$response}");
        }

        return $response;
    }
}
