<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class PiiMaskingProcessor implements ProcessorInterface
{
    /**
     * Fields to mask in log records
     */
    protected array $sensitiveKeys = [
        'email', 'phone', 'first_name', 'last_name', 'middle_name',
        'inn', 'kpp', 'ogrn', 'bank_account', 'bank_correspondent_account',
        'legal_address', 'postal_address', 'director_name',
        'password', 'token', 'secret', 'key', 'api_key', 'client_secret',
        'woo_consumer_secret', 'smtp_password', 'telegram_bot_token'
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $extra = $record->extra;

        if (!empty($context)) {
            $record = $record->with(context: $this->maskSensitiveData($context));
        }

        if (!empty($extra)) {
            $record = $record->with(extra: $this->maskSensitiveData($extra));
        }

        // Also attempt to mask sensitive info in the message itself if it contains JSON-like strings
        $message = $record->message;
        if (str_contains($message, '{') && str_contains($message, '}')) {
            // Very basic heuristic for JSON masking in messages
            foreach ($this->sensitiveKeys as $key) {
                $pattern = '/"'.preg_quote($key, '/').'"\s*:\s*"([^"]+)"/i';
                $message = preg_replace_callback($pattern, function($matches) {
                    return str_replace($matches[1], $this->maskValue($matches[1]), $matches[0]);
                }, $message);
            }
            $record = $record->with(message: $message);
        }

        return $record;
    }

    protected function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif (is_string($value) && $this->isSensitiveKey($key)) {
                $data[$key] = $this->maskValue($value);
            }
        }

        return $data;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if (str_contains($key, $sensitiveKey)) {
                return true;
            }
        }
        return false;
    }

    protected function maskValue(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        if (str_starts_with($value, 'vault:')) {
            return '[ENCRYPTED PII]';
        }

        $len = mb_strlen($value);
        if ($len > 8) {
            return mb_substr($value, 0, 2) . '***' . mb_substr($value, -2);
        }

        return '***';
    }
}
