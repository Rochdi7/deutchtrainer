<?php

namespace App\Services\Import;

use App\Services\Import\Pdf\PdfLayoutReader;
use Closure;
use RuntimeException;

/**
 * PDF → structured import payload.
 *
 * Engines (config trainer.import.engine):
 *  - rules  (default): pure PHP, no external binary, no API. The layout reader pairs the
 *             German and Arabic columns by position, the rule-based parser builds the payload.
 *  - claude: the layout text is sent to the Anthropic API (needs ANTHROPIC_API_KEY).
 *  - auto:   claude when a key is configured, rules otherwise.
 */
class ImportPipeline
{
    public function __construct(
        private readonly PdfLayoutReader $reader,
        private readonly RuleBasedLektionParser $rules,
        private readonly LektionParser $claude,
        private readonly PayloadPostProcessor $post,
        private readonly ImportSchemaValidator $validator,
    ) {}

    public function engine(): string
    {
        $engine = (string) config('trainer.import.engine', 'rules');
        if ($engine === 'auto') {
            return AnthropicClient::fromConfig()->isConfigured() ? 'claude' : 'rules';
        }

        return in_array($engine, ['rules', 'claude'], true) ? $engine : 'rules';
    }

    /**
     * @param  Closure(int $done, int $total):void|null  $progress
     * @return array{text: string, payload: array, warnings: string[], engine: string}
     */
    public function run(string $absolutePdfPath, int $lektionNumber, ?Closure $progress = null): array
    {
        $doc = $this->reader->read($absolutePdfPath);
        if ($doc->isEmpty()) {
            throw new RuntimeException('The PDF contains no text layer (scanned image?). Export the word list as a text PDF and try again.');
        }

        $text = $doc->toText();
        $engine = $this->engine();

        if ($engine === 'claude') {
            $payload = $this->claude->parse($text, $lektionNumber, $progress);
            $warnings = [];
        } else {
            $progress?->__invoke(0, 1);
            $result = $this->rules->parse($doc);
            $payload = $this->post->process($this->post->coerce($result['payload']));
            $warnings = $result['warnings'];
            $progress?->__invoke(1, 1);
        }

        $check = $this->validator->validate($payload);
        if (! $check['valid']) {
            throw new RuntimeException('The parsed payload failed schema validation: '.implode('; ', array_slice($check['errors'], 0, 5)));
        }

        if ($payload['words'] === []) {
            $warnings[] = 'Keine Wörter erkannt – entspricht das PDF dem Lektion-1-Layout (Tabellen „Deutsch | العربية“)?';
        }

        return ['text' => $text, 'payload' => $payload, 'warnings' => $warnings, 'engine' => $engine];
    }
}
