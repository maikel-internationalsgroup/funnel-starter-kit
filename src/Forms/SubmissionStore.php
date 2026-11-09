<?php

declare(strict_types=1);

namespace Funnel\Forms;

use Funnel\Support\Config;

/**
 * Safety net: every submission is appended to storage/submissions/YYYY-MM.jsonl
 * BEFORE integrations run, so a failing mailbox or CRM never loses a lead.
 * Files older than storage.keep_days are deleted automatically (GDPR retention).
 */
final class SubmissionStore
{
    public static function append(Submission $submission): void
    {
        if (!Config::get('storage.store_submissions', true)) {
            return;
        }
        $dir = self::dir();
        @file_put_contents(
            $dir . '/' . date('Y-m') . '.jsonl',
            json_encode($submission->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
        self::purge();
    }

    /** @return Submission[] newest last */
    public static function all(): array
    {
        $rows = [];
        foreach (glob(self::dir() . '/*.jsonl') ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $row = json_decode($line, true);
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    private static function purge(): void
    {
        $keepDays = (int) Config::get('storage.keep_days', 90);
        if ($keepDays <= 0) {
            return;
        }
        $cutoff = time() - $keepDays * 86400;
        foreach (glob(self::dir() . '/*.jsonl') ?: [] as $file) {
            // A monthly file can go once the last day of that month is older than the cutoff.
            $month = basename($file, '.jsonl');
            $end = strtotime($month . '-01 +1 month');
            if ($end !== false && $end < $cutoff) {
                @unlink($file);
            }
        }
    }

    private static function dir(): string
    {
        $dir = FUNNEL_ROOT . '/storage/submissions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
