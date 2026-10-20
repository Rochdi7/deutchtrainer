<?php

return [
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => '2023-06-01',
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 16000),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 300),
    ],

    'import' => [
        // pdftotext binary; leave empty to rely on PATH.
        'pdftotext_path' => env('PDFTOTEXT_PATH', ''),
        'chunk_lines' => (int) env('IMPORT_CHUNK_LINES', 120),
        'disk' => 'pdfs',
        'pdf_dir' => '',
    ],

    'quiz' => [
        'questions_per_session' => 20,
        'pass_threshold' => 0.8,
        // Mix ratio in percent. Must sum to 100.
        'ratios' => [
            'ar_to_de' => 40,
            'de_to_ar' => 25,
            'article' => 15,
            'conjugation' => 10,
            'typing' => 10,
        ],
        // Probability that an "article" slot becomes a "plural" question when the noun has a plural.
        'plural_share' => 0.33,
        // Max dialog questions per session (replaces de_to_ar slots) when the pool has dialog lines.
        'dialog_max' => 2,
        // Weight multiplier for irregular verbs in conjugation questions.
        'irregular_weight' => 3,
        // Global mode mix.
        'global_mix' => ['due' => 0.5, 'random' => 0.3, 'last_lektion' => 0.2],
    ],
];
