<?php

declare(strict_types=1);

namespace Chat;

use Gemini\GeminiClient;
use RuntimeException;
use User\UserStateRepository;

class ChatService
{
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly UserStateRepository $userStateRepository,
        private readonly GeminiClient $geminiClient,
    ) {
    }

    public function resetChat(int $userId): void
    {
        $this->chatRepository->closeSessions($userId);
        $this->userStateRepository->setState($userId, 'main_menu');
    }

    public function handleUserMessage(int $userId, string $text): string
    {
        $sessionId = $this->chatRepository->getActiveSessionId($userId);
        if ($sessionId === null) {
            $sessionId = $this->chatRepository->createSession($userId);
        }

        $this->chatRepository->addMessage($sessionId, 'user', $text);

        $history = $this->chatRepository->getMessages($sessionId, 12);
        $contents = $this->buildContents($history);
        $reply = $this->geminiClient->generateText($contents);

        if (trim($reply) === '') {
            throw new \RuntimeException('Gemini returned an empty reply');
        }

        $this->chatRepository->addMessage($sessionId, 'model', $reply);

        return $reply;
    }

    /**
     * @param array<int, array<string, string>> $history
     * @return array<int, array<string, mixed>>
     */
    private function buildContents(array $history): array
    {
        $contents = [[
            'role' => 'system',
            'parts' => [[
                'text' => 'Ты дружелюбный ассистент в Telegram-боте. Помогаешь пользователю, отвечаешь коротко и по делу.',
            ]],
        ]];

        foreach ($history as $message) {
            $contents[] = [
                'role' => $message['role'] === 'model' ? 'model' : 'user',
                'parts' => [[
                    'text' => $message['content'],
                ]],
            ];
        }

        return $contents;
    }
}
