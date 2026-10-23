# Vocabulary images – attribution

All pictures in this folder come from free, openly licensed icon and emoji sets. No images were taken from the
textbook *DaF kompakt neu A1* (Klett); its illustrations and photos are copyrighted and are not reproduced here.

| File name pattern | Set | License | Source |
|---|---|---|---|
| `<hex>.svg` (e.g. `1f4f1.svg`) | Twemoji 15.0 | CC BY 4.0 (graphics), MIT (code) | https://github.com/jdecked/twemoji · npm `@twemoji/svg` |
| `fluent-emoji-flat-*.svg` | Microsoft Fluent Emoji (flat) | MIT | https://github.com/microsoft/fluentui-emoji · npm `@iconify-json/fluent-emoji-flat` |
| `noto-*.svg` | Google Noto Emoji | Apache 2.0 | https://github.com/googlefonts/noto-emoji · npm `@iconify-json/noto` |
| `openmoji-*.svg` | OpenMoji | CC BY-SA 4.0 | https://openmoji.org · npm `@iconify-json/openmoji` |
| `tabler-*.svg` | Tabler Icons | MIT | https://tabler.io/icons · npm `@iconify-json/tabler` |
| `game-icons-*.svg` | Game-icons.net | CC BY 3.0 | https://game-icons.net · npm `@iconify-json/game-icons` |

Licenses: CC BY 4.0 https://creativecommons.org/licenses/by/4.0/ · CC BY-SA 4.0 https://creativecommons.org/licenses/by-sa/4.0/ ·
CC BY 3.0 https://creativecommons.org/licenses/by/3.0/ · MIT https://opensource.org/licenses/MIT · Apache 2.0 https://www.apache.org/licenses/LICENSE-2.0

How files get here:

- Twemoji code points are referenced per word in `resources/content/a1/lektion-*/vocab.json` (`img` option) and copied by
  `php artisan course:images` (`App\Services\Course\VocabImageService`).
- Iconify set icons are chosen in `resources/content/a1/icons-manual.json` (hand-reviewed) or matched by exact name, and
  written by `node scripts/vocab-icons.mjs` into `resources/content/a1/icons.json` (overlay read by `ContentLoader`) and
  this folder. The SVG files are committed so the production server (no Node) serves them as static assets.
