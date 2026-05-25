<?php

namespace App\Services\Llm;

class PromptRedactor
{
    public function redact(string $prompt): string
    {
        if (! (bool) config('llm.redact_prompts', true)) {
            return $prompt;
        }

        $patterns = [
            '/([A-Z0-9._%+\-]+)@([A-Z0-9.\-]+\.[A-Z]{2,})/iu' => '[redacted-email]',
            '/\b(?:password|token|api[_-]?key|secret|credential|authorization)\s*[:=]\s*[^\s,;]+/iu' => '[redacted-secret]',
            '/\b\d{10,12}\b/u' => '[redacted-tax-id]',
            '/\b(?:\+?\d[\d\s().-]{8,}\d)\b/u' => '[redacted-phone]',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $prompt);
    }
}
