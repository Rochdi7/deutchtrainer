# DeutschTrainer

Duolingo-style vocabulary trainer for the **DaF kompakt A1** course (German–Arabic). Upload one PDF word list per Lektion, let Claude turn it into structured vocabulary, then drill it with multiple-choice, article, plural, conjugation, typing and dialog questions. Progress uses a light SM-2 schedule.

Stack: Laravel 12 · Inertia + Vue 3 · Tailwind (dark mode) · MySQL · Breeze auth · database queue · `spatie/pdf-to-text` · Anthropic Messages API via Laravel's `Http` client · Chart.js.

## Setup

Requirements: PHP ≥ 8.2 (with `intl`, `mbstring`, `pdo_mysql`), Composer, Node ≥ 20, MySQL/MariaDB, and `pdftotext` (poppler). On Windows the Git for Windows install ships `pdftotext.exe`; on Debian/Ubuntu `apt install poppler-utils`; on macOS `brew install poppler`.

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
ANTHROPIC_API_KEY=sk-ant-...          # required for the import (UI and CLI)
ANTHROPIC_MODEL=claude-sonnet-4-5     # model used for parsing
ANTHROPIC_MAX_TOKENS=16000
PDFTOTEXT_PATH=                       # leave empty if pdftotext is on PATH
                                      # e.g. "C:/Program Files/Git/mingw64/bin/pdftotext.exe"
IMPORT_CHUNK_LINES=120
```

## Importing a Lektion

### Web (admin)

`/import` → upload PDF + Lektion number → the job `ImportLektionPdf` runs `pdftotext -layout -enc UTF-8`, normalises the text (NFKC folds Arabic presentation forms, bidi marks are stripped), splits it into ~120-line chunks and sends each to Claude with a format-aware system prompt (temperature 0). Every reply is validated against `resources/schemas/import.schema.json`; an invalid reply is sent back once with the validation errors. The merged result lands in `import_previews`, and the preview page lets you fix German/Arabic/article/plural inline, change type/category, delete rows, then **Confirm & save**. Words that already exist in the same Lektion (same German, case-insensitive) are skipped.

### CLI

```bash
php artisan import:lektion path/to/Lektion2.pdf 2 --title="Lektion 2"
php artisan import:lektion path/to/Lektion2.pdf 2 --dry-run --dump=lektion2.json   # parse only
php artisan import:lektion lektion2.json 2 --json                                   # save an already-parsed payload
```

The CLI goes straight from PDF to database (no preview step) and prints the words-per-category table.

### Lektion 1 seed

`database/seeders/data/lektion1.json` is the import payload for `DaF_kompakt_A1_Lektion1_Resume_DE-AR.pdf` (207 words, 34 conjugations, 14 dialog lines, 6 grammar notes). `LektionOneSeeder` runs it through the same schema validator and persister as a normal import, so `migrate --seed` works without an API key. To re-run the real pipeline on the PDF: `php artisan import:lektion database/seeders/data/lektion1.pdf 1`.

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

Covers: QuizGenerator (no repeats, distractor validity and type, ratios, article/conjugation/dialog questions, weak/global modes), ProgressService (SM-2-lite), AnswerChecker (Umlaut/article tolerance), PluralExpander, import schema validation + retry-once behaviour (Anthropic HTTP faked), persister dedup, the end-to-end quiz flow and admin gating. Tests use in-memory SQLite.

## Pages

Dashboard (per-Lektion mastery, best score, completed badge; global totals, due today, daily streak, XP; 30-day chart; weakest 10 with "practice these"), Lektionen list, Lektion detail (words by category with inline add/edit/delete, flashcard flip mode, conjugation tables, grammar notes, dialog table), Quiz, per-word history page, Import (admin). Dark-mode toggle in the nav; Arabic is rendered RTL in Noto Naskh Arabic, German in Inter.
