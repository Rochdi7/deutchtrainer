# A1 course – implementation plan

## What exists and is reused
- Laravel 12 + Inertia/Vue 3 + Tailwind, SQLite/MySQL, Breeze auth, DE/EN UI (`resources/js/i18n`).
- `lektionen`, `categories`, `words` (German + Arabic, article, plural, type, meta), `conjugations`, `dialog_lines`, `grammar_notes`.
- Quiz engine (`QuizGenerator`, `AnswerChecker`, `ProgressService` = SM-2-lite in `user_word_progress`), `quiz_sessions`/`quiz_answers`, Dashboard stats (`StatsService`), Flashcards, ConjugationTable, DialogTable, MarkdownNote components, PWA shell.
- Lektion 1 vocabulary (207 words), dialog lines and grammar notes are already seeded from the same textbook page (AaeB p. 14–15).

## Additions
| Area | Change |
|---|---|
| Schema | `lektionen`: `level` (default A1), `slug`, `subtitle`, `objectives` json, `source_pages` json, `revision_md`, `sort`; unique(level, number). `words`: `english`, `example_en`, `image`, `source_page`, `tags`. `user_word_progress`: `is_difficult`. New: `readings`, `exercises`, `exercise_sessions`, `exercise_attempts`, `lesson_progress`. |
| Content | `resources/content/a1/lektion-0N/{meta,vocab,grammar,dialog,readings,exercises}.json` (+ `extras.json` for Lektion 1), validated by `ContentLoader::validate` (as implemented; no JSON-schema file); each vocab item may name a Twemoji SVG (CC-BY 4.0) by Unicode hex, copied into `public/images/vocab/` by `course:images`. Seeder `A1CourseSeeder` upserts by (level, number) / (lektion, german) – safe to rerun, never deletes user data. |
| Backend | `CourseController` (A1 dashboard, lesson overview, vocab study, sections, progress), `ExerciseController` + `ExerciseEngine` (sets, lesson test, practice, final assessment, answer checking with alternatives), `PracticeController`, `RevisionController` (due/difficult/wrong, `toggleDifficult`), `CourseProgressService`. |
| Frontend | `Course/Dashboard.vue`, `Lektionen/Show.vue` extended (Lernziele, Wortschatz with images + modes, Grammatik, Dialoge, Lesen, Übungen, Wiederholung, Lektionstest), `Exercises/Play.vue` (all types, TTS listening), `Practice/Index.vue`, `Revision/Index.vue`; nav: Dashboard · Kurs A1 · Lektionen · Üben · Wiederholung (· Quiz). |
| Audio | Browser SpeechSynthesis (de-DE) via `useSpeech` composable; listening exercises only when a German voice exists. |
| Tests | content validation test (every lesson file valid, references resolve, images exist), exercise checking tests, HTTP tests for the learner journey. |

## Not in scope
A2/B1, audio files from the CD, copyrighted textbook texts/illustrations (original texts are written at A1 level instead), external image downloads.
