<?php

declare(strict_types=1);

namespace Gemini;

use RuntimeException;
use Support\Logger;

class GeminiClient
{
    private const TEXT_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private const IMAGE_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent';

    public function __construct(
        private readonly string $apiKey,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $contents
     */
    public function generateText(array $contents): string
    {
        $payload = [
            'contents' => $contents,
        ];

        $this->logger?->info('Gemini text request queued', ['endpoint' => self::TEXT_ENDPOINT]);
        $response = $this->postJson(self::TEXT_ENDPOINT, $payload);

        if (!isset($response['candidates'][0]['content']['parts'])) {
            throw new RuntimeException('Empty response from Gemini text API');
        }

        $parts = $response['candidates'][0]['content']['parts'];
        $texts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $texts[] = $part['text'];
            }
        }

        $result = trim(implode("\n", $texts));
        if ($result === '') {
            throw new RuntimeException('Gemini text response is empty');
        }

        return $result;
    }

    public function generateImage(string $prompt, string $aspectRatio = '16:9'): string
    {
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => [
                'responseModalities' => ['Image'],
                'imageConfig' => [
                    'aspectRatio' => $aspectRatio,
                ],
            ],
        ];

        $this->logger?->info('Gemini image request queued', ['endpoint' => self::IMAGE_ENDPOINT]);
        $response = $this->postJson(self::IMAGE_ENDPOINT, $payload);

        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['inlineData']['data']) && is_string($part['inlineData']['data'])) {
                $imageData = base64_decode($part['inlineData']['data'], true);
                if ($imageData === false) {
                    break;
                }

                return $imageData;
            }
        }

        throw new RuntimeException('Image data not found in Gemini response');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize curl');
        }

        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Curl error: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        $this->logger?->info('Gemini response received', [
            'endpoint' => $url,
            'status' => $statusCode,
        ]);

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from Gemini');
        }

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? 'Gemini API request failed';
            $this->logger?->error('Gemini API error', [
                'endpoint' => $url,
                'status' => $statusCode,
                'message' => $message,
            ]);
            throw new RuntimeException($message);
        }

        return $decoded;
    }
}
