<?php

declare(strict_types=1);

namespace User;

use PDO;

class UserStateRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureUser(array $telegramUser): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE telegram_user_id = :telegramId LIMIT 1');
        $stmt->execute(['telegramId' => $telegramUser['id']]);
        $userId = $stmt->fetchColumn();

        if ($userId !== false) {
            $this->updateUserInfo((int) $userId, $telegramUser);
            return (int) $userId;
        }

        $stmt = $this->pdo->prepare('INSERT INTO users (telegram_user_id, first_name, last_name, username, created_at, updated_at) VALUES (:telegramId, :firstName, :lastName, :username, NOW(), NOW())');
        $stmt->execute([
            'telegramId' => $telegramUser['id'],
            'firstName' => $telegramUser['first_name'] ?? null,
            'lastName' => $telegramUser['last_name'] ?? null,
            'username' => $telegramUser['username'] ?? null,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $this->setState($userId, 'main_menu');

        return $userId;
    }

    public function setState(int $userId, string $state): void
    {
        $stmt = $this->pdo->prepare('REPLACE INTO user_states (user_id, state, updated_at) VALUES (:userId, :state, NOW())');
        $stmt->execute([
            'userId' => $userId,
            'state' => $state,
        ]);
    }

    public function getState(int $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT state FROM user_states WHERE user_id = :userId LIMIT 1');
        $stmt->execute(['userId' => $userId]);
        $state = $stmt->fetchColumn();

        return $state !== false ? (string) $state : 'main_menu';
    }

    private function updateUserInfo(int $userId, array $telegramUser): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET first_name = :firstName, last_name = :lastName, username = :username, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $userId,
            'firstName' => $telegramUser['first_name'] ?? null,
            'lastName' => $telegramUser['last_name'] ?? null,
            'username' => $telegramUser['username'] ?? null,
        ]);
    }
}
