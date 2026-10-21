<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\Word;
use Closure;
use RuntimeException;

/**
 * Optional Claude engine (IMPORT_ENGINE=claude): sends ~120-line chunks of the layout text
 * to the Anthropic API and merges the validated JSON replies.
 */
class LektionParser
{
    public function __construct(
        private readonly AnthropicClient $client,
        private readonly ImportSchemaValidator $validator,
        private readonly TextNormalizer $normalizer,
        private readonly PayloadPostProcessor $post,
    ) {}

    /**
     * @param  Closure(int $done, int $total):void|null  $progress
     */
    public function parse(string $normalizedText, int $lektionNumber, ?Closure $progress = null): array
    {
        $chunks = $this->normalizer->chunk($normalizedText, (int) config('trainer.import.chunk_lines', 120));
        $total = count($chunks);
        $merged = ['words' => [], 'conjugations' => [], 'dialog_lines' => [], 'grammar_notes' => []];

        foreach ($chunks as $i => $chunk) {
            $part = $this->parseChunk($chunk, $lektionNumber, $i + 1, $total);
            foreach ($merged as $key => $_) {
                $merged[$key] = array_merge($merged[$key], $part[$key] ?? []);
            }
            $progress?->__invoke($i + 1, $total);
        }

        return $this->postProcess($merged);
    }

    /** One chunk -> validated array. Retries once with the validation errors fed back. */
    public function parseChunk(string $chunk, int $lektionNumber, int $index = 1, int $total = 1): array
    {
        $messages = [[
            'role' => 'user',
            'content' => "Lektion {$lektionNumber}, Textabschnitt {$index}/{$total}.\n\n<layout>\n{$chunk}\n</layout>",
        ]];

        $lastErrors = [];

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $raw = $this->client->complete($this->systemPrompt(), $messages, 0.0);
            $json = $this->validator->extractJson($raw);

            if ($json === null) {
                $lastErrors = ['Response was not a JSON object.'];
            } else {
                $json = $this->post->coerce($json);
                $result = $this->validator->validate($json);
                if ($result['valid']) {
                    return $json;
                }
                $lastErrors = $result['errors'];
            }

            // Feed the problem back and retry exactly once.
            $messages[] = ['role' => 'assistant', 'content' => $raw];
            $messages[] = [
                'role' => 'user',
                'content' => "Die Antwort war ungültig:\n- ".implode("\n- ", array_slice($lastErrors, 0, 20))
                    ."\n\nGib NUR das korrigierte JSON-Objekt zurück, ohne Erklärung und ohne Code-Fences.",
            ];
        }

        throw new RuntimeException('Chunk '.$index.' could not be parsed into valid JSON after retry: '.implode('; ', array_slice($lastErrors, 0, 5)));
    }

    /** Dedupe across chunks and derive plural forms where only the suffix was given. */
    public function postProcess(array $merged): array
    {
        return $this->post->process($merged);
    }

    public function systemPrompt(): string
    {
        $types = implode(', ', Word::TYPES);
        $categories = implode(', ', Category::CANONICAL);

        return <<<PROMPT
Du bist ein präziser Parser für Wortschatzlisten des Lehrwerks "DaF kompakt A1" (Deutsch–Arabisch).
Du bekommst den Layout-Text eines Lektions-PDFs (eine Zeile pro PDF-Zeile, Tabellenzellen durch ` | ` getrennt). Du gibst AUSSCHLIESSLICH ein JSON-Objekt zurück.

## Format der Quelle
- Zeilen der Wortliste haben die Form `Artikel Nomen, Pluralendung   Arabisch` (z. B. `die Handynummer, -n   رقم الهاتف المحمول`).
- Die arabische Übersetzung steht in derselben Zeile rechts vom ` | `. Gib sie in korrekter Buchstabenfolge zurück; repariere offensichtlich zerrissene Wörter.
- Wenn ein Eintrag Tokens verdreht enthält (z. B. `رقم الهاتف المحمول n- ,Handynummer die`), bedeutet das `die Handynummer, -n` + Arabisch.
- Abschnittsüberschriften wie `Formular – ...`, `Sprachen lernen – ...`, `Verben – ...`, `Adjektive – ...`, `Adverbien – ...`, `Fragewörter`, `Präpositionen`, `Partikeln`, `Redemittel / Ausdrücke`, `Länder – Sprachen – Nationalitäten`, `Redemittel: ...`, `Grammatik: ...`, `Wortstellung` bestimmen `category` und `type`.
- `(Pl.)` heißt: nur Plural. `¨-er` / `¨-e` bedeutet Umlaut + Endung (Studienfach, ¨-er → Studienfächer; Buch, ¨-er → Bücher; Stuhl, ¨-e → Stühle). `-` allein bedeutet unveränderter Plural (Lehrer → Lehrer). Eine ausgeschriebene Form (`Praktikum, Praktika`) ist die volle Pluralform.
- Deutschen Text immer korrekt mit Umlauten/ß schreiben, Nummerierungen, Spaltentitel (`Deutsch`, `العربية`), Seitenköpfe und Datumsangaben ignorieren.

## Ausgabe-Schema (nur diese Schlüssel)
{
  "words": [{"german": string, "article": "der"|"die"|"das"|"der/die"|null, "plural": string|null (volle Form, z. B. "Formulare"), "plural_suffix": string|null (roh, z. B. "-e", "¨-er", "-", "Praktika"), "arabic": string, "type": one of [{$types}], "category": one of [{$categories}], "example_de": string|null, "example_ar": string|null, "note": string|null, "meta": object|null}],
  "conjugations": [{"german": Infinitiv, "ich": string, "du": string, "er_sie_es": string, "wir": string, "ihr": string, "sie_Sie": string}],
  "dialog_lines": [{"topic": string, "question_formal": string|null, "question_informal": string|null, "answer": string, "arabic": string}],
  "grammar_notes": [{"title": string, "body_md": string}]
}

## Regeln für `type` / `category` / `meta`
- Nomen → type "noun", article gesetzt; Kontinente (Afrika …) → "noun" ohne Artikel, note "Kontinent".
- Verben → "verb". meta: {"irregular": bool, "stem_rule": "t/d" | "s/ß/z" | null, "modal": bool}. Reflexivpartikel wie "(sich)" im Feld german belassen (z. B. "(sich) vorstellen").
- Adjektive → "adjective", Adverbien → "adverb", Fragewörter → "question_word" (german z. B. "Wie?"), Präpositionen → "preposition", Partikeln → "particle", Redemittel/Ausdrücke → "expression" (category "Redemittel").
- Tabelle Länder – Sprachen – Nationalitäten: pro Land DREI Einträge, alle mit category "Länder-Sprachen-Nationalitäten":
  1. type "country": german = Land (mit Artikel im Feld german belassen, z. B. "die Türkei", article null), arabic = arabischer Ländername, meta {"language": "Türkisch", "nationality_m": "der Türke", "nationality_f": "die Türkin"}.
  2. type "language": german = Sprache (z. B. "Türkisch"), arabic = arabische Bezeichnung (z. B. "التركية"). Jede Sprache nur einmal.
  3. type "nationality": german = "der Türke / die Türkin" (bzw. "der/die Spanier/in"), arabic = "تركي/ة", meta {"country": "die Türkei", "m": "der Türke", "f": "die Türkin"}.
  Die Nationalitäten-Spalte ist im Rohtext oft zeichenweise mit Arabisch vermischt (z. B. `ة/يdنeتيrن/جdرiأe–Argentinier/in`): rekonstruiere `der/die Argentinier/in` + `أرجنتيني/ة`. Die Spalte ist gegenüber den Ländern nach unten verschoben – ordne nach Reihenfolge zu.
- Konjugationstabellen (Spalten = Verben, Zeilen = ich/du/er-sie-es/wir/ihr/sie-Sie) → `conjugations`. Die Spalten sind im Rohtext vertikal verschoben: ordne die Formen über die Endungen zu (ich -e, du -st, er/sie/es -t, wir -en, ihr -t, sie/Sie -en; bei unregelmäßigen Verben die bekannten Formen). Für jedes Verb in einer Konjugationstabelle, das noch nicht in `words` steht, zusätzlich einen `words`-Eintrag mit type "verb", category "Verben", arabic aus der Klammer in der Tabellenüberschrift (z. B. `trinken (يشرب)`) und passendem meta anlegen (Modalverben: modal true, irregular true).
- Redemittel-Dialogtabelle (Thema / Person A Frage / Person B Antwort / Arabisch) → `dialog_lines`: question_formal = Sie-Form, question_informal = du-Form (aus "Wie heißen Sie? / Wie heißt du?" bzw. "Ihr / dein" aufsplitten), answer = Antwort, arabic = arabische Übersetzung der Zeile. Thema als topic (Begrüßung, Name, Vorname, Nachname, Buchstabieren, Land, Nationalität, Sprachen, Wohnort, Adresse, Telefonnummer, E-Mail, Verabschiedung).
- Grammatikblöcke (Regeln, Wortstellungstabellen, Hinweise zu -t/-d, -s/-ß/-z, Modalverben, Sie/du) → `grammar_notes` mit kurzem Titel und body_md in Markdown (Tabellen als Markdown-Tabellen, arabische Erklärungen übernehmen).
- Wenn ein Abschnitt in diesem Textabschnitt nicht vorkommt, das Array leer lassen. Keine Einträge erfinden. Keine Duplikate innerhalb der Antwort.

Antworte NUR mit dem JSON-Objekt. Keine Einleitung, keine Code-Fences, kein Kommentar.
PROMPT;
    }
}
