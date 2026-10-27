#!/usr/bin/env node
/**
 * Assign free icons to vocabulary words that have no Twemoji picture.
 *
 * Sources (all free, attribution in public/images/vocab/ATTRIBUTION.md):
 *   fluent-emoji-flat (MIT) · noto (Apache 2.0) · openmoji (CC BY-SA 4.0) · tabler (MIT) · game-icons (CC BY 3.0)
 *
 * Strategy: match the English translation (whole phrase, then head noun) against icon names,
 * colourful emoji sets first, mono icon sets last. Results go to resources/content/a1/icons.json
 * (overlay read by ContentLoader) and the SVGs are written to public/images/vocab/<set>-<name>.svg.
 *
 * Usage: node scripts/vocab-icons.mjs [--dry] [--only lektion-03]
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1')), '..');
const CONTENT = path.join(ROOT, 'resources/content/a1');
const OUT = path.join(ROOT, 'public/images/vocab');
const OVERLAY = path.join(CONTENT, 'icons.json');
const dry = process.argv.includes('--dry');
const only = process.argv.includes('--only') ? process.argv[process.argv.indexOf('--only') + 1] : null;

const SETS = ['fluent-emoji-flat', 'noto', 'openmoji', 'tabler', 'game-icons'];
const MONO = new Set(['tabler', 'game-icons']);
const sets = Object.fromEntries(SETS.map((s) => [s, require(`@iconify-json/${s}/icons.json`)]));

// Manual overrides / blocks reviewed by hand (English phrase -> set:name, or null to skip).
const MANUAL = JSON.parse(fs.readFileSync(path.join(CONTENT, 'icons-manual.json'), 'utf8'));

const STOP = new Set(['the', 'a', 'an', 'to', 'be', 'of', 'and', 'or', 'in', 'on', 'at', 'for', 'with', 'not', 'one', 'sb', 'sth', 'something', 'somebody', 'someone', 'so', 'that', 'this', 'is', 'are', 'it', 'as', 'by', 'from', 'very', 'all', 'no', 'yes', 'only', 'also', 'still', 'already', 'then', 'now', 'here', 'there', 'please', 'more', 'other']);
const BAD_ICON = /(skin-tone|flag-|-flag$|regional-indicator|keycap|^[0-9]+$|button$|-button-|symbol$|arrow|chevron|brand-|-off$|-x$|-plus$|-minus$|-check$|-filled$)/;

function keywords(en) {
    let s = en.toLowerCase().replace(/\(.*?\)/g, ' ').replace(/[^a-z\s-]/g, ' ');
    s = s.replace(/\bto\b/g, ' ');
    const phrases = s.split(/[,/;]|\bor\b/).map((p) => p.trim()).filter(Boolean);
    const out = [];
    for (const p of phrases) {
        const words = p.split(/\s+/).filter((w) => w && !STOP.has(w));
        if (!words.length) continue;
        out.push(words.join('-'));
        if (words.length > 1) out.push(words[words.length - 1]);
    }
    return [...new Set(out)];
}

function find(name) {
    for (const set of SETS) {
        const j = sets[set];
        if (j.icons[name] && !BAD_ICON.test(name)) return `${set}:${name}`;
        if (j.aliases?.[name] && !BAD_ICON.test(name)) return `${set}:${name}`;
    }
    return null;
}
function findLoose(word) {
    // whole word inside a hyphenated name, prefer colourful sets and short names
    for (const set of SETS) {
        const names = Object.keys(sets[set].icons).filter((n) => n.split('-').includes(word) && !BAD_ICON.test(n));
        if (names.length) {
            names.sort((a, b) => a.length - b.length);
            return `${set}:${names[0]}`;
        }
    }
    return null;
}

function iconExists(ref) {
    // refs without a set are hand-drawn SVGs already in public/images/vocab (e.g. "prep-auf")
    if (!ref.includes(':')) return fs.existsSync(path.join(OUT, `${ref}.svg`));
    const [set, name] = ref.split(':');
    const j = sets[set];
    return !!j && (!!j.icons[name] || !!j.aliases?.[name]);
}

function svgFor(ref) {
    const [set, name] = ref.split(':');
    const j = sets[set];
    let icon = j.icons[name];
    if (!icon && j.aliases?.[name]) icon = { ...j.icons[j.aliases[name].parent], ...j.aliases[name] };
    const w = icon.width ?? j.width ?? 24;
    const h = icon.height ?? j.height ?? 24;
    const color = MONO.has(set) ? ' color="#3f3f46"' : '';
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}"${color}>${icon.body}</svg>\n`;
}

const overlay = fs.existsSync(OVERLAY) ? JSON.parse(fs.readFileSync(OVERLAY, 'utf8')) : {};
const stats = { words: 0, hadImage: 0, matched: 0, manual: 0, unmatched: 0, bySet: {} };
const report = [];

function assign(dir, key, en, img) {
    stats.words++;
    if (img) {
        stats.hadImage++;
        return;
    }
    let ref = null;
    if (Object.prototype.hasOwnProperty.call(MANUAL, en)) {
        ref = MANUAL[en];
        if (ref && !iconExists(ref)) {
            console.warn(`invalid manual icon for "${en}": ${ref}`);
            stats.invalid = (stats.invalid || 0) + 1;
            ref = null;
        }
        if (ref) stats.manual++;
    } else {
        const kws = keywords(en);
        for (const k of kws) {
            ref = find(k);
            if (ref) break;
        }
        // Loose (substring) matching is only used when explicitly allowed: it produced too many
        // false friends ("counter" -> geiger-counter). Exact phrase / head-noun matches only.
        if (!ref && process.argv.includes('--loose')) {
            for (const k of kws) {
                if (k.length < 4 || k.includes('-')) continue;
                ref = findLoose(k);
                if (ref) break;
            }
        }
    }
    if (!ref) {
        stats.unmatched++;
        delete overlay[dir][key];
        report.push(`${dir}\t${key}\t${en}\t-`);
        return;
    }
    stats.matched++;
    const set = ref.includes(':') ? ref.split(':')[0] : 'custom';
    stats.bySet[set] = (stats.bySet[set] || 0) + 1;
    overlay[dir][key] = ref;
    report.push(`${dir}\t${key}\t${en}\t${ref}`);
    if (!dry) {
        const file = path.join(OUT, `${ref.replace(':', '-')}.svg`);
        if (!fs.existsSync(file)) fs.writeFileSync(file, svgFor(ref));
    }
}

for (const dir of fs.readdirSync(CONTENT).filter((d) => d.startsWith('lektion-')).sort()) {
    if (only && dir !== only) continue;
    const vocab = JSON.parse(fs.readFileSync(path.join(CONTENT, dir, 'vocab.json'), 'utf8'));
    overlay[dir] = overlay[dir] || {};
    for (const group of vocab.groups || []) {
        for (const item of group.items || []) {
            const [raw, en] = item;
            // key = "article german" without the plural suffix, same as ContentLoader builds it
            const key = raw.replace(/,\s*.*$/, '').replace(/\s*\(.*?\)\s*/g, ' ').trim();
            assign(dir, key, en, (item[3] || {}).img);
        }
    }
    // extras.json (Lektion 1): words seeded elsewhere, keyed by their German form as-is
    const extrasFile = path.join(CONTENT, dir, 'extras.json');
    if (fs.existsSync(extrasFile)) {
        const extras = JSON.parse(fs.readFileSync(extrasFile, 'utf8'));
        for (const [german, x] of Object.entries(extras.words || {})) {
            if (x.en) assign(dir, german, x.en, x.img);
        }
    }
}

if (!dry) fs.writeFileSync(OVERLAY, JSON.stringify(overlay, null, 2) + '\n');
fs.writeFileSync(path.join(ROOT, 'storage/app/vocab-icons-report.tsv'), report.join('\n') + '\n');
console.log(JSON.stringify(stats, null, 2));
console.log('report: storage/app/vocab-icons-report.tsv');
