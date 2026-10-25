<?php

namespace App\Services\Course;

use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseSession;
use App\Models\Lektion;
use App\Models\LessonProgress;
use App\Models\Reading;
use App\Models\User;
use App\Models\UserWordProgress;
use App\Models\Word;
use App\Services\Quiz\AnswerChecker;
use App\Services\Quiz\ProgressService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds exercise sessions (lesson sets, lesson tests, practice, revision, final assessment),
 * turns exercises / readings / words into client items and checks answers.
 *
 * Items keep their solution server-side (session.items); the controller strips 'solution'
 * before sending items to the browser.
 */
class ExerciseEngine
{
    /** Generated vocabulary modes. */
    public const VOCAB_MODES = ['image_word', 'word_image', 'de_en', 'en_de', 'ar_de', 'typing', 'article', 'plural', 'match', 'sentence', 'spelling', 'speak', 'speak_sentence'];

    /** Spoken answers scoring at least this (0–100, see AnswerChecker::spokenScore) count as correct. */
    public const SPEAK_PASS_SCORE = 80;

    public function __construct(
        private readonly AnswerChecker $checker,
        private readonly ProgressService $progress,
    ) {}

    // ------------------------------------------------------------------ sessions

    /** All exercises of one section of a lesson (plus reading questions for the reading section). */
    public function lessonSet(User $user, Lektion $lektion, string $section): ExerciseSession
    {
        $items = [];
        if ($section === 'reading') {
            foreach ($lektion->readings as $reading) {
                $items = array_merge($items, $this->readingItems($reading));
            }
        }
        foreach ($lektion->exercises()->where('section', $section)->orderBy('sort')->get() as $exercise) {
            $items[] = $this->exerciseItem($exercise);
        }
        if ($section === 'vocab') {
            $items = array_merge($items, $this->vocabItems($lektion->words()->get(), ['image_word', 'en_de', 'article'], 8));
        }

        return $this->createSession($user, 'set', $items, $lektion, $section, 'Übungen: '.$section);
    }

    /** Vocabulary practice in one mode (image→word, word→image, EN→DE …) for a lesson. */
    public function vocabSession(User $user, Lektion $lektion, string $mode, int $count = 12): ExerciseSession
    {
        $modes = $mode === 'mixed' ? ['image_word', 'word_image', 'de_en', 'en_de', 'typing', 'article', 'plural', 'sentence'] : [$mode];
        $items = $this->vocabItems($lektion->words()->get(), $modes, $count);

        return $this->createSession($user, 'practice', $items, $lektion, 'vocab', 'Wortschatz: '.$mode);
    }

    /** Lesson test: mixed exercises + reading + vocabulary; passing marks the lesson as completed. */
    public function lessonTest(User $user, Lektion $lektion): ExerciseSession
    {
        $exercises = $lektion->exercises()->where('type', '!=', 'write')->get()->shuffle();
        $items = $exercises->take(12)->map(fn (Exercise $e) => $this->exerciseItem($e))->all();
        $readingItems = collect($lektion->readings)->flatMap(fn (Reading $r) => $this->readingItems($r))->shuffle()->take(4)->all();
        $vocab = $this->vocabItems($lektion->words()->get(), ['image_word', 'en_de', 'article', 'typing'], 4);
        $items = collect(array_merge($items, $readingItems, $vocab))->shuffle()->values()->all();

        return $this->createSession($user, 'test', $items, $lektion, 'test', 'Lektionstest '.$lektion->number);
    }

    /**
     * Free practice with filters.
     *
     * @param  array{lektion_ids?: int[], types?: string[], sections?: string[], difficult?: bool, wrong?: bool, count?: int, level?: string}  $filters
     */
    public function practice(User $user, array $filters): ExerciseSession
    {
        $count = max(5, min(40, (int) ($filters['count'] ?? 20)));
        $lektionen = Lektion::level($filters['level'] ?? 'A1')->get();
        if (! empty($filters['lektion_ids'])) {
            $lektionen = $lektionen->whereIn('id', $filters['lektion_ids']);
        }
        $ids = $lektionen->pluck('id');
        $items = [];

        if (! empty($filters['wrong'])) {
            $wrongIds = ExerciseAttempt::where('user_id', $user->id)->where('is_correct', false)->whereNotNull('exercise_id')
                ->latest('answered_at')->limit(200)->pluck('exercise_id')->unique();
            $exercises = Exercise::whereIn('id', $wrongIds)->whereIn('lektion_id', $ids)->where('type', '!=', 'write')->get()->shuffle()->take($count);
            $items = $exercises->map(fn (Exercise $e) => $this->exerciseItem($e))->all();
        } elseif (! empty($filters['difficult'])) {
            $wordIds = UserWordProgress::where('user_id', $user->id)
                ->where(fn ($q) => $q->where('is_difficult', true)->orWhereColumn('wrong_count', '>', 'correct_count'))
                ->pluck('word_id');
            $words = Word::whereIn('id', $wordIds)->whereIn('lektion_id', $ids)->get();
            $items = $this->vocabItems($words, ['image_word', 'en_de', 'typing', 'article', 'de_en'], $count);
        } else {
            $query = Exercise::whereIn('lektion_id', $ids)->where('type', '!=', 'write');
            if (! empty($filters['types'])) {
                $query->whereIn('type', $filters['types']);
            }
            if (! empty($filters['sections'])) {
                $query->whereIn('section', $filters['sections']);
            }
            $wantVocab = empty($filters['types']) && (empty($filters['sections']) || in_array('vocab', $filters['sections'], true));
            $exerciseShare = $wantVocab ? (int) ceil($count * 0.6) : $count;
            $items = $query->inRandomOrder()->limit($exerciseShare)->get()->map(fn (Exercise $e) => $this->exerciseItem($e))->all();
            if ($wantVocab && empty($filters['types'])) {
                $words = Word::whereIn('lektion_id', $ids)->inRandomOrder()->limit(120)->get();
                $items = array_merge($items, $this->vocabItems($words, ['image_word', 'word_image', 'en_de', 'de_en', 'typing', 'article', 'plural'], $count - count($items)));
            }
        }

        $label = ! empty($filters['wrong']) ? 'Üben: Fehler' : (! empty($filters['difficult']) ? 'Üben: schwierige Wörter' : 'Üben');

        return $this->createSession($user, 'practice', collect($items)->shuffle()->values()->all(), null, null, $label);
    }

    /** Spaced-repetition revision: due words first, then recent mistakes. */
    public function revision(User $user, int $count = 20): ExerciseSession
    {
        $due = UserWordProgress::where('user_id', $user->id)
            ->where(fn ($q) => $q->where('next_review_at', '<=', now()->endOfDay())->orWhere('is_difficult', true))
            ->orderBy('next_review_at')->limit(60)->pluck('word_id');
        $words = Word::whereIn('id', $due)->get()->shuffle();
        $items = $this->vocabItems($words, ['image_word', 'en_de', 'typing', 'article', 'de_en', 'plural', 'sentence'], (int) ceil($count * 0.75));

        $wrongIds = ExerciseAttempt::where('user_id', $user->id)->where('is_correct', false)->whereNotNull('exercise_id')
            ->latest('answered_at')->limit(100)->pluck('exercise_id')->unique();
        $exercises = Exercise::whereIn('id', $wrongIds)->where('type', '!=', 'write')->get()->shuffle()->take($count - count($items));
        $items = array_merge($items, $exercises->map(fn (Exercise $e) => $this->exerciseItem($e))->all());

        if (count($items) < $count) {
            // Nothing (or not enough) due yet: top up with the weakest / least practised words of the
            // lessons the learner has started, so "Wiederholung" always offers something to do.
            $started = LessonProgress::where('user_id', $user->id)->pluck('lektion_id')->unique();
            $lektionIds = $started->isNotEmpty() ? $started : Lektion::level('A1')->orderBy('sort')->limit(1)->pluck('id');
            $seen = collect($items)->pluck('word_id')->filter();
            $pool = Word::whereIn('lektion_id', $lektionIds)->whereNotIn('id', $seen)
                ->leftJoin('user_word_progress', fn ($j) => $j->on('words.id', '=', 'user_word_progress.word_id')->where('user_word_progress.user_id', $user->id))
                ->orderByRaw('COALESCE(user_word_progress.level, 0) ASC')->inRandomOrder()->limit(80)->select('words.*')->get();
            $items = array_merge($items, $this->vocabItems($pool, ['image_word', 'en_de', 'typing', 'article', 'de_en'], $count - count($items)));
        }

        return $this->createSession($user, 'revision', collect($items)->shuffle()->values()->all(), null, null, 'Wiederholung');
    }

    /**
     * Pronunciation drill: say words (and a few example sentences) out loud; the browser's speech
     * recognition transcribes them and check() scores the transcript.
     */
    public function speaking(User $user, array $filters): ExerciseSession
    {
        $count = max(5, min(40, (int) ($filters['count'] ?? 12)));
        $ids = collect($filters['lektion_ids'] ?? []);
        if ($ids->isEmpty()) {
            $ids = LessonProgress::where('user_id', $user->id)->pluck('lektion_id')->unique();
        }
        if ($ids->isEmpty()) {
            $ids = Lektion::level($filters['level'] ?? 'A1')->orderBy('sort')->limit(1)->pluck('id');
        }

        $words = Word::whereIn('lektion_id', $ids)->inRandomOrder()->limit(150)->get();
        $sentences = (int) floor($count / 4);
        $items = array_merge(
            $this->vocabItems($words, ['speak'], $count - $sentences),
            $this->vocabItems($words, ['speak_sentence'], $sentences),
        );

        return $this->createSession($user, 'practice', collect($items)->shuffle()->values()->all(), null, 'vocab', 'Aussprache');
    }

    /** Cumulative A1 practice test (not an official exam). */
    public function finalAssessment(User $user, string $level = 'A1'): ExerciseSession
    {
        $lektionen = Lektion::level($level)->get();
        $ids = $lektionen->pluck('id');
        $items = [];

        // ~3 exercises per lesson across grammar/vocab/dialog, balanced by lesson.
        foreach ($lektionen as $l) {
            $items = array_merge($items, $l->exercises()->whereIn('section', ['grammar', 'dialog', 'vocab', 'listening'])
                ->where('type', '!=', 'write')->inRandomOrder()->limit(3)->get()->map(fn (Exercise $e) => $this->exerciseItem($e))->all());
        }
        $readings = Reading::whereIn('lektion_id', $ids)->inRandomOrder()->limit(3)->get();
        foreach ($readings as $r) {
            $items = array_merge($items, collect($this->readingItems($r))->shuffle()->take(3)->all());
        }
        $words = Word::whereIn('lektion_id', $ids)->inRandomOrder()->limit(150)->get();
        $items = array_merge($items, $this->vocabItems($words, ['image_word', 'en_de', 'typing', 'article', 'plural', 'sentence'], 10));

        return $this->createSession($user, 'final', collect($items)->shuffle()->values()->all(), null, null, 'A1 Abschlusstest');
    }

    private function createSession(User $user, string $mode, array $items, ?Lektion $lektion, ?string $section, string $label): ExerciseSession
    {
        return ExerciseSession::create([
            'user_id' => $user->id,
            'lektion_id' => $lektion?->id,
            'mode' => $mode,
            'section' => $section,
            'label' => $label,
            'items' => array_values($items),
            'total' => count($items),
            'correct' => 0,
            'started_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ items

    public function exerciseItem(Exercise $e): array
    {
        $d = $e->data;
        $public = collect($d)->except(['answer', 'accept', 'explanation', 'transcript', 'model_answer', 'checklist', 'pairs'])->all();
        $solution = collect($d)->only(['answer', 'accept', 'explanation', 'transcript', 'model_answer', 'checklist', 'translation'])->all();
        if (! isset($solution['answer']) && ! empty($solution['accept'])) {
            // Typed exercises may list only accepted spellings; the first one is shown as the model answer.
            $solution['answer'] = $solution['accept'][0];
        }

        if ($e->type === 'match') {
            $pairs = $d['pairs'];
            $public['left'] = array_map(fn ($p) => $p[0], $pairs);
            $right = array_map(fn ($p) => $p[1], $pairs);
            shuffle($right);
            $public['right'] = $right;
            $solution['pairs'] = $pairs;
        }
        if ($e->type === 'order') {
            $tokens = $d['tokens'];
            shuffle($tokens);
            $public['tokens'] = $tokens;
        }
        if (in_array($e->type, ['mc', 'dialog', 'listen'], true) && isset($d['options'])) {
            $options = $d['options'];
            shuffle($options);
            $public['options'] = $options;
        }
        if ($e->type === 'article') {
            $public['options'] = ['der', 'die', 'das'];
        }
        if ($e->type === 'write') {
            $solution['model_answer'] = $d['model_answer'];
            $solution['checklist'] = $d['checklist'] ?? [];
        }

        return [
            'id' => (string) Str::uuid(),
            'kind' => 'exercise',
            'exercise_id' => $e->id,
            'word_id' => null,
            'lektion_id' => $e->lektion_id,
            'type' => $e->type,
            'section' => $e->section,
            'prompt' => $e->prompt,
            'data' => $public,
            'solution' => $solution,
        ];
    }

    /** One item per comprehension question, carrying the passage. */
    public function readingItems(Reading $r): array
    {
        $items = [];
        foreach ($r->questions ?? [] as $q) {
            $options = $q['options'];
            shuffle($options);
            $items[] = [
                'id' => (string) Str::uuid(),
                'kind' => 'reading',
                'exercise_id' => null,
                'word_id' => null,
                'lektion_id' => $r->lektion_id,
                'type' => 'reading',
                'section' => 'reading',
                'prompt' => $q['q'],
                'data' => [
                    'options' => $options,
                    'passage' => ['title' => $r->title, 'text_md' => $r->text_md, 'translation_md' => $r->translation_md, 'key' => $r->key],
                ],
                'solution' => ['answer' => $q['answer'], 'explanation' => $q['explanation'] ?? null],
            ];
        }

        return $items;
    }

    /**
     * Generated vocabulary items.
     *
     * @param  Collection<int, Word>  $pool
     * @param  string[]  $modes
     */
    public function vocabItems(Collection $pool, array $modes, int $count): array
    {
        $pool = $pool->values();
        if ($pool->isEmpty() || $count <= 0) {
            return [];
        }
        $candidates = $pool->shuffle();
        $items = [];
        $used = [];
        $i = 0;
        $guard = 0;
        while (count($items) < $count && $guard++ < $count * 12) {
            $mode = $modes[$i++ % count($modes)];
            $word = $candidates->first(fn (Word $w) => ! isset($used[$w->id]) && $this->supports($w, $mode, $pool));
            if (! $word) {
                // No fresh word for this mode: try another mode, give up if nothing works.
                if (! $candidates->first(fn (Word $w) => ! isset($used[$w->id]))) {
                    break;
                }

                continue;
            }
            $item = $this->vocabItem($word, $mode, $pool);
            if ($item === null) {
                continue;
            }
            $used[$word->id] = true;
            $items[] = $item;
        }

        return $items;
    }

    private function supports(Word $w, string $mode, Collection $pool): bool
    {
        return match ($mode) {
            'image_word', 'word_image' => $w->image !== null && $pool->filter(fn (Word $o) => $o->image && $o->image !== $w->image)->count() >= 3,
            'article' => $w->type === 'noun' && in_array($w->article, ['der', 'die', 'das'], true),
            'plural' => $w->type === 'noun' && $w->plural && mb_strtolower($w->plural) !== mb_strtolower($w->german),
            'sentence' => $w->example_de !== null && preg_match('/\b'.preg_quote($w->german, '/').'\b/u', $w->example_de) === 1 && ! str_contains($w->german, ' '),
            'spelling' => mb_strlen($w->german) >= 4 && ! str_contains($w->german, ' '),
            'de_en', 'en_de', 'typing' => $w->english !== null,
            'ar_de', 'speak' => true,
            'speak_sentence' => $w->example_de !== null && mb_strlen($w->example_de) <= 80,
            'match' => $w->english !== null && $pool->count() >= 4,
            default => false,
        };
    }

    private function vocabItem(Word $w, string $mode, Collection $pool): ?array
    {
        $base = [
            'id' => (string) Str::uuid(),
            'kind' => 'vocab',
            'exercise_id' => null,
            'word_id' => $w->id,
            'lektion_id' => $w->lektion_id,
            'section' => 'vocab',
            'word' => ['display' => $w->display, 'german' => $w->german, 'english' => $w->english, 'arabic' => $w->arabic, 'image' => $w->image, 'plural' => $w->plural, 'type' => $w->type, 'example_de' => $w->example_de, 'example_en' => $w->example_en],
        ];

        switch ($mode) {
            case 'image_word':
                $others = $pool->filter(fn (Word $o) => $o->id !== $w->id && $o->image && $o->image !== $w->image && $o->type === $w->type)->shuffle()->take(3);
                if ($others->count() < 3) {
                    $others = $pool->filter(fn (Word $o) => $o->id !== $w->id && $o->image && $o->image !== $w->image)->shuffle()->take(3);
                }
                $options = $others->map(fn (Word $o) => $o->display)->push($w->display)->shuffle()->values()->all();

                return $base + ['type' => 'mc', 'vocab_mode' => $mode, 'prompt' => 'Was ist das?', 'data' => ['image' => $w->image, 'options' => $options, 'hint' => $w->type], 'solution' => ['answer' => $w->display, 'translation' => $w->english]];
            case 'word_image':
                $others = $pool->filter(fn (Word $o) => $o->id !== $w->id && $o->image && $o->image !== $w->image)->unique('image')->shuffle()->take(3);
                $options = $others->map(fn (Word $o) => $o->image)->push($w->image)->shuffle()->values()->all();

                return $base + ['type' => 'mc', 'vocab_mode' => $mode, 'prompt' => $w->display, 'data' => ['options' => $options, 'options_kind' => 'image'], 'solution' => ['answer' => $w->image, 'translation' => $w->english]];
            case 'de_en':
                $options = $this->distractors($pool, $w, 'english')->push($w->english)->shuffle()->values()->all();

                return $base + ['type' => 'mc', 'vocab_mode' => $mode, 'prompt' => $w->display, 'data' => ['options' => $options, 'image' => $w->image], 'solution' => ['answer' => $w->english]];
            case 'en_de':
                $options = $this->distractors($pool, $w, 'display')->push($w->display)->shuffle()->values()->all();

                return $base + ['type' => 'mc', 'vocab_mode' => $mode, 'prompt' => $w->english, 'data' => ['options' => $options, 'hint' => $w->type], 'solution' => ['answer' => $w->display]];
            case 'ar_de':
                $options = $this->distractors($pool, $w, 'display')->push($w->display)->shuffle()->values()->all();

                return $base + ['type' => 'mc', 'vocab_mode' => $mode, 'prompt' => $w->arabic, 'data' => ['options' => $options, 'prompt_lang' => 'ar', 'hint' => $w->type], 'solution' => ['answer' => $w->display]];
            case 'typing':
                return $base + ['type' => 'gap', 'vocab_mode' => $mode, 'prompt' => $w->english, 'data' => ['image' => $w->image, 'hint' => $w->type === 'noun' ? 'mit Artikel' : null, 'typing' => true], 'solution' => ['answer' => $w->display, 'accept' => array_values(array_unique([$w->display, $w->german]))]];
            case 'article':
                return $base + ['type' => 'article', 'vocab_mode' => $mode, 'prompt' => $w->german, 'data' => ['word' => $w->german, 'options' => ['der', 'die', 'das'], 'image' => $w->image], 'solution' => ['answer' => $w->article, 'explanation' => $w->display.($w->plural ? ', Pl. '.$w->plural : '')]];
            case 'plural':
                return $base + ['type' => 'plural', 'vocab_mode' => $mode, 'prompt' => $w->display, 'data' => ['word' => $w->display, 'image' => $w->image], 'solution' => ['answer' => 'die '.$w->plural, 'accept' => [$w->plural, 'die '.$w->plural], 'explanation' => $w->plural_suffix ? 'Pluralzeichen: '.$w->plural_suffix : null]];
            case 'sentence':
                $gapped = preg_replace('/\b'.preg_quote($w->german, '/').'\b/u', '___', $w->example_de, 1);
                $options = $this->distractors($pool, $w, 'german')->push($w->german)->shuffle()->values()->all();

                return $base + ['type' => 'mc', 'vocab_mode' => $mode, 'prompt' => $gapped, 'data' => ['options' => $options, 'translation' => $w->example_en], 'solution' => ['answer' => $w->german, 'translation' => $w->example_en]];
            case 'spelling':
                $letters = preg_split('//u', $w->german, -1, PREG_SPLIT_NO_EMPTY);
                shuffle($letters);

                return $base + ['type' => 'order', 'vocab_mode' => $mode, 'prompt' => $w->english.($w->article ? ' ('.$w->article.')' : ''), 'data' => ['tokens' => $letters, 'join' => '', 'image' => $w->image], 'solution' => ['answer' => $w->german, 'accept' => [$w->german]]];
            case 'speak':
                return $base + ['type' => 'speak', 'vocab_mode' => $mode, 'prompt' => $w->display, 'data' => ['text' => $w->display, 'image' => $w->image, 'translation' => $w->english], 'solution' => ['answer' => $w->display, 'accept' => array_values(array_unique([$w->display, $w->german]))]];
            case 'speak_sentence':
                return $base + ['type' => 'speak', 'vocab_mode' => $mode, 'prompt' => $w->example_de, 'data' => ['text' => $w->example_de, 'translation' => $w->example_en], 'solution' => ['answer' => $w->example_de, 'accept' => [$w->example_de]]];
            case 'match':
                $others = $pool->filter(fn (Word $o) => $o->id !== $w->id && $o->english)->shuffle()->take(3);
                $pairs = $others->map(fn (Word $o) => [$o->display, $o->english])->push([$w->display, $w->english])->values()->all();
                $right = array_map(fn ($p) => $p[1], $pairs);
                shuffle($right);

                return $base + ['type' => 'match', 'vocab_mode' => $mode, 'prompt' => 'Ordnen Sie zu.', 'data' => ['left' => array_map(fn ($p) => $p[0], $pairs), 'right' => $right], 'solution' => ['pairs' => $pairs]];
        }

        return null;
    }

    private function distractors(Collection $pool, Word $w, string $field): Collection
    {
        $value = fn (Word $o) => $field === 'display' ? $o->display : $o->{$field};
        $same = $pool->filter(fn (Word $o) => $o->id !== $w->id && $o->type === $w->type && $value($o) && mb_strtolower($value($o)) !== mb_strtolower($value($w)))
            ->unique(fn (Word $o) => mb_strtolower($value($o)))->shuffle()->take(3);
        if ($same->count() < 3) {
            $more = $pool->filter(fn (Word $o) => $o->id !== $w->id && $value($o) && mb_strtolower($value($o)) !== mb_strtolower($value($w)))
                ->unique(fn (Word $o) => mb_strtolower($value($o)))->reject(fn (Word $o) => $same->contains('id', $o->id))->shuffle()->take(3 - $same->count());
            $same = $same->merge($more);
        }

        return $same->map($value)->values();
    }

    // ------------------------------------------------------------------ checking

    /**
     * @param  mixed  $given  string | string[][] (match) | array{text:string, self_ok:bool} (write) | array{transcripts:string[], self_ok?:bool} (speak)
     * @return array{correct: bool, answer: mixed, explanation: ?string, translation: ?string, model_answer?: string, checklist?: array}
     */
    public function check(array $item, mixed $given): array
    {
        $s = $item['solution'];
        $type = $item['type'];
        $correct = false;
        $heard = null;
        $score = null;

        switch ($type) {
            case 'mc':
            case 'dialog':
            case 'listen':
            case 'reading':
            case 'article':
                $correct = is_string($given) && $this->checker->optionMatches($given, (string) $s['answer']);
                break;
            case 'gap':
            case 'plural':
            case 'conjugation':
                $accept = $s['accept'] ?? [$s['answer']];
                $correct = is_string($given) && collect($accept)->contains(fn ($a) => $this->checker->typingMatches($given, (string) $a));
                break;
            case 'translate':
                $accept = $s['accept'] ?? [$s['answer']];
                $direction = $item['data']['direction'] ?? 'en-de';
                $correct = is_string($given) && collect($accept)->contains(fn ($a) => $direction === 'de-en'
                    ? $this->checker->englishMatches($given, (string) $a)
                    : $this->checker->sentenceMatches($given, (string) $a));
                break;
            case 'order':
                $join = $item['data']['join'] ?? ' ';
                $text = is_array($given) ? implode($join, $given) : (string) $given;
                $accept = array_merge([$s['answer']], $s['accept'] ?? []);
                $correct = collect($accept)->contains(fn ($a) => $this->checker->sentenceMatches($text, (string) $a));
                break;
            case 'match':
                $pairs = $s['pairs'];
                $correct = is_array($given) && count($given) === count($pairs)
                    && collect($given)->every(fn ($p) => is_array($p) && collect($pairs)->contains(fn ($x) => $x[0] === ($p[0] ?? null) && $x[1] === ($p[1] ?? null)));
                break;
            case 'speak':
                // Recogniser alternatives are scored against every accepted form; without speech
                // recognition (e.g. Firefox) the learner self-checks against the TTS model.
                $transcripts = is_array($given) ? array_filter(array_map('strval', (array) ($given['transcripts'] ?? [])), fn ($t) => trim($t) !== '') : [];
                if ($transcripts === []) {
                    $correct = is_array($given) && ! empty($given['self_ok']);
                    break;
                }
                $score = -1;
                foreach (array_slice($transcripts, 0, 5) as $t) {
                    foreach ($s['accept'] ?? [$s['answer']] as $a) {
                        $sc = $this->checker->spokenScore($t, (string) $a);
                        if ($sc > $score) {
                            [$score, $heard] = [$sc, $t];
                        }
                    }
                }
                $correct = $score >= self::SPEAK_PASS_SCORE;
                break;
            case 'write':
                $correct = is_array($given) && ! empty($given['self_ok']) && trim((string) ($given['text'] ?? '')) !== '';
                break;
        }

        $answer = $s['answer'] ?? null;
        if ($type === 'match') {
            $answer = $s['pairs'];
        }
        if ($type === 'write') {
            $answer = $s['model_answer'];
        }

        return [
            'correct' => $correct,
            'answer' => $answer,
            'explanation' => $s['explanation'] ?? null,
            'translation' => $s['translation'] ?? ($item['data']['translation'] ?? null),
            'transcript' => $s['transcript'] ?? null,
            'model_answer' => $s['model_answer'] ?? null,
            'checklist' => $s['checklist'] ?? null,
            'heard' => $heard,
            'score' => $score,
        ];
    }

    /** Record an attempt, update word progress and the session counter. */
    public function record(User $user, ExerciseSession $session, array $item, mixed $given, bool $correct): ExerciseAttempt
    {
        $attempt = ExerciseAttempt::create([
            'user_id' => $user->id,
            'exercise_session_id' => $session->id,
            'exercise_id' => $item['exercise_id'],
            'word_id' => $item['word_id'],
            'item_id' => $item['id'],
            'type' => $item['type'],
            'given' => is_string($given) ? $given : json_encode($given, JSON_UNESCAPED_UNICODE),
            'is_correct' => $correct,
            'answered_at' => now(),
        ]);
        if ($correct) {
            $session->increment('correct');
        }
        if ($item['word_id']) {
            $this->progress->record($user, $item['word_id'], $correct);
        }

        return $attempt;
    }

    /** Items without solutions, for the browser. */
    public function publicItems(ExerciseSession $session): array
    {
        return array_map(fn ($item) => collect($item)->except(['solution'])->all(), $session->items);
    }
}
