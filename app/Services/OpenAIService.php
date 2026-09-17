<?php

declare(strict_types=1);

namespace MeatinOS\Services;

final class OpenAIService
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';

    public function respond(string $apiKey, string $model, string $instructions, string $input, int $maxOutputTokens = 900): array
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('PHP cURL is required for OpenAI connectivity.');
        $payload = json_encode([
            'model' => $model,
            'instructions' => $instructions,
            'input' => $input,
            'max_output_tokens' => $maxOutputTokens,
            'store' => false,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $started = microtime(true);
        $status = 0;
        $body = '';
        $requestId = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $headers = [];
            $handle = curl_init(self::ENDPOINT);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey,'Content-Type: application/json'],
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                    return strlen($line);
                },
            ]);
            $body = (string) curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            curl_close($handle);
            $requestId = $headers['x-request-id'] ?? null;
            if ($error !== '') throw new \RuntimeException('OpenAI connection failed. Check outbound HTTPS access.');
            if (!in_array($status, [429,500,502,503,504], true) || $attempt === 1) break;
            usleep(300000 * ($attempt + 1));
        }
        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $providerCode = is_array($decoded) ? (string) ($decoded['error']['code'] ?? '') : '';
            $safe = match ($status) {
                401 => 'OpenAI rejected the API key.',
                429 => 'OpenAI rate or billing limit reached. Try again later.',
                default => 'OpenAI request failed with status ' . $status . '.',
            };
            throw new \RuntimeException($safe . ($providerCode !== '' ? ' Code: ' . preg_replace('/[^a-z0-9_.-]/i', '', $providerCode) : ''));
        }
        $text = '';
        foreach (($decoded['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text') $text .= (string) ($content['text'] ?? '');
            }
        }
        if (trim($text) === '') throw new \RuntimeException('OpenAI returned no usable text.');
        return [
            'text' => trim($text),
            'request_id' => $requestId ?: ($decoded['id'] ?? null),
            'input_tokens' => (int) ($decoded['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($decoded['usage']['output_tokens'] ?? 0),
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }
}
