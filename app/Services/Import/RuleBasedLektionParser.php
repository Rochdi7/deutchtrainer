<?php

namespace App\Services\Import;

use App\Services\Import\Pdf\Cell;
use App\Services\Import\Pdf\Document;
use App\Services\Import\Pdf\Line;

/**
 * Deterministic parser for the "DaF kompakt A1 – Lektion N Wortschatz (Deutsch – العربية)" PDFs.
 *
 * Works on the layout produced by {@see Pdf\PdfLayoutReader}: section headers (bold, at the left
 * margin), vocabulary tables ("Deutsch | العربية"), the Länder–Sprachen–Nationalitäten table,
 * the Redemittel dialog table (Thema | Person A | Person B | العربية), conjugation tables
 * (verbs as columns, ich/du/… as rows) and free grammar text. No network, no external binary.
 */
class RuleBasedLektionParser
{
    public const PRONOUN_KEYS = ['ich', 'du', 'er_sie_es', 'wir', 'ihr', 'sie_Sie'];

    private const CAT_LSN = 'Länder-Sprachen-Nationalitäten';

    private const MODALS = ['können', 'müssen', 'wollen', 'dürfen', 'sollen', 'mögen', 'möchten'];

    /** Verbs with an irregular present tense (stem change or suppletive forms). */
    private const IRREGULAR = [
        'sein', 'haben', 'werden', 'wissen', 'tun', 'sprechen', 'lesen', 'essen', 'sehen', 'fahren', 'schlafen',
        'nehmen', 'geben', 'treffen', 'helfen', 'laufen', 'lassen', 'tragen', 'waschen', 'fallen', 'halten',
        'gefallen', 'vergessen', 'empfehlen', 'sterben', 'werfen', 'brechen', 'fangen', 'schlagen', 'wachsen',
        'raten', 'braten', 'laden', 'stehlen', 'befehlen', 'erschrecken', 'gelten', 'messen', 'treten',
        'geschehen', 'blasen', 'graben', 'stoßen', 'backen', 'fressen', 'schmelzen', 'verderben', 'mögen',
        'möchten', 'dürfen', 'können', 'müssen', 'wollen', 'sollen',
    ];

    private const SEPARABLE_PREFIXES = ['ab', 'an', 'auf', 'aus', 'bei', 'ein', 'fern', 'fest', 'frei', 'her', 'hin', 'los', 'mit', 'nach', 'statt', 'teil', 'vor', 'weg', 'zu', 'zurück', 'zusammen'];

    /** Dialog topic inferred from the question when the "Thema" column repeats a label. */
    private const TOPIC_RULES = [
        '/Vorname/u' => 'Vorname',
        '/Nachname|Familienname/u' => 'Nachname',
        '/uchstabier|schreibt man/u' => 'Buchstabieren',
        '/heiß|Name ist/u' => 'Name',
        '/geht es|geht\'s/u' => 'Reaktion',
        '/Guten (Morgen|Tag|Abend)|Hallo|Grüß/u' => 'Begrüßung',
        '/Wiedersehen|Tschüss|Gute Nacht/u' => 'Verabschiedung',
        '/Woher/u' => 'Land',
        '/Nationalität/u' => 'Nationalität',
        '/Sprache/u' => 'Sprachen',
        '/Wo wohn/u' => 'Wohnort',
        '/Telefon|Handy/u' => 'Telefonnummer',
        '/E-Mail/u' => 'E-Mail',
        '/Adresse/u' => 'Adresse',
        '/wie alt|Alter/iu' => 'Alter',
        '/Beruf|arbeit/iu' => 'Beruf',
    ];

    private Document $doc;

    /** @var array<int, int> line index => header level (0 = document title, 1 = section, 2+ = sub-section) */
    private array $levels = [];

    private array $words = [];

    /** @var array<string, array{arabic: ?string, forms: array<string, string>, section: ?string}> */
    private array $tableVerbs = [];

    private array $dialog = [];

    private array $notes = [];

    private array $warnings = [];

    /** @var array{title: string, body: string[], table: bool}|null */
    private ?array $section = null;

    /** @return array{payload: array, warnings: string[]} */
    public function parse(Document $doc): array
    {
        $this->doc = $doc;
        $this->words = $this->tableVerbs = $this->dialog = $this->notes = $this->warnings = [];
        $this->section = null;
        $this->levels = $this->headerLevels();

        $lines = $doc->lines;
        $n = count($lines);
        $category = 'Nomen';

        for ($i = 0; $i < $n;) {
            $line = $lines[$i];

            if (array_key_exists($i, $this->levels)) {
                $level = $this->levels[$i];
                if ($level === 0) {
                    $i++;

                    continue;
                }
                $title = $this->clean($line->text());
                if ($level === 1 || $this->section === null) {
                    $this->flushSection();
                    $this->section = ['title' => $title, 'body' => [], 'table' => false];
                } else {
                    $this->section['body'][] = '**'.$title.'**';
                }
                $category = $this->categoryFor($this->germanPart($title));
                $i++;

                continue;
            }

            if ($this->section === null) {
                $i++; // page header / date line before the first section

                continue;
            }

            if ($this->isVocabHeader($line)) {
                $this->section['table'] = true;
                $i = $this->parseVocab($i + 1, $category);

                continue;
            }
            if ($this->isLaenderHeader($line)) {
                $this->section['table'] = true;
                $i = $this->parseLaender($i);

                continue;
            }
            if ($this->isDialogHeader($line)) {
                $this->section['table'] = true;
                $i = $this->parseDialog($i);

                continue;
            }
            if ($line->count() >= 2) {
                if ($this->looksLikeVocabRow($line)) {
                    $this->section['table'] = true;
                    $i = $this->parseVocab($i, $category);
                } else {
                    $i = $this->parseTableBlock($i);
                }

                continue;
            }

            $this->section['body'][] = $this->paragraph($line);
            $i++;
        }

        $this->flushSection();
        $this->finalizeVerbs();
        $this->finalizeDialogTopics();

        return [
            'payload' => [
                'words' => array_values($this->words),
                'conjugations' => $this->conjugations(),
                'dialog_lines' => $this->dialog,
                'grammar_notes' => $this->notes,
            ],
            'warnings' => $this->warnings,
        ];
    }

    // ------------------------------------------------------------------ headers

    /** @return array<int, int> */
    private function headerLevels(): array
    {
        $geo = $this->doc->geometry;
        $candidates = [];
        foreach ($this->doc->lines as $i => $line) {
            if ($line->count() !== 1) {
                continue;
            }
            $cell = $line->first();
            $text = $this->clean($cell->text());
            if ($text === '' || ! $geo->isAtMargin($cell->x0)) {
                continue;
            }
            if ($this->doc->hasFontInfo) {
                if (! $cell->bold) {
                    continue;
                }
            } elseif (! preg_match('/^(\p{Lu}[^|]{1,80}\s[–\-]\s.*\p{Arabic}|Grammatik|Redemittel|Wortstellung)/u', $text)) {
                continue;
            }
            if ($this->pronounKey($text) !== null || preg_match('/^(Deutsch|Thema|Person\b|Position\b)/u', $text)) {
                continue;
            }
            $candidates[$i] = round($cell->size, 1);
        }
        if ($candidates === []) {
            return [];
        }

        $sizes = array_values(array_unique(array_values($candidates)));
        rsort($sizes);

        $titleIndex = null;
        if (count($sizes) >= 2) {
            $top = array_keys($candidates, $sizes[0], true);
            if (count($top) === 1 && $top[0] === array_key_first($candidates)) {
                $titleIndex = $top[0];
                array_shift($sizes);
            }
        }

        $levels = [];
        foreach ($candidates as $i => $size) {
            $levels[$i] = $i === $titleIndex ? 0 : (int) array_search($size, $sizes, true) + 1;
        }

        return $levels;
    }

    private function germanPart(string $title): string
    {
        $title = preg_replace('/^(Grammatik|Wortschatz|Vokabeln)\s*:\s*/u', '', $title);
        $parts = preg_split('/\s+[–\-]\s+(?=\p{Arabic})/u', $title, 2);

        return $this->clean($parts[0]);
    }

    private function categoryFor(string $germanTitle): string
    {
        return PluralExpander::matchCategory($germanTitle) ?? ($germanTitle !== '' ? mb_substr($germanTitle, 0, 100) : 'Nomen');
    }

    // ------------------------------------------------------------------ vocabulary tables

    private function isVocabHeader(Line $line): bool
    {
        return $line->count() >= 2 && preg_match('/^Deutsch\b/iu', $line->first()->text());
    }

    private function looksLikeVocabRow(Line $line): bool
    {
        if ($line->count() < 2 || $line->count() > 3) {
            return false;
        }
        $first = $line->first();
        $last = $line->last();

        return $last->isArabicDominant() && $first->hasLatin() && ! $first->hasArabic()
            && $this->pronounKey($first->text()) === null && ! str_contains($first->text(), ' – ');
    }

    private function parseVocab(int $i, string $category): int
    {
        $lines = $this->doc->lines;
        $n = count($lines);
        $geo = $this->doc->geometry;
        $pending = null;        // German-only line waiting for its Arabic line
        $lastIndex = null;

        for (; $i < $n; $i++) {
            $line = $lines[$i];
            if (array_key_exists($i, $this->levels)) {
                break;
            }
            if ($this->isVocabHeader($line)) {
                continue; // header repeated after a page break
            }

            if ($line->count() === 1) {
                $cell = $line->first();
                if ($geo->isAtMargin($cell->x0)) {
                    break; // a paragraph: the table has ended
                }
                if ($cell->isArabicDominant()) {
                    if ($pending !== null) {
                        $pending['arabic'] = trim($pending['arabic'].' '.$cell->text());
                        $lastIndex = $this->addVocabWord($pending['german'], $pending['arabic'], $category);
                        $pending = null;
                    } elseif ($lastIndex !== null) {
                        $this->words[$lastIndex]['arabic'] = trim($this->words[$lastIndex]['arabic'].' '.$cell->text());
                    }
                } else {
                    if ($pending !== null) {
                        $this->warn('Eintrag ohne arabische Übersetzung übersprungen: '.$pending['german']);
                    }
                    $pending = ['german' => $cell->text(), 'arabic' => ''];
                }

                continue;
            }

            $cells = $line->cells;
            if (! $line->last()->isArabicDominant() && $line->last()->hasArabic()) {
                $split = $line->last()->splitBeforeArabicTail();
                if ($split !== null) {
                    array_pop($cells);
                    array_push($cells, ...$split);
                }
            }
            if ($this->pronounKey($cells[0]->text()) !== null) {
                break; // a conjugation table starts here
            }
            if (! end($cells)->isArabicDominant()) {
                $this->warn('Zeile nicht als Wortschatz erkannt: '.$line->text());

                continue;
            }
            if ($pending !== null) {
                $this->warn('Eintrag ohne arabische Übersetzung übersprungen: '.$pending['german']);
                $pending = null;
            }

            $arabic = end($cells)->text();
            $germanCells = array_slice($cells, 0, -1);
            $german = $germanCells[0]->text();
            for ($k = 1; $k < count($germanCells); $k++) {
                $extra = $germanCells[$k]->text();
                $german .= (preg_match('/^[¨"\-]/u', $extra) ? ', ' : ' ').$extra;
            }
            $lastIndex = $this->addVocabWord($german, $arabic, $category);
        }

        if ($pending !== null) {
            $this->warn('Eintrag ohne arabische Übersetzung übersprungen: '.$pending['german']);
        }

        return $i;
    }

    private function addVocabWord(string $german, string $arabic, string $category): ?int
    {
        $german = $this->clean($german);
        $arabic = $this->clean($arabic);
        if ($german === '' || $arabic === '') {
            return null;
        }

        $type = $this->typeForCategory($category);
        $article = $suffix = $note = $meta = null;

        if (preg_match('#^(der/die|die/der|der/das|das/der|der|die|das)\s+(\S.*)$#u', $german, $m)) {
            $article = $m[1];
            $german = $m[2];
            $type = 'noun';
        }
        if ($type === null) {
            $type = $this->looksLikeVerb($german) ? 'verb' : 'noun';
        }

        if ($type === 'noun') {
            if (preg_match('/^(.*\S)\s*,\s*([^,()]{1,24})$/u', $german, $m)) {
                $german = $m[1];
                $suffix = trim($m[2]);
            }
            if (preg_match('/\(Pl\.?\)/iu', $german.' '.($suffix ?? ''))) {
                $note = 'nur Plural (Pl.)';
                $german = $this->clean(preg_replace('/\s*\(Pl\.?\)/iu', '', $german));
                if ($suffix !== null && preg_match('/\(Pl\.?\)/iu', $suffix)) {
                    $suffix = null;
                }
            }
            if ($article === null && $category === 'Freizeit/Kontinente' && $note === null) {
                $note = 'Kontinent';
            }
        } elseif ($type === 'verb') {
            $meta = $this->verbMeta($german);
        } elseif ($type === 'preposition' && preg_match('/^(\S+)\s+\((.+)\)$/u', $german, $m)) {
            $note = $german;
            $german = $m[1];
        }

        $this->words[] = [
            'german' => $german,
            'article' => $article,
            'plural' => null,
            'plural_suffix' => $suffix,
            'arabic' => $arabic,
            'type' => $type,
            'category' => $category,
            'note' => $note,
            'meta' => $meta,
        ];

        return array_key_last($this->words);
    }

    private function typeForCategory(string $category): ?string
    {
        return match ($category) {
            'Verben' => 'verb',
            'Adjektive' => 'adjective',
            'Adverbien' => 'adverb',
            'Fragewörter' => 'question_word',
            'Präpositionen' => 'preposition',
            'Partikeln' => 'particle',
            'Redemittel' => 'expression',
            default => null,
        };
    }

    private function looksLikeVerb(string $german): bool
    {
        $base = $this->verbBase($german);

        return $base !== '' && preg_match('/^[a-zäöüß][a-zäöüß\-]*(en|ern|eln|n)$/u', $base) && ! preg_match('/^(wann|wenn|denn|schon|dann|hinten|oben|unten|drinnen|draußen|gern|morgen|gestern|zusammen|offen)$/u', $base);
    }

    /** "arbeiten (als …)" → "arbeiten", "(sich) vorstellen" → "vorstellen". */
    private function verbBase(string $german): string
    {
        $g = preg_replace('/\([^)]*\)/u', ' ', $german);
        $g = preg_replace('/^\s*sich\s+/u', '', $g);
        $g = trim(preg_replace('/\s+/u', ' ', $g));
        $tokens = explode(' ', $g);
        foreach ($tokens as $t) {
            if (preg_match('/^[a-zäöüß][a-zäöüß\-]*(en|ern|eln|n)$/u', $t)) {
                return $t;
            }
        }

        return $tokens[0] ?? '';
    }

    private function verbMeta(string $german, ?array $forms = null): array
    {
        $base = mb_strtolower($this->verbBase($german));
        $stem = preg_replace('/(en|n)$/u', '', $base);
        $stemRule = preg_match('/[td]$/u', $stem) ? 't/d' : (preg_match('/(s|ß|z|x)$/u', $stem) ? 's/ß/z' : null);
        $modal = in_array($base, self::MODALS, true);

        if ($forms !== null) {
            $regular = $this->regularForms($base);
            $irregular = $modal || $regular === null
                || mb_strtolower($forms['du']) !== $regular['du']
                || mb_strtolower($forms['er_sie_es']) !== $regular['er_sie_es'];
        } else {
            $irregular = $modal || $this->isKnownIrregular($base);
        }

        return ['irregular' => $irregular, 'stem_rule' => $stemRule, 'modal' => $modal];
    }

    private function isKnownIrregular(string $base): bool
    {
        if (in_array($base, self::IRREGULAR, true)) {
            return true;
        }
        foreach (self::IRREGULAR as $irr) {
            if (mb_strlen($irr) >= 4 && mb_strlen($base) > mb_strlen($irr) && str_ends_with($base, $irr)) {
                return true;
            }
        }

        return false;
    }

    private function isSeparable(string $base): bool
    {
        foreach (self::SEPARABLE_PREFIXES as $p) {
            if (str_starts_with($base, $p) && mb_strlen($base) > mb_strlen($p) + 3) {
                return true;
            }
        }

        return false;
    }

    /** Regular present tense of an -en/-eln/-ern verb; null when the infinitive does not fit. */
    public function regularForms(string $base): ?array
    {
        if (! preg_match('/^([a-zäöüß\-]+?)(en|eln|ern)$/u', $base, $m)) {
            return null;
        }
        if ($m[2] === 'en') {
            $stem = $m[1];
            $eInsert = (bool) preg_match('/([td]|[^aeiouäöüyhlrmn][mn])$/u', $stem);
            $sRule = (bool) preg_match('/(s|ß|z|x)$/u', $stem);

            return [
                'ich' => $stem.'e',
                'du' => $stem.($eInsert ? 'est' : ($sRule ? 't' : 'st')),
                'er_sie_es' => $stem.($eInsert ? 'et' : 't'),
                'wir' => $base,
                'ihr' => $stem.($eInsert ? 'et' : 't'),
                'sie_Sie' => $base,
            ];
        }
        $stem = $m[1].substr($m[2], 0, 2);           // klingeln → klingel, wandern → wander

        return [
            'ich' => preg_replace('/e(l|r)$/u', '$1', $stem).'e',
            'du' => $stem.'st',
            'er_sie_es' => $stem.'t',
            'wir' => $base,
            'ihr' => $stem.'t',
            'sie_Sie' => $base,
        ];
    }

    // ------------------------------------------------------------------ Länder – Sprachen – Nationalitäten

    private function isLaenderHeader(Line $line): bool
    {
        if ($line->count() < 2 || ! preg_match('/^Land\b/u', $line->first()->text())) {
            return false;
        }
        foreach ($line->cells as $c) {
            if (preg_match('/^(Sprache|Nationalit)/u', $c->text())) {
                return true;
            }
        }

        return false;
    }

    private function parseLaender(int $i): int
    {
        $lines = $this->doc->lines;
        $n = count($lines);
        $geo = $this->doc->geometry;

        $roles = [];
        foreach ($lines[$i]->cells as $k => $c) {
            $t = mb_strtolower($c->text());
            $roles[$k] = match (true) {
                str_starts_with($t, 'land') => 'country',
                str_starts_with($t, 'sprache') => 'language',
                str_starts_with($t, 'nationalit') => 'nationality',
                default => null,
            };
        }
        $i++;

        $countries = $languages = $nationalities = [];
        for (; $i < $n; $i++) {
            $line = $lines[$i];
            if (array_key_exists($i, $this->levels)) {
                break;
            }
            if ($line->count() === 1) {
                if ($geo->isAtMargin($line->first()->x0)) {
                    break;
                }

                continue;
            }
            if ($this->isLaenderHeader($line)) {
                continue;
            }

            $row = [];
            foreach ($line->cells as $k => $c) {
                $role = $roles[$k] ?? null;
                if ($role === null) {
                    continue;
                }
                $row[$role] = $this->splitPair($c->text());
            }
            if (! isset($row['country']) || $row['country'][0] === '') {
                continue;
            }

            [$countryDe, $countryAr] = $row['country'];
            $lang = $row['language'] ?? null;
            $nat = $row['nationality'] ?? null;
            [$m, $f] = $nat ? $this->splitNationality($nat[0]) : [null, null];

            $countries[] = [
                'german' => $countryDe, 'article' => null, 'plural' => null, 'plural_suffix' => null,
                'arabic' => $countryAr !== '' ? $countryAr : '—', 'type' => 'country', 'category' => self::CAT_LSN,
                'note' => preg_match('/^(der|die|das)\s/u', $countryDe) ? 'mit Artikel: '.$countryDe : null,
                'meta' => array_filter(['language' => $lang[0] ?? null, 'nationality_m' => $m, 'nationality_f' => $f], fn ($v) => $v !== null && $v !== '') ?: null,
            ];
            if ($lang !== null && $lang[0] !== '') {
                $languages[$lang[0]] ??= ['arabic' => $lang[1], 'countries' => []];
                $languages[$lang[0]]['countries'][] = $countryDe;
            }
            if ($nat !== null && $nat[0] !== '') {
                $nationalities[] = [
                    'german' => $nat[0], 'article' => null, 'plural' => null, 'plural_suffix' => null,
                    'arabic' => $nat[1] !== '' ? $nat[1] : '—', 'type' => 'nationality', 'category' => self::CAT_LSN,
                    'note' => null,
                    'meta' => array_filter(['country' => $countryDe, 'm' => $m, 'f' => $f], fn ($v) => $v !== null && $v !== '') ?: null,
                ];
            }
        }

        foreach ($countries as $w) {
            $this->words[] = $w;
        }
        foreach ($languages as $name => $l) {
            $this->words[] = [
                'german' => $name, 'article' => null, 'plural' => null, 'plural_suffix' => null,
                'arabic' => $l['arabic'] !== '' ? $l['arabic'] : '—', 'type' => 'language', 'category' => self::CAT_LSN,
                'note' => count($l['countries']) > 1 ? implode(', ', array_unique($l['countries'])) : null, 'meta' => null,
            ];
        }
        foreach ($nationalities as $w) {
            $this->words[] = $w;
        }

        return $i;
    }

    /** "Argentinien – الأرجنتين" → ["Argentinien", "الأرجنتين"] */
    private function splitPair(string $text): array
    {
        $text = $this->clean($text);
        $parts = preg_split('/\s+[–\-]\s+/u', $text, 2);
        if (count($parts) === 2) {
            return [$this->clean($parts[0]), $this->clean($parts[1])];
        }

        return preg_match('/\p{Arabic}/u', $text) && ! preg_match('/\p{Latin}/u', $text) ? ['', $text] : [$text, ''];
    }

    /** "der/die Argentinier/in" → ["der Argentinier", "die Argentinierin"]; "der Pole / die Polin" → both. */
    private function splitNationality(string $s): array
    {
        $s = $this->clean($s);
        if (preg_match('#^(der\s+\S.*?)\s*/\s*(die\s+\S.*)$#u', $s, $m)) {
            return [trim($m[1]), trim($m[2])];
        }
        if (preg_match('#^der/die\s+(.+?)/in$#u', $s, $m)) {
            return ['der '.$m[1], 'die '.$m[1].'in'];
        }
        if (preg_match('#^der/die\s+(.+)$#u', $s, $m)) {
            return ['der '.$m[1], 'die '.$m[1]];
        }

        return [$s, null];
    }

    // ------------------------------------------------------------------ dialog table

    private function isDialogHeader(Line $line): bool
    {
        return preg_match('/^(Thema|Situation)\b/u', $line->first()->text()) && ($line->count() >= 2 || $line->isBold());
    }

    private function parseDialog(int $i): int
    {
        $lines = $this->doc->lines;
        $n = count($lines);
        $geo = $this->doc->geometry;

        $starts = [];
        $hasArabicCol = false;
        $headerLines = 0;
        while ($i < $n && $headerLines < 3 && $this->isDialogHeaderLine($lines[$i])) {
            foreach ($lines[$i]->cells as $c) {
                if ($c->isArabicDominant() || $c->isRightAligned()) {
                    $hasArabicCol = true;
                } else {
                    $starts[] = $c->x0;
                }
            }
            $i++;
            $headerLines++;
        }
        $starts = $this->clusterPositions($starts, 4.0);
        if (count($starts) < 2) {
            $this->warn('Dialogtabelle ohne erkennbare Spalten übersprungen.');

            return $i;
        }
        $arabicCol = count($starts);

        $rows = [];
        for (; $i < $n; $i++) {
            $line = $lines[$i];
            if (array_key_exists($i, $this->levels)) {
                break;
            }
            if ($line->count() === 1 && $geo->isAtMargin($line->first()->x0) && ! $line->first()->bold) {
                break;
            }
            if ($this->pronounKey($line->first()->text()) !== null) {
                break;
            }

            $assigned = [];
            foreach ($this->splitAtColumns($line->cells, $starts) as $c) {
                $col = ($hasArabicCol && $c->isArabicDominant()) ? $arabicCol : $this->columnIndex($c->x0, $starts);
                $assigned[$col][] = $c->text();
            }
            if (isset($assigned[0]) && trim(implode('', $assigned[0])) !== '') {
                $rows[] = $assigned;
            } elseif ($rows !== []) {
                $last = count($rows) - 1;
                foreach ($assigned as $col => $texts) {
                    foreach ($texts as $t) {
                        $rows[$last][$col][] = $t;
                    }
                }
            }
        }

        foreach ($rows as $r) {
            $topic = $this->clean(implode(' ', $r[0]));
            $arabic = $this->clean(implode(' ', $r[$arabicCol] ?? []));
            $middle = [];
            for ($k = 1; $k < $arabicCol; $k++) {
                $middle[] = $this->clean(implode(' ', $r[$k] ?? []));
            }
            $question = count($middle) >= 2 ? $middle[0] : null;
            $answer = count($middle) >= 2 ? $middle[1] : ($middle[0] ?? '');
            if ($answer === '' && $question !== null && $question !== '') {
                $answer = $question;
                $question = null;
            }
            if ($answer === '' || $arabic === '') {
                $this->warn('Dialogzeile unvollständig übersprungen: '.$topic.' – '.($answer ?: $question ?: ''));

                continue;
            }
            [$formal, $informal] = $question !== null && $question !== '' ? $this->splitFormality($question) : [null, null];
            $this->dialog[] = [
                'topic' => $topic !== '' ? $topic : 'Dialog',
                'question_formal' => $formal,
                'question_informal' => $informal,
                'answer' => $answer,
                'arabic' => $arabic,
            ];
        }

        return $i;
    }

    private function isDialogHeaderLine(Line $line): bool
    {
        if ($line->isBold()) {
            return true;
        }
        foreach ($line->cells as $c) {
            if (preg_match('/^(Thema|Situation|Person\s*[AB]?|Frage|Antwort|Deutsch|العربية|السؤال|الجواب)/u', $c->text())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cells that run across a column start (two table cells touching) are cut at that start;
     * a Latin cell glued to the Arabic cell of its row is cut before the Arabic run.
     *
     * @param  Cell[]  $cells
     * @param  float[]  $starts
     * @return Cell[]
     */
    private function splitAtColumns(array $cells, array $starts): array
    {
        $out = [];
        $work = array_values($cells);
        while ($work !== []) {
            $c = array_shift($work);
            $done = false;
            foreach (array_slice($starts, 1) as $s) {
                if ($c->x0 < $s - 3 && $c->x1 > $s + 3) {
                    $x = $c->wordStartNear($s);
                    if ($x !== null) {
                        $parts = $c->splitAtX($x);
                        if (count($parts) === 2) {
                            array_unshift($work, ...$parts);
                            $done = true;
                            break;
                        }
                    }
                }
            }
            if ($done) {
                continue;
            }
            if (! $c->isArabicDominant() && $c->hasArabic() && $c->hasLatin()) {
                $parts = $c->splitBeforeArabicTail();
                if ($parts !== null) {
                    array_unshift($work, ...$parts);

                    continue;
                }
            }
            $out[] = $c;
        }
        usort($out, fn (Cell $a, Cell $b) => $a->x0 <=> $b->x0);

        return $out;
    }

    /** @param  float[]  $starts  sorted */
    private function columnIndex(float $x0, array $starts): int
    {
        $col = 0;
        foreach ($starts as $k => $s) {
            if ($s <= $x0 + 3) {
                $col = $k;
            }
        }

        return $col;
    }

    /** "Wie heißen Sie? / Wie heißt du?" or "Woher kommen Sie / kommst du?" → [formal, informal]. */
    private function splitFormality(string $q): array
    {
        $q = $this->clean($q);
        $parts = preg_split('#\s*/\s*#u', $q);
        if (count($parts) === 2 && preg_match('/[?!.]/', $parts[0]) && preg_match('/[?!.]/', $parts[1])) {
            return [trim($parts[0]), trim($parts[1])];
        }
        $verbPair = '/(\S+)\s+(Sie|Ihnen)\s*\/\s*(\S+)\s+(du|dir|dich)\b/u';
        if (preg_match($verbPair, $q)) {
            return [preg_replace($verbPair, '$1 $2', $q), preg_replace($verbPair, '$3 $4', $q)];
        }
        $pronounPair = '/\b(Sie|Ihnen|Ihre?[nms]?)\s*\/\s*(du|dir|dich|deine?[nms]?|euch|eure?[nms]?)\b/u';
        if (preg_match($pronounPair, $q)) {
            return [preg_replace($pronounPair, '$1', $q), preg_replace($pronounPair, '$2', $q)];
        }
        if (preg_match('/\b(Sie|Ihnen|Ihre?[nms]?)\b/u', $q)) {
            return [$q, null];
        }
        if (preg_match('/\b(du|dir|dich|deine?[nms]?)\b/u', $q)) {
            return [null, $q];
        }

        return [$q, null];
    }

    private function finalizeDialogTopics(): void
    {
        $counts = [];
        foreach ($this->dialog as $d) {
            $counts[$d['topic']] = ($counts[$d['topic']] ?? 0) + 1;
        }
        foreach ($this->dialog as &$d) {
            if (($counts[$d['topic']] ?? 0) < 2 && $d['topic'] !== 'Dialog') {
                continue;
            }
            $haystack = ($d['question_formal'] ?? '').' '.($d['question_informal'] ?? '').' '.$d['answer'];
            foreach (self::TOPIC_RULES as $re => $topic) {
                if (preg_match($re, $haystack)) {
                    $d['topic'] = $topic;
                    break;
                }
            }
        }
        unset($d);
    }

    // ------------------------------------------------------------------ grammar blocks & conjugation tables

    /** A run of multi-cell lines: conjugation tables are extracted, everything is rendered as Markdown. */
    private function parseTableBlock(int $i): int
    {
        $lines = $this->doc->lines;
        $n = count($lines);
        $block = [];
        for (; $i < $n; $i++) {
            if (array_key_exists($i, $this->levels) || $lines[$i]->count() < 2) {
                break;
            }
            $block[] = $lines[$i];
        }

        $pronounRows = array_filter($block, fn (Line $l) => $this->pronounKey($l->first()->text()) !== null);
        if (count($pronounRows) >= 3) {
            $this->parseConjugationTables($block);
        } else {
            $this->section['body'][] = $this->markdownTable($block);
        }

        return $i;
    }

    /** @param  Line[]  $block */
    private function parseConjugationTables(array $block): void
    {
        $tables = [];
        $current = null;
        foreach ($block as $line) {
            $key = $this->pronounKey($line->first()->text());
            if ($key === null) {
                if ($current !== null) {
                    $tables[] = $current;
                }
                $current = ['header' => $line, 'rows' => []];
            } else {
                $current ??= ['header' => null, 'rows' => []];
                $current['rows'][$key] = $line;
            }
        }
        if ($current !== null) {
            $tables[] = $current;
        }

        foreach ($tables as $table) {
            $md = $this->extractConjugationTable($table['header'], $table['rows']);
            $this->section['body'][] = $md;
        }
    }

    /**
     * @param  array<string, Line>  $rows  pronoun key => line
     */
    private function extractConjugationTable(?Line $header, array $rows): string
    {
        // Columns from the form cells of all pronoun rows.
        $positions = [];
        foreach ($rows as $line) {
            foreach (array_slice($line->cells, 1) as $c) {
                $positions[] = $c->isRightAligned() ? PHP_FLOAT_MAX : $c->x0;
            }
        }
        $hasRight = in_array(PHP_FLOAT_MAX, $positions, true);
        $centers = $this->clusterPositions(array_filter($positions, fn ($p) => $p !== PHP_FLOAT_MAX), 10.0);
        $columnCount = count($centers) + ($hasRight ? 1 : 0);

        // Verbs from the header, in visual order.
        $verbs = [];
        if ($header !== null) {
            $cells = $header->cells;
            if ($this->pronounKey($cells[0]->text()) !== null || preg_match('/^(Verb|Infinitiv)/iu', $cells[0]->text())) {
                $cells = array_slice($cells, 1);
            }
            foreach ($cells as $c) {
                $t = $this->clean($c->text());
                if (preg_match_all('/([\p{Latin}][\p{Latin}\-]*(?:\s+[\p{Latin}][\p{Latin}\-]*)?)\s*\(([^()]*)\)/u', $t, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $m) {
                        $verbs[] = ['german' => trim($m[1]), 'arabic' => $this->clean($m[2]) ?: null];
                    }
                } elseif ($t !== '') {
                    $verbs[] = ['german' => $t, 'arabic' => null];
                }
            }
        }

        $forms = array_fill(0, $columnCount, []);
        foreach ($rows as $key => $line) {
            foreach (array_slice($line->cells, 1) as $c) {
                $col = $c->isRightAligned() ? $columnCount - 1 : $this->nearestIndex($c->x0, $centers);
                $forms[$col][$key] = $this->clean($c->text());
            }
        }

        $extracted = count($verbs) === $columnCount;
        if (! $extracted) {
            $this->warn(sprintf('Konjugationstabelle: %d Verben, aber %d Spalten – Formen nicht übernommen (%s).', count($verbs), $columnCount, implode(', ', array_column($verbs, 'german'))));
        }

        $sectionTitle = $this->section ? $this->germanPart($this->section['title']) : null;
        foreach ($forms as $col => $f) {
            if (! $extracted) {
                break;
            }
            $missing = array_diff(self::PRONOUN_KEYS, array_keys(array_filter($f, fn ($v) => $v !== '')));
            if ($missing !== []) {
                $this->warn(sprintf('Konjugation %s unvollständig (fehlt: %s).', $verbs[$col]['german'], implode(', ', $missing)));

                continue;
            }
            $inf = mb_strtolower($this->verbBase($verbs[$col]['german'])) ?: mb_strtolower($verbs[$col]['german']);
            $this->tableVerbs[$inf] ??= ['arabic' => $verbs[$col]['arabic'], 'forms' => $f, 'section' => $sectionTitle];
        }

        // Markdown rendering.
        $head = [''];
        for ($col = 0; $col < $columnCount; $col++) {
            $v = $verbs[$col] ?? null;
            $head[] = $v ? $v['german'].($v['arabic'] ? ' ('.$v['arabic'].')' : '') : '';
        }
        $md = '| '.implode(' | ', $head)." |\n|".str_repeat('---|', $columnCount + 1)."\n";
        foreach (self::PRONOUN_KEYS as $key) {
            if (! isset($rows[$key])) {
                continue;
            }
            $cells = [str_replace('_', '/', $key === 'sie_Sie' ? 'sie/Sie' : $key)];
            for ($col = 0; $col < $columnCount; $col++) {
                $cells[] = $forms[$col][$key] ?? '';
            }
            $md .= '| '.implode(' | ', $cells)." |\n";
        }

        return rtrim($md);
    }

    /** @param  Line[]  $block */
    private function markdownTable(array $block): string
    {
        $positions = [];
        $hasRight = false;
        foreach ($block as $line) {
            foreach ($line->cells as $c) {
                if ($c->isRightAligned()) {
                    $hasRight = true;
                } else {
                    $positions[] = $c->x0;
                }
            }
        }
        $centers = $this->clusterPositions($positions, 10.0);
        $columnCount = count($centers) + ($hasRight ? 1 : 0);
        if ($columnCount === 0) {
            return '';
        }

        $rows = [];
        foreach ($block as $line) {
            $row = array_fill(0, $columnCount, '');
            foreach ($line->cells as $c) {
                $col = $c->isRightAligned() ? $columnCount - 1 : $this->nearestIndex($c->x0, $centers);
                $row[$col] = trim($row[$col].' '.$this->clean($c->text()));
            }
            $rows[] = $row;
        }

        $md = '| '.implode(' | ', array_map(fn ($v) => str_replace('|', '\\|', $v), $rows[0]))." |\n|".str_repeat('---|', $columnCount)."\n";
        foreach (array_slice($rows, 1) as $row) {
            $md .= '| '.implode(' | ', array_map(fn ($v) => str_replace('|', '\\|', $v), $row))." |\n";
        }

        return rtrim($md);
    }

    private function paragraph(Line $line): string
    {
        $text = $this->clean($line->text(' '));
        $geo = $this->doc->geometry;
        if (! $geo->isAtMargin($line->first()->x0) && $line->first()->x0 > $geo->leftMargin + 8) {
            return '- '.$text;
        }

        return $text;
    }

    private function flushSection(): void
    {
        if ($this->section === null) {
            return;
        }
        $body = array_values(array_filter($this->section['body'], fn ($b) => trim((string) $b) !== ''));
        // Drop trailing sub-headers that have no content after them.
        while ($body !== [] && preg_match('/^\*\*.*\*\*$/su', end($body))) {
            array_pop($body);
        }
        $content = array_filter($body, fn ($b) => ! preg_match('/^\*\*.*\*\*$/su', $b));
        if ($content !== []) {
            $title = preg_replace('/^Grammatik\s*:\s*/u', '', $this->section['title']);
            if ($this->section['table']) {
                $title = 'Hinweise: '.$title;
            }
            $this->notes[] = ['title' => mb_substr($title, 0, 255), 'body_md' => implode("\n\n", $body)];
        }
        $this->section = null;
    }

    // ------------------------------------------------------------------ verbs

    private function finalizeVerbs(): void
    {
        foreach ($this->tableVerbs as $inf => $tv) {
            $idx = $this->findVerbIndex($inf);
            $meta = $this->verbMeta($inf, $tv['forms']);
            if ($idx === null) {
                $this->words[] = [
                    'german' => $inf, 'article' => null, 'plural' => null, 'plural_suffix' => null,
                    'arabic' => $tv['arabic'] ?? '—', 'type' => 'verb', 'category' => 'Verben',
                    'note' => $this->verbNote($meta, $tv['forms']) ?? $tv['section'], 'meta' => $meta,
                ];
                $idx = array_key_last($this->words);
            } else {
                $this->words[$idx]['meta'] = $meta;
                $this->words[$idx]['note'] ??= $this->verbNote($meta, $tv['forms']);
            }
            $this->tableVerbs[$inf]['german'] = $this->words[$idx]['german'];
        }

        // Regular verbs from the word lists without a table get a generated conjugation.
        $covered = array_map(fn ($tv) => mb_strtolower($tv['german']), $this->tableVerbs);
        foreach ($this->words as $w) {
            if ($w['type'] !== 'verb' || in_array(mb_strtolower($w['german']), $covered, true)) {
                continue;
            }
            $base = mb_strtolower($this->verbBase($w['german']));
            if (($w['meta']['irregular'] ?? false) || $this->isSeparable($base) || preg_match('/^\(?sich\b/u', $w['german'])) {
                continue;
            }
            $forms = $this->regularForms($base);
            if ($forms === null) {
                continue;
            }
            $this->tableVerbs['__gen_'.$base] = ['arabic' => null, 'forms' => $forms, 'section' => null, 'german' => $w['german']];
        }
    }

    private function findVerbIndex(string $infinitive): ?int
    {
        foreach ($this->words as $i => $w) {
            if ($w['type'] === 'verb' && mb_strtolower($this->verbBase($w['german'])) === $infinitive) {
                return $i;
            }
        }

        return null;
    }

    private function verbNote(array $meta, array $forms): ?string
    {
        if ($meta['modal']) {
            return sprintf('Modalverb: ich %s, du %s, er %s', $forms['ich'], $forms['du'], $forms['er_sie_es']);
        }
        if ($meta['irregular']) {
            return sprintf('unregelmäßig: du %s, er %s', $forms['du'], $forms['er_sie_es']);
        }

        return match ($meta['stem_rule']) {
            't/d' => sprintf('Stamm auf -t/-d: du %s, er %s', $forms['du'], $forms['er_sie_es']),
            's/ß/z' => sprintf('Stamm auf -s/-ß/-z: du %s (nicht %sst)', $forms['du'], preg_replace('/(st|t)$/u', '', $forms['du'])),
            default => null,
        };
    }

    private function conjugations(): array
    {
        $out = [];
        foreach ($this->tableVerbs as $tv) {
            $out[] = ['german' => $tv['german']] + array_intersect_key($tv['forms'], array_flip(self::PRONOUN_KEYS));
        }

        return $out;
    }

    // ------------------------------------------------------------------ helpers

    private function pronounKey(string $text): ?string
    {
        $t = mb_strtolower(preg_replace('/\s*\/\s*/u', '/', trim($text)));
        $t = rtrim($t, ':');

        return match ($t) {
            'ich' => 'ich',
            'du' => 'du',
            'er/sie/es', 'er/es/sie', 'er', 'sie/er/es' => 'er_sie_es',
            'wir' => 'wir',
            'ihr' => 'ihr',
            'sie/sie', 'sie' => 'sie_Sie',
            default => null,
        };
    }

    /** @param  float[]  $positions */
    private function clusterPositions(array $positions, float $tolerance): array
    {
        $positions = array_values($positions);
        sort($positions);
        $clusters = [];
        foreach ($positions as $p) {
            if ($clusters !== [] && $p - end($clusters)['last'] <= $tolerance) {
                $k = count($clusters) - 1;
                $clusters[$k]['last'] = $p;
                $clusters[$k]['sum'] += $p;
                $clusters[$k]['n']++;
            } else {
                $clusters[] = ['last' => $p, 'sum' => $p, 'n' => 1];
            }
        }

        return array_map(fn ($c) => $c['sum'] / $c['n'], $clusters);
    }

    /** @param  float[]  $centers */
    private function nearestIndex(float $x, array $centers): int
    {
        $best = 0;
        $bestDist = INF;
        foreach ($centers as $i => $c) {
            $d = abs($x - $c);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $i;
            }
        }

        return $best;
    }

    private function clean(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = preg_replace('/\s+([,.;:!?؟،])/u', '$1', $s);

        return trim($s);
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
