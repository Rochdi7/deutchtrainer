<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\Word;

/**
 * Shared clean-up for import payloads, whichever engine produced them:
 * light coercion so near-misses pass the schema, de-duplication, plural expansion
 * and category label normalisation.
 */
class PayloadPostProcessor
{
    /** Light normalisation so near-misses (null strings, missing keys, German type names) pass the schema. */
    public function coerce(array $json): array
    {
        $json += ['words' => [], 'conjugations' => [], 'dialog_lines' => [], 'grammar_notes' => []];

        $json['words'] = array_values(array_filter(array_map(function ($w) {
            if (! is_array($w)) {
                return null;
            }
            $w += ['article' => null, 'plural' => null, 'plural_suffix' => null, 'example_de' => null, 'example_ar' => null, 'note' => null, 'meta' => null, 'category' => 'Nomen', 'type' => 'noun'];
            foreach (['german', 'arabic', 'article', 'plural', 'plural_suffix', 'example_de', 'example_ar', 'note', 'category'] as $k) {
                if (isset($w[$k]) && ! is_string($w[$k])) {
                    $w[$k] = is_scalar($w[$k]) ? (string) $w[$k] : null;
                }
                if (isset($w[$k]) && $w[$k] !== null) {
                    $w[$k] = trim($w[$k]);
                    if ($w[$k] === '' && ! in_array($k, ['german', 'arabic', 'category'])) {
                        $w[$k] = null;
                    }
                }
            }
            if (($w['category'] ?? '') === '') {
                $w['category'] = 'Nomen';
            }
            if (! in_array($w['type'], Word::TYPES, true)) {
                $w['type'] = match (mb_strtolower((string) $w['type'])) {
                    'nomen', 'substantiv' => 'noun',
                    'verb', 'verben' => 'verb',
                    'adjektiv', 'adj' => 'adjective',
                    'adverb' => 'adverb',
                    'fragewort' => 'question_word',
                    'präposition' => 'preposition',
                    'partikel' => 'particle',
                    'redemittel', 'ausdruck', 'phrase' => 'expression',
                    'land' => 'country',
                    'sprache' => 'language',
                    'nationalität' => 'nationality',
                    default => 'expression',
                };
            }
            if (array_key_exists('meta', $w) && $w['meta'] !== null && ! is_array($w['meta'])) {
                $w['meta'] = null;
            }
            if (is_array($w['meta']) && $w['meta'] === []) {
                $w['meta'] = null;
            }

            return array_intersect_key($w, array_flip(['german', 'article', 'plural', 'plural_suffix', 'arabic', 'type', 'category', 'example_de', 'example_ar', 'note', 'meta']));
        }, $json['words'] ?? [])));

        foreach (['conjugations', 'dialog_lines', 'grammar_notes'] as $k) {
            $json[$k] = array_values(array_filter($json[$k] ?? [], 'is_array'));
        }

        return array_intersect_key($json, array_flip(['words', 'conjugations', 'dialog_lines', 'grammar_notes']));
    }

    /** Dedupe, derive plural forms where only the suffix was given, normalise category labels. */
    public function process(array $merged): array
    {
        $merged += ['words' => [], 'conjugations' => [], 'dialog_lines' => [], 'grammar_notes' => []];

        $seen = [];
        $merged['words'] = array_values(array_filter($merged['words'], function (array $w) use (&$seen) {
            $key = mb_strtolower(trim($w['german'])).'|'.$w['type'];
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));

        $merged['words'] = array_map(function (array $w) {
            if ($w['type'] === 'noun' && empty($w['plural']) && ! empty($w['plural_suffix'])) {
                $w['plural'] = PluralExpander::expand($w['german'], $w['plural_suffix']);
            }
            if (! in_array($w['category'], Category::CANONICAL, true)) {
                // Known topics get their canonical name; a new topic from the PDF keeps its own label.
                $w['category'] = PluralExpander::matchCategory($w['category'])
                    ?? (trim($w['category']) !== '' ? trim($w['category']) : PluralExpander::mapCategory('', $w['type']));
            }

            return $w;
        }, $merged['words']);

        $seenC = [];
        $merged['conjugations'] = array_values(array_filter($merged['conjugations'], function (array $c) use (&$seenC) {
            $key = mb_strtolower(trim($c['german']));
            if (isset($seenC[$key])) {
                return false;
            }
            $seenC[$key] = true;

            return true;
        }));

        return $merged;
    }
}
