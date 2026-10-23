<?php

namespace App\Console\Commands;

use App\Services\Course\VocabImageService;
use Illuminate\Console\Command;

class CourseImages extends Command
{
    protected $signature = 'course:images';

    protected $description = 'Copy the vocabulary images (Twemoji SVGs) referenced by words into public/images/vocab and list words without an image';

    public function handle(VocabImageService $images): int
    {
        $report = $images->sync();
        $this->info(sprintf('%d images present, %d copied', $report['present'], $report['copied']));
        foreach ($report['missing'] as $m) {
            $this->warn('missing: '.$m);
        }

        return self::SUCCESS;
    }
}
