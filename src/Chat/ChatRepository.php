<?php

declare(strict_types=1);

namespace Chat;

use PDO;

class ChatRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createSession(int $userId): int
    {
        $this->pdo->prepare('UPDATE chat_sessions SET is_active = 0 WHERE user_id = :userId')->execute(['userId' => $userId]);

        $stmt = $this->pdo->prepare('INSERT INTO chat_sessions (user_id, is_active, created_at, updated_at) VALUES (:userId, 1, NOW(), NOW())');
        $stmt->execute(['userId' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getActiveSessionId(int $userId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM chat_sessions WHERE user_id = :userId AND is_active = 1 ORDER BY id DESC LIMIT 1');
        $stmt->execute(['userId' => $userId]);
        $session = $stmt->fetchColumn();

        return $session !== false ? (int) $session : null;
    }

    public function closeSessions(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE chat_sessions SET is_active = 0, updated_at = NOW() WHERE user_id = :userId');
        $stmt->execute(['userId' => $userId]);
    }

    public function addMessage(int $sessionId, string $role, string $content): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO chat_messages (session_id, role, content, created_at) VALUES (:sessionId, :role, :content, NOW())');
        $stmt->execute([
            'sessionId' => $sessionId,
            'role' => $role,
            'content' => $content,
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getMessages(int $sessionId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('SELECT role, content FROM chat_messages WHERE session_id = :sessionId ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue('sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll();

        return array_reverse($messages);
    }
}
