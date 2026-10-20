<?php

namespace App\Services\Import;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Messages API client (POST /v1/messages) over Laravel's Http facade.
 */
class AnthropicClient
{
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('trainer.anthropic'));
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['key']);
    }

    /**
     * Send one user message with a system prompt and return the concatenated text blocks.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     */
    public function complete(string $system, array $messages, ?float $temperature = 0.0): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set. Add it to .env to run the PDF import pipeline.');
        }

        $body = [
            'model' => $this->config['model'],
            'max_tokens' => $this->config['max_tokens'],
            'system' => $system,
            'messages' => $messages,
        ];

        if ($temperature !== null) {
            $body['temperature'] = $temperature;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->config['key'],
                'anthropic-version' => $this->config['version'],
                'content-type' => 'application/json',
            ])
                ->timeout($this->config['timeout'])
                ->retry(2, 2000, throw: false)
                ->post(rtrim($this->config['base_url'], '/').'/v1/messages', $body)
                ->throw();
        } catch (RequestException $e) {
            $detail = $e->response?->json('error.message') ?? $e->getMessage();
            throw new RuntimeException('Anthropic API error: '.$detail, previous: $e);
        }

        $json = $response->json();

        if (($json['stop_reason'] ?? null) === 'max_tokens') {
            throw new RuntimeException('Anthropic response was truncated (max_tokens). Reduce IMPORT_CHUNK_LINES or raise ANTHROPIC_MAX_TOKENS.');
        }

        $text = '';
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        return $text;
    }
}
