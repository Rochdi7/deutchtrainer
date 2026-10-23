# A1 course – final report

Date: 2026-10-21 · Branch `main`, uncommitted working tree (nothing committed, pushed or deployed, as requested).

## 1. PDF analysis

- Source: `book/daf-kompakt-neu-a1.pdf`, **145 pages, all read** (scanned images without a text layer; each page was rendered and read visually, OCR was used only as a search aid). The per-page log is `docs/a1-source-inventory.md`; the lesson map (titles, Kursbuch / "Alles auf einen Blick" / Übungsbuch pages, grammar, vocabulary groups, Redemittel, text types) is `docs/a1-curriculum-map.md`.
- The scan ends on p. 145 in the middle of the "Modelltest Start Deutsch 1"; the answer key, transcripts and any pages after 145 are not in the file.
- No audio is included with the PDF; listening exercises therefore use the browser's German text-to-speech voice.

## 2. Curriculum (A1 only)

| # | Lektion | Kursbuch | Wortschatz/Grammatik | Übungsbuch |
|---|---|---|---|---|
| 1 | Ich und die anderen | 8–13 | 14–17 | 76–83 |
| 2 | Menschen und Dinge | 18–23 | 24–25 | 84–91 |
| 3 | Studentenleben | 26–31 | 32–33 | 92–99 |
| 4 | Wirtschaft trifft Kultur | 34–39 | 40–41 | 100–107 |
| 5 | Spiel und Spaß | 42–47 | 48–49 | 108–115 |
| 6 | Endlich ein Zimmer | 50–55 | 56–57 | 116–123 |
| 7 | Kleider machen Freunde | 58–63 | 64–65 | 124–131 |
| 8 | Grüezi in der Schweiz | 66–71 | 72–73 | 132–139 |

Each lesson in the app has the sections Lernziele · Wortschatz · Grammatik · Sätze und Dialoge · Lesen · Übungen (sets for Wortschatz, Grammatik, Dialoge, Hören, Schreiben) · Wiederholung · Lektionstest. Lesson objectives, vocabulary lists and grammar topics follow the textbook; reading texts, dialogues beyond the Redemittel, example sentences and all exercises are original material written for this app (no textbook texts, songs, poems or images are reproduced).

## 3. Content inventory (seeded, verified twice in a fresh SQLite database)

| | Count |
|---|---|
| Lektionen (level A1) | 8 |
| Vocabulary words | 1343 (nouns 724, verbs 237, adjectives 127, expressions 85, adverbs 54, prepositions 33, nationalities 21, countries 21, languages 16, particles 15, question words 10) |
| Words with English translation | 1343 / 1343 |
| Words with Arabic translation | 1343 / 1343 |
| Nouns with plural form | 621 / 724 (the rest are singular-only or plural-only per the textbook notation) |
| Words with example sentence (de + en) | 229 |
| Words with image | 615 (303 distinct Twemoji SVGs, CC-BY 4.0, attribution in `public/images/vocab/ATTRIBUTION.md`) |
| Verb conjugation tables | 100 |
| Dialog / Redemittel lines (de, ar, en) | 116 |
| Grammar notes (markdown, de + en) | 48 |
| Reading texts (with translation + questions) | 19, 74 comprehension questions |
| Authored exercises | 336 (per lesson 39–45): mc 102, gap 72, order 31, conjugation 25, dialog 23, article 19, translate 17, listen 16, match 14, plural 9, write 8 |
| Generated vocabulary exercises | unlimited (11 modes: picture→word, word→picture, de→en, en→de, ar→de, typing, article, plural, matching, in-context, spelling) |

Per lesson: L1 245 words / 41 exercises, L2 182 / 39, L3 184 / 42, L4 131 / 41, L5 147 / 41, L6 172 / 43, L7 158 / 45, L8 124 / 44. Lesson tests draw 12 authored + 4 reading + 4 vocabulary items; the A1 final assessment draws 40 items across all lessons.

## 4. Application changes

**Schema** (`database/migrations/2026_10_21_*`): `lektionen` + level/slug/subtitle/objectives/source_pages/revision_md/sort with unique (level, number); `words` + english/example_en/image/source_page/tags; `user_word_progress` + is_difficult; `dialog_lines` + english/source_page; `grammar_notes` + key/source_page; new tables `readings`, `exercises`, `exercise_sessions`, `exercise_attempts`, `lesson_progress`.

**Content & seeding**: `resources/content/a1/lektion-0N/{meta,vocab,grammar,dialog,readings,exercises}.json` (+ `extras.json` for L1); `App\Services\Course\ContentLoader` (load + validate), `VocabEntryFactory` (textbook notation parser), `VocabImageService` + `php artisan course:images`; `Database\Seeders\A1CourseSeeder` (idempotent upserts, validates before writing, registered in `DatabaseSeeder`).

**Backend**: `ExerciseEngine` (lesson sets, vocab sessions, lesson test, practice filters, spaced-repetition revision with fallback pool, final assessment, server-side answer checking for 12 item types, SM-2 updates), `CourseProgressService` (sections, lesson completion, course overview, revision overview), `AnswerChecker::sentenceMatches/englishMatches`; controllers `CourseController`, `ExerciseController`, `PracticeController`, `RevisionController`, extended `LektionController`, `DashboardController`, `WordController` (English field); routes `/kurs/{level}`, `/uebungen/*`, `/ueben`, `/wiederholung`, `POST /words/{word}/difficult`, `POST /kurs/{lektion}/{section}`.

**Frontend**: `Course/Dashboard.vue`, rewritten `Lektionen/Show.vue` (8 sections, image cards/list/flashcards/verbs, practice modes, difficult toggle, prev/next, admin word editing kept), `Exercises/Play.vue` (all item types, feedback sheet, retry, keyboard, TTS), `Practice/Index.vue`, `Revision/Index.vue`, `VocabImage.vue`, `useSpeech.js`, course card on the home dashboard, completion bar on the lesson list, navigation Dashboard · Kurs A1 · Lektionen · Üben · Wiederholung (· Quiz), bottom tab bar; DE/EN strings in `resources/js/i18n/course.{de,en}.js`.

**Preserved**: the existing quiz engine, flashcards, import pipeline, word pages, admin editing and stats all keep working (their tests still pass).

## 5. Testing

Run on 2026-10-21 (in-memory SQLite):

```
php artisan test      → 79 passed (5793 assertions), 0 failed, 19.9 s
npm run build         → built in 7.6 s, no warnings
vendor/bin/pint --test (files touched by this work) → clean
```

New tests: `tests/Feature/A1ContentTest.php` (8 lessons valid, enough content per lesson, every word has de/en/ar, every referenced image file exists, attribution present) and `tests/Feature/A1CourseFlowTest.php` (seeder idempotency and counts; course dashboard, lesson page, home dashboard, lesson list, practice and revision pages render with the expected props; exercise set with wrong + correct answers, duplicate-answer rejection, finish, set score stored, retry-wrong session; vocab session updates spaced repetition and the difficult flag; marking sections and passing the lesson test completes the lesson and advances the current lesson; practice by type/lesson/section, revision, final assessment, session privacy; engine checks for mc/gap/order/match/translate (both directions)/write/listen).

Bugs found and fixed by the tests: an invisible backspace byte in the English answer regex; SQLite `LOWER()` not folding umlauts (seeder re-created "Öl", "Übung", … on every run); accept-only typed exercises returned no model answer in feedback; practice with a type filter returned fewer items than requested; home dashboard crashed when quiz sessions existed (Eloquent/base collection merge); revision had nothing to offer before any word was due.

Not verified: no browser was available in this session, so the Vue pages were checked only through the Vite build and the Inertia prop assertions, not by clicking through them. Three pre-existing files (`HandleInertiaRequests`, `TextNormalizer`, `StatsService`) have Pint style warnings that predate this work and were left untouched.

## 6. Remaining work / limitations

- Sprechen has no dedicated section; speaking is covered by read-aloud (TTS) prompts in Dialoge and Lesen only. Hören uses browser TTS, which depends on a German voice being installed (a transcript is shown otherwise).
- Writing tasks are self-assessed against a model answer and checklist (no automatic grading).
- 728 words have no emoji illustration and show an initial-letter tile; a reviewed, license-clean photo set would improve the picture modes.
- Content was authored from the textbook's word lists and grammar overviews; a native-speaker review pass of the original texts and exercises is recommended before publishing.
- The Modelltest (p. 140–145) is incomplete in the scan and was not turned into content.
- Nothing has been committed, pushed or deployed. To publish: review the diff, commit, then run the existing `deploy.sh` flow on the server (`php artisan migrate --force && php artisan db:seed --class=A1CourseSeeder --force`; the SVGs under `public/images/vocab` must be uploaded with the build since the server has no Node).
