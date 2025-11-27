<?php

declare(strict_types=1);

namespace Support;

class Logger
{
    public function __construct(private readonly string $filePath)
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->writeLog('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->writeLog('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function writeLog(string $level, string $message, array $context): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf('[%s] [%s] %s', $timestamp, $level, $message);

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        file_put_contents($this->filePath, $line . PHP_EOL, FILE_APPEND);
    }
}
