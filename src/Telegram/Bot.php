<?php

declare(strict_types=1);

namespace Telegram;

use Chat\ChatService;
use Gemini\GeminiClient;
use RuntimeException;
use Support\Logger;
use User\UserStateRepository;

class Bot
{
    private const TELEGRAM_API_URL = 'https://api.telegram.org/bot';

    public function __construct(
        private readonly string $botToken,
        private readonly GeminiClient $geminiClient,
        private readonly ChatService $chatService,
        private readonly UserStateRepository $userStateRepository,
        private readonly Logger $logger,
    ) {
    }

    public function handleUpdate(array $update): void
    {
        if (!isset($update['message'])) {
            return;
        }

        $message = $update['message'];
        $telegramUser = $message['from'] ?? null;
        if ($telegramUser === null) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        if ($chatId === null) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        $userId = $this->userStateRepository->ensureUser($telegramUser);

        if ($text === '/start') {
            $this->userStateRepository->setState($userId, 'main_menu');
            $this->sendMessage($chatId, "Привет! Я бот на Gemini. Я умею вести диалог и генерировать картинки.", KeyboardFactory::mainMenu());
            return;
        }

        if ($text === '/reset' || $text === 'Начать заново') {
            $this->chatService->resetChat($userId);
            $this->sendMessage($chatId, 'Контекст диалога сброшен. Начинаем с чистого листа.', KeyboardFactory::mainMenu());
            return;
        }

        if ($text === 'Сгенерировать картинку') {
            $this->userStateRepository->setState($userId, 'image_prompt');
            $this->sendMessage($chatId, 'Опиши картинку, которую нужно сгенерировать');
            return;
        }

        if ($text === 'Чат с Gemini') {
            $this->userStateRepository->setState($userId, 'chat');
            $this->sendMessage($chatId, 'Теперь вы в режиме чата. Пишите сообщение, и я отвечу с учётом контекста.', KeyboardFactory::mainMenu());
            return;
        }

        $state = $this->userStateRepository->getState($userId);
        if ($state === 'image_prompt') {
            $this->handleImagePrompt($chatId, $userId, $text);
            return;
        }

        if ($state === 'chat') {
            $this->handleChat($chatId, $userId, $text);
            return;
        }

        $this->sendMessage($chatId, 'Выберите действие из меню.', KeyboardFactory::mainMenu());
    }

    private function handleImagePrompt(int|string $chatId, int $userId, string $prompt): void
    {
        if ($prompt === '') {
            $this->sendMessage($chatId, 'Пожалуйста, отправьте текстовое описание для изображения.');
            return;
        }

        try {
            $imageData = $this->geminiClient->generateImage($prompt, '16:9');
            $filePath = $this->saveTempImage($imageData);
            $this->sendPhoto($chatId, $filePath, 'Готово!');
            @unlink($filePath);
        } catch (RuntimeException $exception) {
            error_log('Gemini image error: ' . $exception->getMessage());
            $this->sendMessage($chatId, 'Сервис генерации сейчас недоступен, попробуйте позже.');
        }

        $this->userStateRepository->setState($userId, 'main_menu');
        $this->sendMessage($chatId, 'Что дальше?', KeyboardFactory::mainMenu());
    }

    private function handleChat(int|string $chatId, int $userId, string $text): void
    {
        if ($text === '') {
            $this->sendMessage($chatId, 'Отправьте текстовое сообщение для чата.');
            return;
        }

        try {
            $reply = $this->chatService->handleUserMessage($userId, $text);
            $this->sendMessage($chatId, $reply, KeyboardFactory::mainMenu());
        } catch (RuntimeException $exception) {
            error_log('Gemini text error: ' . $exception->getMessage());
            $this->sendMessage($chatId, 'Сервис диалога сейчас недоступен, попробуйте позже.');
        }
    }

    private function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        $this->sendTelegramRequest('sendMessage', $payload);
    }

    private function sendPhoto(int|string $chatId, string $filePath, string $caption = ''): void
    {
        $payload = [
            'chat_id' => $chatId,
            'caption' => $caption,
        ];

        $file = new \CURLFile($filePath, 'image/png', basename($filePath));
        $payload['photo'] = $file;

        $this->sendTelegramRequest('sendPhoto', $payload, true);
    }

    private function sendTelegramRequest(string $method, array $payload, bool $isMultipart = false): void
    {
        $url = self::TELEGRAM_API_URL . $this->botToken . '/' . $method;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize curl');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
        ];

        if (!$isMultipart) {
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
        }

        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Telegram request error: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        $responseBody = $this->truncateString($result, 1000);
        $responseData = json_decode($result, true);
        $ok = is_array($responseData) ? ($responseData['ok'] ?? false) : false;
        $description = is_array($responseData) ? ($responseData['description'] ?? '') : '';

        if ($statusCode >= 400 || !$ok) {
            $this->logger->error('Telegram API error', [
                'method' => $method,
                'status' => $statusCode,
                'ok' => $ok,
                'description' => $description,
                'response' => $responseBody,
            ]);
        }

        $this->logger->info('Telegram API request', [
            'method' => $method,
            'status' => $statusCode,
            'ok' => $ok,
        ]);
    }

    private function truncateString(string $value, int $limit): string
    {
        return mb_strlen($value) <= $limit ? $value : (mb_substr($value, 0, $limit) . '...');
    }

    private function saveTempImage(string $imageData): string
    {
        $filePath = sys_get_temp_dir() . '/gemini_' . uniqid('', true) . '.png';
        file_put_contents($filePath, $imageData);

        return $filePath;
    }
}
