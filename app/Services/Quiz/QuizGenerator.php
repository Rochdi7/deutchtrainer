<?php

namespace App\Services\Quiz;

use App\Models\Conjugation;
use App\Models\DialogLine;
use App\Models\Lektion;
use App\Models\User;
use App\Models\UserWordProgress;
use App\Models\Word;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QuizGenerator
{
    public const TYPES = ['ar_to_de', 'de_to_ar', 'article', 'plural', 'conjugation', 'typing', 'dialog'];

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('trainer.quiz');
    }

    // ---------------------------------------------------------------- modes

    public function forLektion(User $user, Lektion $lektion, ?int $count = null): array
    {
        $words = $lektion->words()->with('conjugation')->get()->shuffle();
        $dialog = $lektion->dialogLines()->get();

        return $this->build($words, $count ?? $this->count(), $dialog, $words);
    }

    /** 50 % due (next_review_at <= now or level <= 1, incl. never seen), 30 % random, 20 % last Lektion. */
    public function global(User $user, ?int $count = null): array
    {
        $count ??= $this->count();
        $all = Word::with('conjugation')->get();
        if ($all->isEmpty()) {
            return [];
        }

        $progress = UserWordProgress::where('user_id', $user->id)->get()->keyBy('word_id');
        $lastLektionId = Lektion::orderByDesc('number')->value('id');
        $mix = $this->config['global_mix'];

        $due = $all->filter(function (Word $w) use ($progress) {
            $p = $progress->get($w->id);

            return $p === null || $p->level <= 1 || ($p->next_review_at && $p->next_review_at->lte(now()));
        })->shuffle();
        $last = $all->where('lektion_id', $lastLektionId)->shuffle();
        $random = $all->shuffle();

        $picked = collect();
        $take = function (Collection $from, int $n) use (&$picked) {
            foreach ($from as $w) {
                if ($n <= 0) {
                    break;
                }
                if (! $picked->has($w->id)) {
                    $picked->put($w->id, $w);
                    $n--;
                }
            }
        };

        $take($due, (int) round($count * $mix['due']));
        $take($last, (int) round($count * $mix['last_lektion']));
        $take($random, $count - $picked->count());

        $dialog = DialogLine::whereIn('lektion_id', $picked->pluck('lektion_id')->unique())->get();

        return $this->build($picked->values()->shuffle(), $count, $dialog, $all);
    }

    /** Only words the user has answered wrong more often than right. */
    public function weak(User $user, ?int $count = null): array
    {
        $ids = UserWordProgress::where('user_id', $user->id)
            ->whereColumn('wrong_count', '>', 'correct_count')
            ->orderByRaw('(wrong_count - correct_count) DESC')
            ->pluck('word_id');

        $words = Word::with('conjugation')->whereIn('id', $ids)->get()->shuffle();

        return $this->build($words, $count ?? $this->count(), collect(), Word::with('conjugation')->get());
    }

    /** Explicit list (retry wrong words, "practice these"). */
    public function forWords(User $user, Collection|array $wordIds, ?int $count = null): array
    {
        $words = Word::with('conjugation')->whereIn('id', collect($wordIds))->get()->shuffle();

        return $this->build($words, $count ?? max($this->count(), $words->count()), collect(), Word::with('conjugation')->get());
    }

    // ---------------------------------------------------------------- core

    /**
     * @param  Collection<int, Word>  $words  candidate words (already shuffled); each used at most once
     * @param  Collection<int, DialogLine>  $dialog
     * @param  Collection<int, Word>|null  $distractorPool  words to draw distractors from (defaults to $words)
     */
    public function build(Collection $words, int $count, ?Collection $dialog = null, ?Collection $distractorPool = null): array
    {
        $dialog ??= collect();
        $distractorPool ??= $words;
        $words = $words->unique('id')->values();
        $count = min($count, $words->count() + ($dialog->count() >= 4 ? min($this->config['dialog_max'], $dialog->count()) : 0));
        if ($count <= 0) {
            return [];
        }

        $slots = $this->slots($count);

        // Replace some de_to_ar slots with dialog questions when dialog lines are available.
        $dialogBudget = $dialog->count() >= 4 ? (int) $this->config['dialog_max'] : 0;
        foreach ($slots as $i => $type) {
            if ($dialogBudget <= 0) {
                break;
            }
            if ($type === 'de_to_ar') {
                $slots[$i] = 'dialog';
                $dialogBudget--;
            }
        }

        $unused = $words->keyBy('id');
        $usedDialog = collect();
        $questions = [];

        foreach ($slots as $type) {
            if ($type === 'dialog') {
                $line = $dialog->whereNotIn('id', $usedDialog)->shuffle()->first();
                if ($line) {
                    $usedDialog->push($line->id);
                    $questions[] = $this->dialogQuestion($line, $dialog);

                    continue;
                }
                $type = 'de_to_ar';
            }

            foreach ($this->fallbackChain($type) as $candidateType) {
                $word = $this->pickWord($unused, $candidateType);
                if ($word) {
                    $unused->forget($word->id);
                    $questions[] = $this->question($candidateType, $word, $distractorPool);

                    continue 2;
                }
            }
            // Nothing left to ask.
            break;
        }

        return array_values($questions);
    }

    /** Distribute $count slots over the configured ratios and shuffle them. */
    public function slots(int $count): array
    {
        $ratios = $this->config['ratios'];
        $sum = array_sum($ratios) ?: 100;
        $slots = [];
        $allocated = 0;
        $fractions = [];

        foreach ($ratios as $type => $pct) {
            $exact = $count * $pct / $sum;
            $n = (int) floor($exact);
            $fractions[$type] = $exact - $n;
            $allocated += $n;
            $slots = array_merge($slots, array_fill(0, $n, $type));
        }
        arsort($fractions);
        foreach (array_keys($fractions) as $type) {
            if ($allocated >= $count) {
                break;
            }
            $slots[] = $type;
            $allocated++;
        }

        shuffle($slots);

        return $slots;
    }

    private function fallbackChain(string $type): array
    {
        return match ($type) {
            'article' => ['article', 'plural', 'ar_to_de'],
            'plural' => ['plural', 'article', 'ar_to_de'],
            'conjugation' => ['conjugation', 'typing', 'ar_to_de'],
            'typing' => ['typing', 'ar_to_de'],
            'de_to_ar' => ['de_to_ar', 'ar_to_de'],
            default => ['ar_to_de', 'de_to_ar'],
        };
    }

    private function supports(Word $word, string $type): bool
    {
        return match ($type) {
            'article' => $word->type === 'noun' && $word->hasValidArticle(),
            'plural' => $word->type === 'noun' && $word->plural && mb_strtolower($word->plural) !== mb_strtolower($word->german),
            'conjugation' => $word->type === 'verb' && $word->conjugation !== null,
            default => true,
        };
    }

    /** Pick an unused word supporting $type. Irregular verbs are weighted higher for conjugation. */
    private function pickWord(Collection $unused, string $type): ?Word
    {
        $candidates = $unused->filter(fn (Word $w) => $this->supports($w, $type));
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($type === 'conjugation') {
            $weighted = [];
            foreach ($candidates as $w) {
                $weight = $w->isIrregularVerb() ? (int) $this->config['irregular_weight'] : 1;
                for ($i = 0; $i < $weight; $i++) {
                    $weighted[] = $w;
                }
            }

            return $weighted[array_rand($weighted)];
        }

        return $candidates->random();
    }

    // ---------------------------------------------------------------- question builders

    public function question(string $type, Word $word, Collection $pool): array
    {
        if ($type === 'article' && $this->supports($word, 'plural') && mt_rand() / mt_getrandmax() < (float) $this->config['plural_share']) {
            $type = 'plural';
        }

        $base = [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'word_id' => $word->id,
            'word' => [
                'german' => $word->german,
                'article' => $word->article,
                'display' => $word->display,
                'arabic' => $word->arabic,
                'plural' => $word->plural,
                'type' => $word->type,
            ],
        ];

        return $base + match ($type) {
            'ar_to_de' => [
                'prompt' => $word->arabic,
                'prompt_lang' => 'ar',
                'hint' => $this->typeLabel($word->type),
                'hint_key' => 'word_type',
                'hint_value' => $word->type,
                'options' => $this->withAnswer($this->distractors($word, 'display', $pool), $word->display),
                'answer' => $word->display,
            ],
            'de_to_ar' => [
                'prompt' => $word->display,
                'prompt_lang' => 'de',
                'hint' => $this->typeLabel($word->type),
                'hint_key' => 'word_type',
                'hint_value' => $word->type,
                'options' => $this->withAnswer($this->distractors($word, 'arabic', $pool), $word->arabic),
                'options_lang' => 'ar',
                'answer' => $word->arabic,
            ],
            'article' => [
                'prompt' => $word->german,
                'prompt_lang' => 'de',
                'hint' => 'der, die oder das?',
                'hint_key' => 'article',
                'hint_value' => null,
                'options' => ['der', 'die', 'das'],
                'answer' => $word->article,
            ],
            'plural' => [
                'prompt' => $word->display,
                'prompt_lang' => 'de',
                'hint' => 'Plural?',
                'hint_key' => 'plural',
                'hint_value' => null,
                'options' => $this->withAnswer($this->distractors($word, 'plural', $pool), $word->plural),
                'answer' => $word->plural,
            ],
            'conjugation' => $this->conjugationQuestion($word, $pool),
            'typing' => [
                'prompt' => $word->arabic,
                'prompt_lang' => 'ar',
                'hint' => $this->typeLabel($word->type).' – auf Deutsch tippen',
                'hint_key' => 'typing',
                'hint_value' => $word->type,
                'options' => [],
                'answer' => $word->display,
                'accept' => array_values(array_unique([$word->display, $word->german])),
            ],
        };
    }

    private function conjugationQuestion(Word $word, Collection $pool): array
    {
        $person = Conjugation::PERSONS[array_rand(Conjugation::PERSONS)];
        $answer = $word->conjugation->{$person};
        $forms = collect($word->conjugation->forms());

        // Other forms of the same verb first, then same person of other verbs.
        $distractors = $forms->values()->unique()->reject(fn ($f) => $f === $answer)->shuffle()->take(3)->values();
        if ($distractors->count() < 3) {
            $others = $pool->filter(fn (Word $w) => $w->id !== $word->id && $w->conjugation)
                ->map(fn (Word $w) => $w->conjugation->{$person})
                ->unique()->reject(fn ($f) => $f === $answer || $distractors->contains($f))
                ->shuffle()->take(3 - $distractors->count());
            $distractors = $distractors->merge($others);
        }

        return [
            'prompt' => $word->german.' – '.Conjugation::LABELS[$person].' ___',
            'prompt_lang' => 'de',
            'hint' => $word->arabic,
            'hint_key' => 'arabic',
            'hint_value' => $word->arabic,
            'person' => $person,
            'options' => $this->withAnswer($distractors->values()->all(), $answer),
            'answer' => $answer,
            'accept' => [$answer],
        ];
    }

    private function dialogQuestion(DialogLine $line, Collection $all): array
    {
        $question = $line->question_formal ?: $line->question_informal;
        $distractors = $all->where('id', '!=', $line->id)->pluck('answer')->unique()
            ->reject(fn ($a) => $a === $line->answer)->shuffle()->take(3)->values()->all();

        return [
            'id' => (string) Str::uuid(),
            'type' => 'dialog',
            'word_id' => null,
            'dialog_line_id' => $line->id,
            'word' => ['display' => $line->answer, 'arabic' => $line->arabic, 'german' => $line->answer, 'type' => 'dialog'],
            'prompt' => $question,
            'prompt_lang' => 'de',
            'hint' => $line->topic,
            'hint_key' => 'topic',
            'hint_value' => $line->topic,
            'options' => $this->withAnswer($distractors, $line->answer),
            'answer' => $line->answer,
        ];
    }

    /**
     * Three distractor strings for $field ('display' | 'arabic' | 'plural'):
     * same type & same Lektion first, then same type anywhere, then any type.
     */
    public function distractors(Word $word, string $field, Collection $pool, int $n = 3): array
    {
        $answer = $this->fieldValue($word, $field);
        $picked = collect();

        $tiers = [
            fn (Word $w) => $w->type === $word->type && $w->lektion_id === $word->lektion_id,
            fn (Word $w) => $w->type === $word->type,
        ];

        foreach ($tiers as $tier) {
            if ($picked->count() >= $n) {
                break;
            }
            $values = $pool->filter(fn (Word $w) => $w->id !== $word->id && $tier($w))
                ->map(fn (Word $w) => $this->fieldValue($w, $field))
                ->filter(fn ($v) => $v !== null && $v !== '' && mb_strtolower($v) !== mb_strtolower($answer))
                ->unique()->reject(fn ($v) => $picked->contains($v))->shuffle()->take($n - $picked->count());
            $picked = $picked->merge($values);
        }

        if ($picked->count() < $n) {
            // Same type anywhere in the database (pool may be a single Lektion).
            $values = Word::where('type', $word->type)->where('id', '!=', $word->id)->inRandomOrder()->limit(50)->get()
                ->map(fn (Word $w) => $this->fieldValue($w, $field))
                ->filter(fn ($v) => $v !== null && $v !== '' && mb_strtolower($v) !== mb_strtolower($answer))
                ->unique()->reject(fn ($v) => $picked->contains($v))->take($n - $picked->count());
            $picked = $picked->merge($values);
        }

        if ($picked->count() < $n) {
            // Last resort: any type, so the question still has four options.
            $values = $pool->filter(fn (Word $w) => $w->id !== $word->id)
                ->map(fn (Word $w) => $this->fieldValue($w, $field))
                ->filter(fn ($v) => $v !== null && $v !== '' && mb_strtolower($v) !== mb_strtolower($answer))
                ->unique()->reject(fn ($v) => $picked->contains($v))->shuffle()->take($n - $picked->count());
            $picked = $picked->merge($values);
        }

        return $picked->values()->all();
    }

    private function fieldValue(Word $w, string $field): ?string
    {
        return match ($field) {
            'display' => $w->display,
            'arabic' => $w->arabic,
            'plural' => $w->plural,
            default => $w->{$field},
        };
    }

    private function withAnswer(array $distractors, string $answer): array
    {
        $options = array_values(array_unique(array_merge($distractors, [$answer])));
        shuffle($options);

        return $options;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'noun' => 'Nomen',
            'verb' => 'Verb',
            'adjective' => 'Adjektiv',
            'adverb' => 'Adverb',
            'question_word' => 'Fragewort',
            'preposition' => 'Präposition',
            'particle' => 'Partikel',
            'expression' => 'Redemittel',
            'country' => 'Land',
            'language' => 'Sprache',
            'nationality' => 'Nationalität',
            default => $type,
        };
    }

    private function count(): int
    {
        return (int) $this->config['questions_per_session'];
    }
}
