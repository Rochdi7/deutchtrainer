# DeutschTrainer

Duolingo-style vocabulary trainer for the **DaF kompakt A1** course (German–Arabic). Upload one PDF word list per Lektion, let the built-in reader turn it into structured vocabulary (no API needed), then drill it with multiple-choice, article, plural, conjugation, typing and dialog questions. Progress uses a light SM-2 schedule.

Stack: Laravel 12 · Inertia + Vue 3 · Tailwind (dark mode) · MySQL or SQLite · Breeze auth · database/sync queue · `smalot/pdfparser` (pure-PHP PDF reader) · optional Anthropic Messages API via Laravel's `Http` client · Chart.js.

## Setup

Requirements: PHP ≥ 8.2 (with `intl`, `mbstring`, `pdo_mysql` or `pdo_sqlite`), Composer, Node ≥ 20 (build machine only), MySQL/MariaDB or SQLite. No external binaries: the PDF is read in pure PHP.

```bash
composer install
npm install
cp .env.example .env          # then edit, see below
php artisan key:generate
php artisan migrate --seed    # creates admin user + imports Lektion 1
npm run build                 # or: npm run dev
php artisan serve
php artisan queue:work        # needed for the web PDF import
```

Logins created by the seeder (both admins):

| Email | Password | UI language |
|---|---|---|
| `rochdi.karouali1234@gmail.com` | `1234` | English |
| `admin@deutschtrainer.local` | `password` | German |

Registration is open; new users are not admins. Change the short password in **Profile** before exposing the app beyond localhost.

## Design language

Playful language-app look (inspired by popular mobile learning apps, no third-party assets): Nunito, a green primary (`brand-*` / `duo-*` colours in `tailwind.config.js`), chunky buttons with a solid bottom edge that presses down (`.btn-primary`, `.btn-secondary`, `.btn-blue`, `.btn-danger`), 2px outlined cards (`.card`), answer choice cards (`.option`, `.option-selected/-correct/-wrong`), token chips (`.chip`), glossy progress bars (`.progress`) and lesson-path nodes (`.path-node`) – all defined in `resources/css/app.css`. Desktop gets a left sidebar; phones get a streak · XP · due stat bar on top and a five-tab bar at the bottom (`AuthenticatedLayout.vue`, `BottomNav.vue`, `NavIcon.vue`). The course page (`/kurs/A1`) is a winding lesson path with one node per section; the exercise player uses select → **Prüfen** → coloured feedback bar → **Weiter**.

## UI language and mobile

- **DE / EN switch** in the top bar. The choice is stored on the user (`users.locale`) and in the session, so it survives devices and logins. Page strings live in `resources/js/i18n/{de,en}.js`; server flash messages in `lang/{de,en}/messages.php`.
- **Mobile first**: bottom tab bar (Dashboard · Lessons · Quiz · Profile), 44–60 px tap targets, sticky lesson tabs, swipeable flashcards, quiz answer sheet pinned to the bottom with auto-advance on correct answers and a short vibration on mistakes.
- **Installable (PWA)**: open the site on your phone and choose *Add to Home Screen*. `public/manifest.webmanifest`, `public/sw.js` and `public/icons/*` provide the manifest, a small cache for the app shell, and the icons. Pages and quiz data are always loaded from the network.

### .env

```ini
APP_NAME=DeutschTrainer
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=deutschtrainer
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database

# PDF import pipeline
IMPORT_ENGINE=rules                   # rules (default, no API) | claude | auto
ANTHROPIC_API_KEY=                    # only for IMPORT_ENGINE=claude
ANTHROPIC_MODEL=claude-sonnet-4-5
ANTHROPIC_MAX_TOKENS=16000
IMPORT_CHUNK_LINES=120
```

## Importing a Lektion

### Web (admin)

`/import` → upload PDF + Lektion number → the job `ImportLektionPdf` runs `ImportPipeline`:

1. **`Pdf\PdfLayoutReader`** (pure PHP, `smalot/pdfparser`) reads every positioned glyph, groups glyphs into lines by their y coordinate and into cells by horizontal gaps. German and Arabic cells of a row share the same y, so the two columns are paired exactly – no more shifted Arabic column as with `pdftotext`. Arabic presentation forms are folded, bidi runs are re-ordered (right-aligned cells are RTL paragraphs) and mirrored brackets are restored. Bold/size per glyph is kept to tell section headers (h2/h3) from table rows.
2. **`RuleBasedLektionParser`** walks the lines: bold headers at the left margin define sections and the word category; `Deutsch | العربية` tables become words (article, plural ending, `(Pl.)`, `¨-er` …, verb meta); the `Land – Sprache – Nationalität` table becomes country/language/nationality entries; the `Thema | Person A | Person B | العربية` table becomes dialog lines (Sie/du questions are split from `Wie heißen Sie? / Wie heißt du?` or `Woher kommen Sie / kommst du?`); conjugation tables (verbs as columns, `ich … sie/Sie` as rows) become `conjugations` and Markdown tables inside grammar notes; remaining text becomes grammar notes. Regular verbs from the word lists without a table get a generated present tense.
3. The payload is validated against `resources/schemas/import.schema.json` and lands in `import_previews`; the preview page lets you fix German/Arabic/article/plural inline, change type/category, delete rows, then **Confirm & save**. Words that already exist in the same Lektion (same German, case-insensitive) are skipped. Parser warnings (rows it could not interpret) are shown at the top of the raw text.

New Lektion PDFs just need the same layout as Lektion 1 (`DaF_kompakt_A1_Lektion1_Resume_DE-AR.pdf`, generated with wkhtmltopdf): bold section titles `Thema – العنوان`, tables with a `Deutsch | العربية` header, and the optional Länder, Redemittel and Grammatik blocks.

Set `IMPORT_ENGINE=claude` (plus `ANTHROPIC_API_KEY`) to send the layout text to Claude instead; `auto` uses Claude only when a key is present.

### CLI

```bash
php artisan import:lektion path/to/Lektion2.pdf 2 --title="Lektion 2"
php artisan import:lektion path/to/Lektion2.pdf 2 --dry-run --dump=lektion2.json   # parse only
php artisan import:lektion lektion2.json 2 --json                                   # save an already-parsed payload
```

The CLI goes straight from PDF to database (no preview step) and prints the words-per-category table. `--text=layout.txt` dumps the reader output, handy when a new PDF is not recognised.

### Lektion 1 seed

`database/seeders/data/lektion1.json` is the import payload for `DaF_kompakt_A1_Lektion1_Resume_DE-AR.pdf` (207 words, 34 conjugations, 14 dialog lines, 6 grammar notes). `LektionOneSeeder` runs it through the same schema validator and persister as a normal import. To re-run the real pipeline on the PDF: `php artisan import:lektion database/seeders/data/lektion1.pdf 1` (the rule-based parser reproduces the seed: 207 words, 14 dialog lines, all conjugation tables).

## Quiz engine

`app/Services/Quiz/QuizGenerator.php` builds a session from a word pool:

| Type | Shown | Answer | Applies to |
|---|---|---|---|
| `ar_to_de` | Arabic | 4 German options | all |
| `de_to_ar` | German | 4 Arabic options | all |
| `article` | noun | der / die / das | nouns with a valid article |
| `plural` | singular | 4 plural options | nouns with a plural (takes an `article` slot with probability `plural_share`) |
| `conjugation` | `wohnen – du ___` | 4 forms | verbs with a conjugation row; irregular verbs weighted ×3 |
| `typing` | Arabic | free text, case-insensitive, article optional, `ue = ü` etc. | all |
| `dialog` | question line | 4 answers | Redemittel table (replaces up to 2 `de_to_ar` slots) |

Default mix 40 / 25 / 15 / 10 / 10 (`config/trainer.php`). A word is never asked twice in a session. Distractors never equal the answer and come from the same word type: same Lektion first, then global; only when the type has fewer than four members does it fall back to other types.

Modes: `/quiz/lektion/{id}` (20 questions, only that Lektion; ≥ 80 % marks it completed), `/quiz/global` (50 % due or level ≤ 1, 30 % random, 20 % last Lektion), `/quiz/weak` (wrong > correct), plus `POST /quiz/words` for "Retry wrong words" and "Practice these". Answers are checked server-side via `POST /quiz/{session}/answer` (JSON) which also updates `user_word_progress`. Keyboard: 1–4 select, Enter next.

Progress (`ProgressService`): correct → level + 1 (max 5) with next review in 1, 3, 7, 14, 30 days; wrong → level 0, due today. Mastered = level ≥ 4.

## Tests

```bash
php artisan test
```

Covers: QuizGenerator (no repeats, distractor validity and type, ratios, article/conjugation/dialog questions, weak/global modes), ProgressService (SM-2-lite), AnswerChecker (Umlaut/article tolerance), PluralExpander, the rule-based import on the real Lektion 1 PDF (reader row pairing, words, Länder table, conjugations, dialog, grammar notes, CLI import into the DB), import schema validation + Claude retry-once behaviour (Anthropic HTTP faked), persister dedup, the end-to-end quiz flow and admin gating, the A1 content files (all 8 lessons valid, every word has English + Arabic, every referenced image exists) and the A1 learner journey (seeder idempotency, course dashboard, lesson page, exercise set with feedback/retry, vocab session → spaced repetition, lesson test → completion, practice/revision/final sessions, engine checks for every item type). Tests use in-memory SQLite.

## Pages

Dashboard (per-Lektion mastery, best score, completed badge; global totals, due today, daily streak, XP; 30-day chart; weakest 10 with "practice these"), Lektionen list, Lektion detail (words by category with inline add/edit/delete, flashcard flip mode, conjugation tables, grammar notes, dialog table), Quiz, per-word history page, Import (admin). Dark-mode toggle in the nav; Arabic is rendered RTL in Noto Naskh Arabic, German in Inter.

## A1 course (DaF kompakt neu A1)

A structured 8-lesson A1 course built from the table of contents and vocabulary lists of *DaF kompakt neu A1* (Klett). The textbook was read page by page; see `docs/a1-source-inventory.md` (page log), `docs/a1-curriculum-map.md` (lesson → pages/topics/grammar) and `docs/a1-implementation-plan.md`. Only the A1 level is implemented, but the schema is level-aware (`lektionen.level`, unique per level + number, `Lektion::level('A1')`), so A2/B1 can be added later as new content folders.

### Content as files

```
resources/content/a1/lektion-0N/
  meta.json       level, number, slug, title, subtitle, source_pages, objectives (de/en), revision_md
  vocab.json      groups → items  ["die Handynummer, -n", "mobile number", "رقم الهاتف", {image, example_de, example_en, …}]
  grammar.json    notes (markdown, de + en) and verb conjugations
  dialog.json     lines (topic, question_formal, question_informal, answer, arabic, english)
  readings.json   original A1 reading texts (markdown) + English translation + comprehension questions
  exercises.json  20–45 exercises per lesson (mc, article, gap, order, match, translate, plural, conjugation, dialog, listen, write)
```

Textbook notation (`der Tisch, -e`, `(nur Sg.)`, `¨er`, `(Pl.)`) is parsed by `App\Services\Course\VocabEntryFactory`. The vocabulary lists and grammar topics follow the textbook; reading texts, dialogues, examples and exercises are **original** material written for this app (the textbook's texts, songs, poems, photos and illustrations are copyrighted and are not reproduced).

```bash
php artisan db:seed --class=A1CourseSeeder   # idempotent: upserts by (level, number) / (lektion, german, type) / exercise key
php artisan course:images                     # copies Twemoji SVGs (CC-BY 4.0) into public/images/vocab/
node scripts/vocab-icons.mjs                  # icons for words without an emoji (Fluent Emoji, Noto, OpenMoji, Tabler, Game-icons – all free)
```

Words without a suitable emoji get an icon from a free set via `resources/content/a1/icons-manual.json` (hand-reviewed, English phrase → `set:name`) plus exact-name matches; the script writes `resources/content/a1/icons.json` (overlay read by `ContentLoader`) and the SVGs. Licenses per set are listed in `public/images/vocab/ATTRIBUTION.md`.

The seeder validates every lesson (`ContentLoader::validate`) before writing and never deletes user data. Vocabulary images are Twemoji SVGs selected per word (`image` option = Unicode hex); attribution in `public/images/vocab/ATTRIBUTION.md`. Words without a suitable emoji get an initial-letter tile (`VocabImage.vue`).

### Pages and routes

| Route | Page |
|---|---|
| `/kurs/A1` | Course dashboard: completion, current lesson + next section, lessons grid, recent activity, due words, final test |
| `/lektionen/{id}` | Lesson with sections Lernziele · Wortschatz (image cards/list/flashcards/verbs, 11 practice modes, ★ difficult) · Grammatik · Sätze und Dialoge · Lesen · Übungen · Wiederholung · Lektionstest |
| `/uebungen/{session}` | Exercise player: instant feedback with correct answer + explanation, retry wrong items, listening via browser TTS (`useSpeech`), writing tasks checked against a model answer + checklist |
| `/ueben` | Practice by lesson / section / type; difficult words, wrong exercises, due words |
| `/wiederholung` | Spaced-repetition queue, difficult words, wrong exercises, per-lesson summaries |
| `POST /uebungen` | `kind` = set · vocab · test · practice · revision · final |

Progress: `lesson_progress` (one row per user/lesson/section; set scores and test best/attempts in `data`), `exercise_sessions` / `exercise_attempts` (server-side solutions, never sent to the client), and the existing `user_word_progress` SM-2-lite schedule (vocabulary items feed it; `is_difficult` flag). A lesson counts as completed when every section is done and the lesson test reaches ≥ 80 %. The A1 final assessment is a cumulative 40-item self-test over all lessons.

## Deployment on shared hosting (Hostinger)

The production instance runs on Hostinger shared hosting without root, Node, cron or external binaries. Recipe:

```bash
# on the server (SSH)
cd ~/domains/<site>/ && git clone https://github.com/Rochdi7/deutchtrainer.git deutschtrainer
cd deutschtrainer && composer install --no-dev --optimize-autoloader && php artisan package:discover
cp .env.example .env    # APP_ENV=production, APP_DEBUG=false, APP_URL, DB_CONNECTION=sqlite, QUEUE_CONNECTION=sync
touch database/database.sqlite && php artisan key:generate --force && php artisan migrate --seed --force
ln -sfn ../storage/app/public public/storage          # artisan storage:link needs exec(), which is disabled
cd .. && rm -rf public_html && ln -s deutschtrainer/public public_html
```

Frontend assets are built locally and uploaded (`npm run build && tar -czf build.tar.gz -C public build`, then `deploy.sh` on the server extracts them). `QUEUE_CONNECTION=sync` runs the import inside the upload request, which the rule-based parser finishes in well under a second.
