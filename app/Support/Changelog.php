<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * 每個版本一個 Markdown 檔，檔名為 vX.Y.Z.md，開頭為 date front matter：
 *
 * ---
 * date: YYYY-MM-DD
 * ---
 */
final readonly class Changelog
{
    /**
     * @var array{html_input: string, allow_unsafe_links: bool}
     */
    private const MARKDOWN_OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ];

    public function __construct(private string $directory) {}

    /**
     * @return list<ChangelogEntry>
     */
    public function releases(): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $filenames = scandir($this->directory);
        if ($filenames === false) {
            return [];
        }

        $releases = [];
        foreach ($filenames as $filename) {
            if (! preg_match('/^v\d+\.\d+\.\d+\.md$/', $filename)) {
                continue;
            }

            $releases[] = $this->parse($filename);
        }

        usort($releases, function (ChangelogEntry $left, ChangelogEntry $right): int {
            $byDate = $right->date->toDateString() <=> $left->date->toDateString();
            if ($byDate !== 0) {
                return $byDate;
            }

            return version_compare($right->version, $left->version);
        });

        return $releases;
    }

    private function parse(string $filename): ChangelogEntry
    {
        $contents = file_get_contents($this->directory.DIRECTORY_SEPARATOR.$filename);
        if ($contents === false) {
            throw new InvalidArgumentException("無法讀取更新紀錄檔 {$filename}。");
        }

        if (! preg_match("/\A---\R(?<frontmatter>.*?)\R---(?:\R(?<body>.*))?\z/s", $contents, $matches)) {
            throw new InvalidArgumentException("更新紀錄檔 {$filename} 缺少開頭的 date。");
        }

        if (! preg_match('/^date:\s*(?<date>\d{4}-\d{2}-\d{2})\s*$/m', $matches['frontmatter'], $dateMatch)) {
            throw new InvalidArgumentException("更新紀錄檔 {$filename} 的 date 必須是 YYYY-MM-DD。");
        }

        $date = $this->date($filename, $dateMatch['date']);
        $html = Str::markdown($this->normalizeHeadings(trim($matches['body'] ?? '')), self::MARKDOWN_OPTIONS);

        return new ChangelogEntry(
            version: substr($filename, 0, -3),
            date: $date,
            html: new HtmlString($html),
        );
    }

    private function date(string $filename, string $value): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException $exception) {
            throw new InvalidArgumentException("更新紀錄檔 {$filename} 的 date 必須是 YYYY-MM-DD。", previous: $exception);
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("更新紀錄檔 {$filename} 的 date 必須是 YYYY-MM-DD。");
        }

        return $date;
    }

    /**
     * 版號由頁面以 h1 顯示，內文最淺的標題（例如「新功能」）對齊為 h3，較深的標題一併下移。
     */
    private function normalizeHeadings(string $markdown): string
    {
        $lines = preg_split("/\R/u", $markdown);
        if ($lines === false) {
            return $markdown;
        }

        $inFence = false;
        $fenceCharacter = null;
        $minLevel = null;
        $headingLines = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^(`{3,}|~{3,})/', $line, $fence) === 1) {
                $marker = $fence[1];
                if (! $inFence) {
                    $inFence = true;
                    $fenceCharacter = $marker[0];
                } elseif (str_starts_with($marker, (string) $fenceCharacter)) {
                    $inFence = false;
                    $fenceCharacter = null;
                }

                continue;
            }

            if ($inFence || preg_match('/^(#{1,6})(?:\s+|$)/', $line, $heading) !== 1) {
                continue;
            }

            $level = strlen($heading[1]);
            $headingLines[$index] = $level;
            $minLevel = $minLevel === null ? $level : min($minLevel, $level);
        }

        if ($minLevel === null || $minLevel === 3) {
            return $markdown;
        }

        $shift = 3 - $minLevel;
        foreach ($headingLines as $index => $level) {
            $newLevel = min(6, max(1, $level + $shift));
            $replaced = preg_replace('/^#{1,6}/', str_repeat('#', $newLevel), $lines[$index], 1);
            if (is_string($replaced)) {
                $lines[$index] = $replaced;
            }
        }

        return implode("\n", $lines);
    }
}
