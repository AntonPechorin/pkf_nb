<?php

declare(strict_types=1);

namespace Telegram;

class KeyboardFactory
{
    public static function mainMenu(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => 'Сгенерировать картинку'],
                    ['text' => 'Чат с Gemini'],
                ],
                [
                    ['text' => 'Начать заново'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }
}
