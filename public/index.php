<?php

declare(strict_types=1);

use Chat\ChatRepository;
use Chat\ChatService;
use Database\Connection;
use Gemini\GeminiClient;
use Telegram\Bot;
use Support\Logger;
use User\UserStateRepository;

require __DIR__ . '/../src/autoload.php';

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo 'Config file missing. Copy config/config.example.php to config/config.php';
    exit;
}

$config = require $configPath;

$logFile = $config['log']['file'] ?? sys_get_temp_dir() . '/telegram_bot.log';
$logger = new Logger($logFile);

if (!empty($config['webhook_secret'])) {
    $secret = $_GET['secret'] ?? '';
    if (!hash_equals($config['webhook_secret'], $secret)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$rawInput = file_get_contents('php://input') ?: '';
$logger->info('Incoming Telegram update', ['body' => $rawInput]);

$update = json_decode($rawInput, true);
if (!is_array($update)) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

$pdo = Connection::make($config['db']);
$geminiClient = new GeminiClient($config['gemini']['api_key'], $logger);
$userStateRepository = new UserStateRepository($pdo);
$chatRepository = new ChatRepository($pdo);
$chatService = new ChatService($chatRepository, $userStateRepository, $geminiClient);
$bot = new Bot($config['telegram']['bot_token'], $geminiClient, $chatService, $userStateRepository, $logger);

$bot->handleUpdate($update);

echo 'ok';
