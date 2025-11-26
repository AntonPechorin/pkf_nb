<?php

declare(strict_types=1);

namespace Gemini;

use RuntimeException;

class GeminiClient
{
    private const TEXT_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private const IMAGE_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent';

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $contents
     */
    public function generateText(array $contents): string
    {
        $payload = [
            'contents' => $contents,
        ];

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

        return trim(implode("\n", $texts));
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

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from Gemini');
        }

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? 'Gemini API request failed';
            throw new RuntimeException($message);
        }

        return $decoded;
    }
}
