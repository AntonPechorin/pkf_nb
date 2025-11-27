# Telegram Gemini Bot (PHP, без фреймворков)

Телеграм-бот на чистом PHP для диалога с Gemini и генерации изображений через Gemini 2.5 Flash Image.

## Архитектура и структура
- `public/index.php` — входная точка вебхука Telegram, bootstrap автозагрузки, конфигурации и зависимостей.
- `src/autoload.php` — простая автозагрузка классов.
- `src/Database/Connection.php` — создание PDO-подключения к MySQL/MariaDB.
- `src/Telegram/Bot.php` — обработка обновлений, маршрутизация по состояниям, ответы в Telegram.
- `src/Telegram/KeyboardFactory.php` — генерация кнопок главного меню.
- `src/Gemini/GeminiClient.php` — запросы к Gemini API (текст и изображение).
- `src/Chat/ChatService.php` — логика чат-сессий, общение с Gemini и сохранение истории.
- `src/Chat/ChatRepository.php` — работа с таблицами `chat_sessions` и `chat_messages`.
- `src/User/UserStateRepository.php` — регистрация пользователя и хранение состояния (`main_menu`, `chat`, `image_prompt`).
- `config/config.example.php` — пример настроек (реальный `config.php` в .gitignore).
- `sql/schema.sql` — SQL-схема БД.
- `storage/images` — каталог для временных файлов изображений (можно использовать system temp).

## Установка и запуск
1. Клонируйте репозиторий и перейдите в папку проекта.
2. Создайте базу данных MySQL/MariaDB и примените миграции:
   ```bash
   mysql -u <user> -p <db_name> < sql/schema.sql
   ```
3. Скопируйте пример конфига и заполните ключи:
   ```bash
   cp config/config.example.php config/config.php
   ```
   В `config.php` укажите TELEGRAM_BOT_TOKEN, GEMINI_API_KEY и реквизиты БД. Поле `webhook_secret` опционально для защиты вебхука.
   При необходимости измените путь к файлу логов в `log.file` (по умолчанию `storage/logs/bot.log`).
4. Настройте виртуальный хост/сервер (Nginx/Apache) на `public/index.php`. PHP >= 8.1 с расширениями `curl`, `json`, `mbstring`.
5. Убедитесь, что каталог для логов существует: `mkdir -p storage/logs` (создаётся автоматически при наличии прав на запись).
6. Установите вебхук Telegram:
   ```bash
   curl -X POST "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook" \
     -d "url=https://<your-domain>/public/index.php?secret=<WEBHOOK_SECRET>"
   ```
   Если секрет не используется, уберите query-параметр `secret`.

## Логика бота
- `/start` — приветствие и показ главного меню.
- Главное меню: кнопки «Сгенерировать картинку», «Чат с Gemini», «Начать заново».
- «Сгенерировать картинку»: бот просит описание, отправляет в Gemini Image API, отвечает фото и возвращает в главное меню.
- «Чат с Gemini»: сохраняет контекст в БД, каждое сообщение отправляет в Gemini Text API и сохраняет ответы.
- «Начать заново» или `/reset`: завершает активные сессии и очищает состояние.

## Примеры запросов к Gemini API
Текст:
```bash
curl -X POST "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent" \
  -H "x-goog-api-key: <GEMINI_API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{"contents":[{"role":"user","parts":[{"text":"Привет!"}]}]}'
```

Изображение:
```bash
curl -X POST "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent" \
  -H "x-goog-api-key: <GEMINI_API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{"contents":[{"parts":[{"text":"a cat in space"}]}],"generationConfig":{"responseModalities":["Image"],"imageConfig":{"aspectRatio":"16:9"}}}'
```

## Примечания безопасности
- Не коммитьте `config.php` с ключами.
- Обрабатывайте ошибки Gemini: бот покажет пользователю вежливое сообщение.
- Опциональный `webhook_secret` позволяет отклонять чужие запросы.

## Как сделать git push
Ниже краткая последовательность для отправки изменений в удалённый репозиторий (замените `origin` и `work` на свои значения, если ветки отличаются):

```bash
# проверить статус и убедиться, что всё закоммичено
git status

# отправить текущую ветку в удалённый репозиторий
git push origin work

# если ветка новая, можно добавить флаг -u, чтобы запомнить связь отслеживания
# git push -u origin work
```

Если push отклонён из-за новых коммитов на удалённой ветке, выполните `git pull --rebase` и повторите push.
