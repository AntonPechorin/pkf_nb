<?php

declare(strict_types=1);

return [
    'telegram' => [
        'bot_token' => 'YOUR_TELEGRAM_BOT_TOKEN',
    ],
    'gemini' => [
        'api_key' => 'YOUR_GEMINI_API_KEY',
    ],
    'db' => [
        'host' => 'localhost',
        'database' => 'telegram_bot',
        'user' => 'db_user',
        'password' => 'db_password',
    ],
    'webhook_secret' => 'optional-secret',
];
