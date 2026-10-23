<?php

namespace App\Services\Course;

use App\Models\Word;

/**
 * Vocabulary images are Twemoji SVGs (graphics CC-BY 4.0, https://github.com/jdecked/twemoji).
 * The files referenced by the content are copied once from node_modules/@twemoji/svg into
 * public/images/vocab/ and committed, so production (no Node) serves them as static files.
 */
class VocabImageService
{
    public const DIR = 'images/vocab';

    public function sourceDir(): string
    {
        return base_path('node_modules/@twemoji/svg');
    }

    /** Copy the SVG for a public path if missing. Returns true when the file is now available. */
    public function ensure(string $publicPath): bool
    {
        $target = public_path($publicPath);
        if (is_file($target)) {
            return true;
        }
        $source = $this->sourceDir().'/'.basename($publicPath);
        if (! is_file($source)) {
            return false;
        }
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        return copy($source, $target);
    }

    /**
     * Make sure every word image exists; unset images that cannot be provided.
     *
     * @return array{copied: int, present: int, missing: string[]}
     */
    public function sync(): array
    {
        $copied = 0;
        $present = 0;
        $missing = [];
        foreach (Word::whereNotNull('image')->get(['id', 'german', 'image']) as $word) {
            $existed = is_file(public_path($word->image));
            if ($this->ensure($word->image)) {
                $existed ? $present++ : $copied++;
            } else {
                $missing[] = $word->german.' ('.$word->image.')';
                $word->update(['image' => null]);
            }
        }

        return compact('copied', 'present', 'missing');
    }
}
