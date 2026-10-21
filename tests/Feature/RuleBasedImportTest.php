<?php

namespace Tests\Feature;

use App\Models\Lektion;
use App\Models\Word;
use App\Services\Import\ImportPipeline;
use App\Services\Import\ImportSchemaValidator;
use App\Services\Import\Pdf\PdfLayoutReader;
use App\Services\Import\RuleBasedLektionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** End-to-end test of the API-free import on the real Lektion 1 PDF. */
class RuleBasedImportTest extends TestCase
{
    use RefreshDatabase;

    private static ?array $parsed = null;

    private function pdf(): string
    {
        return database_path('seeders/data/lektion1.pdf');
    }

    private function parsed(): array
    {
        if (self::$parsed === null) {
            config(['trainer.import.engine' => 'rules']);
            self::$parsed = app(ImportPipeline::class)->run($this->pdf(), 1);
        }

        return self::$parsed;
    }

    private function word(string $german, ?string $type = null): array
    {
        foreach ($this->parsed()['payload']['words'] as $w) {
            if ($w['german'] === $german && ($type === null || $w['type'] === $type)) {
                return $w;
            }
        }
        $this->fail("Word not found: {$german}");
    }

    public function test_reader_pairs_german_and_arabic_cells_on_the_same_row(): void
    {
        $doc = (new PdfLayoutReader)->read($this->pdf());

        $this->assertSame(7, $doc->pages);
        $this->assertTrue($doc->hasFontInfo);

        $found = null;
        foreach ($doc->lines as $line) {
            if (str_starts_with($line->first()->text(), 'die Handynummer')) {
                $found = $line;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame(2, $found->count());
        $this->assertSame('die Handynummer, -n', $found->first()->text());
        $this->assertSame('رقم الهاتف المحمول', $found->last()->text());

        $text = $doc->toText();
        $this->assertStringContainsString('das Studienfach, ¨-er | التخصص الدراسي', $text);
        $this->assertStringContainsString('Ungarn – المجر (هنغاريا)', $text, 'brackets inside RTL runs are un-mirrored');
        $this->assertStringContainsString('sein (يكون)', $text, 'right-aligned mixed cells are read right-to-left');
        $this->assertStringContainsString('Woher | kommen | Sie?', $text);
    }

    public function test_pipeline_uses_rules_engine_without_api_key(): void
    {
        config(['trainer.anthropic.key' => null, 'trainer.import.engine' => 'rules']);
        $this->assertSame('rules', app(ImportPipeline::class)->engine());

        config(['trainer.import.engine' => 'auto']);
        $this->assertSame('rules', app(ImportPipeline::class)->engine());

        config(['trainer.anthropic.key' => 'x', 'trainer.import.engine' => 'auto']);
        $this->assertSame('claude', app(ImportPipeline::class)->engine());
    }

    public function test_parses_lektion_1_vocabulary(): void
    {
        $payload = $this->parsed()['payload'];
        $this->assertTrue((new ImportSchemaValidator)->validate($payload)['valid']);
        $this->assertSame('rules', $this->parsed()['engine']);
        $this->assertGreaterThanOrEqual(200, count($payload['words']));

        $w = $this->word('Handynummer');
        $this->assertSame('die', $w['article']);
        $this->assertSame('-n', $w['plural_suffix']);
        $this->assertSame('Handynummern', $w['plural']);
        $this->assertSame('رقم الهاتف المحمول', $w['arabic']);
        $this->assertSame('noun', $w['type']);
        $this->assertSame('Formular', $w['category']);

        $this->assertSame('Studienfächer', $this->word('Studienfach')['plural']);
        $this->assertSame('Praktika', $this->word('Praktikum')['plural']);
        $this->assertSame('der/die', $this->word('Studierende')['article']);
        $this->assertSame('Studierenden', $this->word('Studierende')['plural']);
        $this->assertSame('Lehrer', $this->word('Lehrer')['plural']);

        $pl = $this->word('Kulturwissenschaften');
        $this->assertSame('nur Plural (Pl.)', $pl['note']);
        $this->assertNull($pl['plural']);
        $this->assertSame('Jura / die Rechtswissenschaften', $this->word('Jura / die Rechtswissenschaften')['german']);

        $this->assertSame('Universität und Studium', $this->word('Universität')['category']);
        $this->assertSame('Sprachen lernen', $this->word('Sprachenzentrum')['category']);
        $this->assertSame('Freizeit/Kontinente', $this->word('Afrika')['category']);
        $this->assertSame('Kontinent', $this->word('Afrika')['note']);
        $this->assertSame('Arbeit und Beruf', $this->word('Lehrer')['category']);

        $this->assertSame('verb', $this->word('lernen')['type']);
        $this->assertSame('Sprachen lernen', $this->word('lernen')['category']);
        $this->assertSame('adjective', $this->word('polyglott')['type']);
        $this->assertSame('adverb', $this->word('noch nicht')['type']);
        $this->assertSame('question_word', $this->word('Woher?')['type']);
        $this->assertSame('من أين؟', $this->word('Woher?')['arabic']);
        $this->assertSame('particle', $this->word('ja')['type']);
        $this->assertSame('expression', $this->word('Herzlich willkommen!')['type']);
        $this->assertSame('أهلاً وسهلاً! / مرحبًا بكم!', $this->word('Herzlich willkommen!')['arabic']);

        $aus = $this->word('aus', 'preposition');
        $this->assertSame('aus (Rio / Brasilien)', $aus['note']);

        $arbeiten = $this->word('arbeiten (als …)');
        $this->assertSame('verb', $arbeiten['type']);
        $this->assertSame('t/d', $arbeiten['meta']['stem_rule']);
        $this->assertFalse($arbeiten['meta']['irregular']);

        $this->assertTrue($this->word('sprechen')['meta']['irregular']);
        $this->assertTrue($this->word('können')['meta']['modal']);
    }

    public function test_parses_countries_languages_and_nationalities(): void
    {
        $payload = $this->parsed()['payload'];
        $countries = array_values(array_filter($payload['words'], fn ($w) => $w['type'] === 'country'));
        $languages = array_values(array_filter($payload['words'], fn ($w) => $w['type'] === 'language'));
        $nationalities = array_values(array_filter($payload['words'], fn ($w) => $w['type'] === 'nationality'));

        $this->assertCount(21, $countries);
        $this->assertCount(16, $languages);
        $this->assertCount(21, $nationalities);

        $arg = $this->word('Argentinien', 'country');
        $this->assertSame('الأرجنتين', $arg['arabic']);
        $this->assertSame('Spanisch', $arg['meta']['language']);
        $this->assertSame('der Argentinier', $arg['meta']['nationality_m']);
        $this->assertSame('die Argentinierin', $arg['meta']['nationality_f']);

        $this->assertSame('mit Artikel: die Türkei', $this->word('die Türkei', 'country')['note']);
        $this->assertSame('المجر (هنغاريا)', $this->word('Ungarn', 'country')['arabic']);

        $spanisch = $this->word('Spanisch', 'language');
        $this->assertSame('الإسبانية', $spanisch['arabic']);
        $this->assertSame('Argentinien, Kolumbien, Spanien', $spanisch['note']);

        $nat = $this->word('der/die Argentinier/in', 'nationality');
        $this->assertSame('أرجنتيني/ة', $nat['arabic']);
        $this->assertSame('Argentinien', $nat['meta']['country']);

        $chinese = $this->word('der Chinese / die Chinesin', 'nationality');
        $this->assertSame('der Chinese', $chinese['meta']['m']);
        $this->assertSame('die Chinesin', $chinese['meta']['f']);
    }

    public function test_parses_conjugation_tables(): void
    {
        $conj = collect($this->parsed()['payload']['conjugations'])->keyBy('german');

        $this->assertGreaterThanOrEqual(34, $conj->count());

        $this->assertSame(['komme', 'kommst', 'kommt', 'kommen', 'kommt', 'kommen'],
            array_values(array_intersect_key($conj['kommen'], array_flip(RuleBasedLektionParser::PRONOUN_KEYS))));
        $this->assertSame('bist', $conj['sein']['du']);
        $this->assertSame('seid', $conj['sein']['ihr']);
        $this->assertSame('arbeitest', $conj['arbeiten (als …)']['du'], 'table verb linked to the vocabulary entry');
        $this->assertSame('sprichst', $conj['sprechen']['du']);
        $this->assertSame('schläfst', $conj['schlafen']['du']);
        $this->assertSame('möchtest', $conj['möchten']['du']);
        $this->assertSame('darf', $conj['dürfen']['er_sie_es']);
        $this->assertSame('weiß', $conj['wissen']['ich']);

        // Regular verbs from the word lists get a generated conjugation; irregular ones without a table do not.
        $this->assertSame('verstehst', $conj['verstehen']['du']);
        $this->assertSame('buchstabierst', $conj['buchstabieren']['du']);
        $this->assertFalse($conj->has('(sich) vorstellen'), 'separable verbs are not generated');

        $trinken = $this->word('trinken');
        $this->assertSame('يشرب', $trinken['arabic']);
        $this->assertSame('Verben', $trinken['category']);
        $this->assertTrue($this->word('lesen')['meta']['irregular']);
        $this->assertSame('unregelmäßig: du liest, er liest', $this->word('lesen')['note']);
    }

    public function test_parses_dialog_table(): void
    {
        $dialog = collect($this->parsed()['payload']['dialog_lines'])->keyBy('topic');

        $this->assertGreaterThanOrEqual(13, $dialog->count());
        $this->assertSame('Wie heißen Sie?', $dialog['Name']['question_formal']);
        $this->assertSame('Wie heißt du?', $dialog['Name']['question_informal']);
        $this->assertSame('Ich heiße / Ich bin / Mein Name ist Tarik Amri.', $dialog['Name']['answer']);
        $this->assertSame('ما اسمك؟ – اسمي طارق العمري.', $dialog['Name']['arabic']);

        $this->assertSame('Wie ist Ihr Vorname?', $dialog['Vorname']['question_formal']);
        $this->assertSame('Wie ist dein Vorname?', $dialog['Vorname']['question_informal']);
        $this->assertSame('Woher kommen Sie?', $dialog['Land']['question_formal']);
        $this->assertSame('Woher kommst du?', $dialog['Land']['question_informal']);
        $this->assertSame('Welche Sprachen sprechen Sie?', $dialog['Sprachen']['question_formal']);
        $this->assertSame('(Ich spreche) Arabisch und Französisch.', $dialog['Sprachen']['answer']);
        $this->assertSame('Wie ist Ihre Telefonnummer / Handynummer?', $dialog['Telefonnummer']['question_formal']);
        $this->assertSame('(Meine Adresse ist) Fichtenweg 11, 72076 Tübingen.', $dialog['Adresse']['answer']);
        $this->assertSame('Wo wohnen Sie?', $dialog['Wohnort']['question_formal']);
        $this->assertSame('Guten Morgen! Guten Tag! Hallo! Grüß dich!', $dialog['Begrüßung']['answer']);
        $this->assertSame('Auf Wiedersehen. / Tschüss.', $dialog['Verabschiedung']['answer']);
    }

    public function test_builds_grammar_notes(): void
    {
        $notes = collect($this->parsed()['payload']['grammar_notes']);

        $this->assertGreaterThanOrEqual(3, $notes->count());
        $titles = $notes->pluck('title')->all();
        $this->assertContains('Regelmäßige Verben im Präsens – الأفعال المنتظمة في المضارع', $titles);
        $this->assertContains('Wortstellung – ترتيب الكلمات في الجملة', $titles);

        $wortstellung = $notes->firstWhere('title', 'Wortstellung – ترتيب الكلمات في الجملة');
        $this->assertStringContainsString('**W-Fragen', $wortstellung['body_md']);
        $this->assertStringContainsString('| Woher | kommen | Sie? |', $wortstellung['body_md']);

        $regular = $notes->firstWhere('title', 'Regelmäßige Verben im Präsens – الأفعال المنتظمة في المضارع');
        $this->assertStringContainsString('| ich | komme | wohne | heiße | arbeite | bin |', $regular['body_md']);
        $this->assertStringContainsString('- جذر ينتهي بـ -t / -d / -n (مثل arbeiten)', $regular['body_md']);
    }

    public function test_cli_command_imports_the_pdf_into_the_database(): void
    {
        config(['trainer.import.engine' => 'rules', 'trainer.anthropic.key' => null]);

        $this->artisan('import:lektion', ['pdf' => $this->pdf(), 'number' => 1, '--title' => 'Lektion 1'])
            ->assertSuccessful();

        $lektion = Lektion::where('number', 1)->firstOrFail();
        $this->assertGreaterThanOrEqual(200, $lektion->words()->count());
        $this->assertSame('Lektion 1', $lektion->title);

        $sein = Word::where('german', 'sein')->firstOrFail();
        $this->assertNotNull($sein->conjugation);
        $this->assertSame('bin', $sein->conjugation->ich);
        $this->assertDatabaseCount('dialog_lines', count($this->parsed()['payload']['dialog_lines']));
        $this->assertGreaterThanOrEqual(3, $lektion->grammarNotes()->count());
    }
}
