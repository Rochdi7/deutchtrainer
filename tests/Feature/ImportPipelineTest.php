<?php

namespace Tests\Feature;

use App\Models\Lektion;
use App\Models\Word;
use App\Services\Import\AnthropicClient;
use App\Services\Import\ImportPersister;
use App\Services\Import\ImportSchemaValidator;
use App\Services\Import\LektionParser;
use App\Services\Import\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        return [
            'words' => [
                ['german' => 'Handynummer', 'article' => 'die', 'plural' => null, 'plural_suffix' => '-n', 'arabic' => 'رقم الهاتف المحمول', 'type' => 'noun', 'category' => 'Formular', 'note' => null, 'meta' => null],
                ['german' => 'wohnen', 'article' => null, 'plural' => null, 'plural_suffix' => null, 'arabic' => 'يسكن', 'type' => 'verb', 'category' => 'Verben', 'note' => null, 'meta' => ['irregular' => false, 'stem_rule' => null, 'modal' => false]],
            ],
            'conjugations' => [
                ['german' => 'wohnen', 'ich' => 'wohne', 'du' => 'wohnst', 'er_sie_es' => 'wohnt', 'wir' => 'wohnen', 'ihr' => 'wohnt', 'sie_Sie' => 'wohnen'],
            ],
            'dialog_lines' => [
                ['topic' => 'Name', 'question_formal' => 'Wie heißen Sie?', 'question_informal' => 'Wie heißt du?', 'answer' => 'Ich heiße Tarik.', 'arabic' => 'ما اسمك؟'],
            ],
            'grammar_notes' => [
                ['title' => 'Wortstellung', 'body_md' => '| Position 1 | Position 2 |'],
            ],
        ];
    }

    public function test_schema_accepts_valid_payload(): void
    {
        $result = (new ImportSchemaValidator)->validate($this->validPayload());
        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
    }

    public function test_schema_rejects_bad_type_missing_fields_and_unknown_keys(): void
    {
        $validator = new ImportSchemaValidator;

        $bad = $this->validPayload();
        $bad['words'][0]['type'] = 'Nomen';
        $this->assertFalse($validator->validate($bad)['valid']);

        $bad = $this->validPayload();
        unset($bad['words'][0]['arabic']);
        $this->assertFalse($validator->validate($bad)['valid']);

        $bad = $this->validPayload();
        $bad['words'][0]['extra'] = 'x';
        $this->assertFalse($validator->validate($bad)['valid']);

        $bad = $this->validPayload();
        $bad['conjugations'][0]['du'] = '';
        $this->assertFalse($validator->validate($bad)['valid']);

        $this->assertFalse($validator->validate(['nope' => []])['valid']);
    }

    public function test_extract_json_handles_code_fences_and_chatter(): void
    {
        $validator = new ImportSchemaValidator;
        $this->assertSame(['words' => []], $validator->extractJson("```json\n{\"words\": []}\n```"));
        $this->assertSame(['words' => []], $validator->extractJson("Hier ist das JSON:\n{\"words\": []}\nFertig."));
        $this->assertNull($validator->extractJson('no json here'));
    }

    public function test_parser_retries_once_on_invalid_json_and_then_succeeds(): void
    {
        config(['trainer.anthropic.key' => 'test-key']);
        $payload = $this->validPayload();

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push($this->anthropicReply('this is not json'), 200)
                ->push($this->anthropicReply(json_encode($payload)), 200),
        ]);

        $parser = app(LektionParser::class);
        $result = $parser->parseChunk("die Handynummer, -n   رقم الهاتف المحمول\nwohnen   يسكن", 1);

        $this->assertCount(2, $result['words']);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $body['model'] === config('trainer.anthropic.model')
                && $body['temperature'] === 0.0
                && str_contains($body['system'], 'DaF kompakt');
        });
    }

    public function test_parser_fails_after_second_invalid_reply(): void
    {
        config(['trainer.anthropic.key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push($this->anthropicReply('{"words": [{"german": "x"}]}'), 200)
                ->push($this->anthropicReply('still broken'), 200),
        ]);

        $this->expectException(RuntimeException::class);
        app(LektionParser::class)->parseChunk('irrelevant', 1);
    }

    public function test_client_requires_api_key(): void
    {
        config(['trainer.anthropic.key' => null]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ANTHROPIC_API_KEY');
        AnthropicClient::fromConfig()->complete('sys', [['role' => 'user', 'content' => 'x']]);
    }

    public function test_persister_inserts_links_and_dedupes(): void
    {
        $payload = app(LektionParser::class)->postProcess($this->validPayload());
        $persister = app(ImportPersister::class);

        $first = $persister->persist($payload, 1, 'Lektion 1');
        $this->assertSame(2, $first['words']);
        $this->assertSame(0, $first['skipped']);
        $this->assertSame(1, $first['conjugations']);
        $this->assertSame(1, $first['dialog_lines']);
        $this->assertSame(1, $first['grammar_notes']);

        $noun = Word::where('german', 'Handynummer')->first();
        $this->assertSame('Handynummern', $noun->plural, 'plural expanded from suffix');
        $this->assertSame('Formular', $noun->category->name);
        $this->assertNotNull(Word::where('german', 'wohnen')->first()->conjugation);

        // Re-import the same payload: words are skipped, nothing duplicated.
        $second = $persister->persist($payload, 1, 'Lektion 1');
        $this->assertSame(0, $second['words']);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame(1, Lektion::count());
        $this->assertSame(2, Word::count());
        $this->assertDatabaseCount('dialog_lines', 1);
        $this->assertDatabaseCount('grammar_notes', 1);
    }

    public function test_normalizer_folds_presentation_forms_and_strips_bidi_marks(): void
    {
        $raw = "die Handynummer, -n   \u{202B}\u{FEFC}\u{FEEE}\u{202C}";
        $clean = (new TextNormalizer)->normalize($raw);

        $this->assertStringNotContainsString("\u{202B}", $clean);
        $this->assertStringContainsString('لا', $clean); // U+FEFC is the "lam-alef" ligature
    }

    public function test_chunker_splits_long_text(): void
    {
        $lines = [];
        for ($i = 0; $i < 300; $i++) {
            $lines[] = " das Wort{$i}, -e   كلمة";
        }
        $chunks = (new TextNormalizer)->chunk(implode("\n", $lines), 120);

        $this->assertCount(3, $chunks);
        $this->assertSame(300, array_sum(array_map(fn ($c) => count(array_filter(explode("\n", $c), fn ($l) => str_contains($l, 'Wort'))), $chunks)));
    }

    public function test_seed_payload_for_lektion_1_is_valid(): void
    {
        $payload = json_decode(file_get_contents(database_path('seeders/data/lektion1.json')), true);
        $payload = app(LektionParser::class)->postProcess($payload);
        $result = (new ImportSchemaValidator)->validate($payload);

        $this->assertTrue($result['valid'], implode("\n", array_slice($result['errors'], 0, 5)));
        $this->assertGreaterThan(150, count($payload['words']));
    }

    private function anthropicReply(string $text): array
    {
        return [
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-4-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }
}
