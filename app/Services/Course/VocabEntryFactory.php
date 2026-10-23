<?php

namespace App\Services\Course;

/**
 * Turns a textbook-style vocabulary entry ("die Handynummer, -n", "das Fleisch (nur Sg.)",
 * "(sich) vorstellen", "fit sein") into the word payload used by the import persister.
 * Shared by the course content loader and (via the same conventions) the PDF importer.
 */
class VocabEntryFactory
{
    private const MODALS = ['können', 'müssen', 'wollen', 'dürfen', 'sollen', 'mögen', 'möchten', 'möcht-'];

    private const IRREGULAR = [
        'sein', 'haben', 'werden', 'wissen', 'tun', 'sprechen', 'lesen', 'essen', 'sehen', 'fahren', 'schlafen',
        'nehmen', 'geben', 'treffen', 'helfen', 'laufen', 'lassen', 'tragen', 'waschen', 'fallen', 'halten',
        'gefallen', 'vergessen', 'empfehlen', 'sterben', 'werfen', 'brechen', 'fangen', 'schlagen', 'wachsen',
        'raten', 'braten', 'laden', 'stehlen', 'befehlen', 'erschrecken', 'gelten', 'messen', 'treten',
        'geschehen', 'blasen', 'graben', 'stoßen', 'backen', 'fressen', 'schmelzen', 'verderben', 'mögen',
        'möchten', 'dürfen', 'können', 'müssen', 'wollen', 'sollen', 'erziehen', 'anfangen', 'aussehen',
        'einladen', 'anstoßen', 'festhalten', 'mitnehmen', 'einlaufen', 'abbiegen', 'aussteigen', 'umsteigen',
        'erhalten', 'entlassen', 'mitfahren', 'wegfahren', 'weiterfahren', 'mitbringen', 'bringen', 'denken',
        'bleiben', 'sitzen', 'stehen', 'liegen', 'hängen', 'verbringen', 'hinterlassen', 'ausgehen', 'zurückgehen',
        'bekommen', 'beschreiben', 'schreiben', 'gehen', 'kommen', 'finden', 'stattfinden', 'anrufen', 'zurückrufen',
        'mitkommen', 'aufstehen', 'trinken', 'beginnen', 'verstehen', 'heißen', 'reiten', 'schwimmen', 'rudern',
    ];

    /**
     * @param  array{0:string,1:string,2:string,3?:array}  $item  [german, english, arabic, options]
     * @return array<string, mixed> word payload (schema of resources/schemas/import.schema.json + english/image/source_page/tags)
     */
    public function make(array $item, string $category, ?string $groupType = null, ?int $page = null): array
    {
        [$raw, $english, $arabic] = $item;
        $opt = $item[3] ?? [];
        $raw = trim(preg_replace('/\s+/u', ' ', $raw));

        $article = null;
        $suffix = null;
        $note = $opt['note'] ?? null;
        $german = $raw;
        $type = $opt['type'] ?? $groupType;

        // Plural-only or singular-only markers in parentheses.
        $onlyPlural = (bool) preg_match('/\((?:nur |meist )?Pl\.?\)/u', $raw);
        $onlySingular = (bool) preg_match('/\((?:hier )?(?:nur |meist )?Sg\.?\)/u', $raw);
        $german = trim(preg_replace('/\s*\((?:hier )?(?:nur |meist |kein )?(?:Pl|Sg)\.?\)/u', '', $german));

        if (preg_match('#^(der/die|die/der|der/das|der|die|das)\s+(.+)$#u', $german, $m)) {
            $article = $m[1];
            $german = $m[2];
            $type ??= 'noun';
        } elseif (preg_match('#^(der|die|das)\s+\S+\s*/\s*(der|die|das)\s+#u', $german)) {
            $type ??= 'noun';
        }

        if ($type === null) {
            $type = $this->looksLikeVerb($german) ? 'verb' : (preg_match('/\s/u', $german) ? 'expression' : 'noun');
        }

        if ($type === 'noun') {
            // "Handynummer, -n", "Studienfach, ¨er", "Museum, Museen", "Juni, -s (Pl. selten)"
            if (preg_match('/^(.*?\S)\s*,\s*([^,()]+?)(\s*\(.*\))?$/u', $german, $m)) {
                $german = trim($m[1]).($m[3] ?? '');
                $suffix = $this->normaliseSuffix(trim($m[2]));
            }
            if ($onlyPlural) {
                $note = $note ? $note.'; nur Plural' : 'nur Plural (Pl.)';
            } elseif ($onlySingular) {
                $note = $note ? $note.'; nur Singular' : 'nur Singular (Sg.)';
            }
            $german = trim(preg_replace('/\s*\(Pl\. selten\)/u', '', $german));
        }

        $meta = $opt['meta'] ?? null;
        if ($type === 'verb') {
            $meta = $this->verbMeta($german, $meta);
        }

        $word = [
            'german' => $german,
            'article' => $article,
            'plural' => null,
            'plural_suffix' => $suffix,
            'arabic' => trim($arabic),
            'english' => trim($english),
            'type' => $type,
            'category' => $category,
            'example_de' => $opt['ex'][0] ?? null,
            'example_en' => $opt['ex'][1] ?? null,
            'example_ar' => $opt['ex_ar'] ?? null,
            'note' => $note,
            'meta' => $meta,
            'image' => isset($opt['img']) ? $this->imagePath($opt['img']) : null,
            'source_page' => $opt['page'] ?? $page,
            'tags' => $opt['tags'] ?? null,
        ];

        if ($onlyPlural && $type === 'noun') {
            $word['plural'] = $german;
        }

        return $word;
    }

    /**
     * "1f4f1" (Twemoji code point) -> images/vocab/1f4f1.svg
     * "tabler:building-bank" (Iconify set:name, see scripts/vocab-icons.mjs) -> images/vocab/tabler-building-bank.svg
     */
    public function imagePath(string $ref): string
    {
        $ref = strtolower(trim($ref));
        if (str_contains($ref, ':')) {
            [$set, $name] = explode(':', $ref, 2);

            return 'images/vocab/'.$set.'-'.$name.'.svg';
        }

        return 'images/vocab/'.$ref.'.svg';
    }

    /** "-n" → "-n", "¨e" → "¨-e", "Museen" → "Museen" (full form), "-" → "-" */
    private function normaliseSuffix(string $s): string
    {
        $s = str_replace(['"', '‟', '¨-'], ['¨', '¨', '¨'], $s);
        if (preg_match('/^¨(?!-)(\S*)$/u', $s, $m)) {
            return '¨-'.$m[1];
        }

        return $s;
    }

    public function looksLikeVerb(string $german): bool
    {
        $base = $this->verbBase($german);

        return $base !== '' && (bool) preg_match('/^[a-zäöüß][a-zäöüß\-]*(en|ern|eln|n)$/u', $base)
            && ! preg_match('/^(wann|wenn|denn|schon|dann|hinten|oben|unten|drinnen|draußen|gern|morgen|gestern|zusammen|offen|allein|einzeln)$/u', $base);
    }

    public function verbBase(string $german): string
    {
        $g = preg_replace('/\([^)]*\)/u', ' ', $german);
        $g = preg_replace('/^\s*sich\s+/u', '', $g);
        $g = trim(preg_replace('/\s+/u', ' ', $g));
        foreach (explode(' ', $g) as $t) {
            if (preg_match('/^[a-zäöüß][a-zäöüß\-]*(en|ern|eln|n)$/u', $t)) {
                return $t;
            }
        }

        return explode(' ', $g)[0] ?? '';
    }

    private function verbMeta(string $german, ?array $meta): array
    {
        $base = mb_strtolower($this->verbBase($german));
        $stem = preg_replace('/(en|n)$/u', '', $base);
        $stemRule = preg_match('/[td]$/u', $stem) ? 't/d' : (preg_match('/(s|ß|z|x)$/u', $stem) ? 's/ß/z' : null);
        $modal = in_array($base, self::MODALS, true) || in_array($german, self::MODALS, true);
        $irregular = $modal || in_array($base, self::IRREGULAR, true);
        if (! $irregular) {
            foreach (self::IRREGULAR as $irr) {
                if (mb_strlen($irr) >= 4 && mb_strlen($base) > mb_strlen($irr) && str_ends_with($base, $irr)) {
                    $irregular = true;
                    break;
                }
            }
        }
        $separable = (bool) preg_match('/^(ab|an|auf|aus|bei|ein|fern|fest|frei|her|hin|los|mit|nach|statt|teil|vor|weg|zu|zurück|zusammen|runter|um|weiter|liegen|dabei)[a-zäöüß]{3,}$/u', $base);

        return array_merge(
            ['irregular' => $irregular, 'stem_rule' => $stemRule, 'modal' => $modal, 'separable' => $separable],
            $meta ?? []
        );
    }
}
