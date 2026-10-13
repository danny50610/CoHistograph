<?php

namespace Tests\Unit\Support;

use App\Support\Changelog;
use App\Support\ChangelogEntry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ChangelogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/changelog-'.uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);

        parent::tearDown();
    }

    public function test_releases_are_sorted_by_date_then_version_descending(): void
    {
        $this->write('v1.0.0.md', '2026-01-01', 'oldest');
        $this->write('v1.2.0.md', '2026-03-01', 'newer');
        $this->write('v1.1.0.md', '2026-02-01', 'middle');
        $this->write('v1.2.1.md', '2026-03-01', 'same day patch');
        $this->write('notes.md', '2026-12-01', 'ignored');

        $versions = array_map(
            fn (ChangelogEntry $release): string => $release->version,
            (new Changelog($this->directory))->releases(),
        );

        $this->assertSame(['v1.2.1', 'v1.2.0', 'v1.1.0', 'v1.0.0'], $versions);
    }

    public function test_renders_markdown_and_strips_raw_html(): void
    {
        $this->write('v1.0.0.md', '2026-01-02', "# 標題\n\n**粗體** 與 <b>標籤</b>\n\n<script>alert(1)</script>\n\n[點我](javascript:alert(1))\n\n---\n\n內文");

        $release = (new Changelog($this->directory))->releases()[0];
        $html = $release->html->toHtml();

        $this->assertSame('v1.0.0', $release->version);
        $this->assertSame('2026-01-02', $release->date->toDateString());
        $this->assertSame('https://github.com/danny50610/CoHistograph/releases/tag/v1.0.0', $release->githubUrl());
        $this->assertStringContainsString('<h3>標題</h3>', $html);
        $this->assertStringContainsString('<strong>粗體</strong>', $html);
        $this->assertStringContainsString('<hr', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_aligns_shallowest_heading_to_h3(): void
    {
        $this->write('v1.0.0.md', '2026-01-02', "# 新功能\n\n## 子項目\n\n```\n# 不要動\n```\n");
        $this->write('v0.9.0.md', '2026-01-01', "## 新功能\n\n### 子項目\n");

        $releases = (new Changelog($this->directory))->releases();

        $this->assertStringContainsString('<h3>新功能</h3>', $releases[0]->html->toHtml());
        $this->assertStringContainsString('<h4>子項目</h4>', $releases[0]->html->toHtml());
        $this->assertStringContainsString('# 不要動', $releases[0]->html->toHtml());
        $this->assertStringNotContainsString('<h1>', $releases[0]->html->toHtml());
        $this->assertStringContainsString('<h3>新功能</h3>', $releases[1]->html->toHtml());
        $this->assertStringContainsString('<h4>子項目</h4>', $releases[1]->html->toHtml());
    }

    public function test_missing_directory_returns_no_releases(): void
    {
        $this->assertSame([], (new Changelog($this->directory.'/missing'))->releases());
    }

    public function test_file_without_date_throws(): void
    {
        file_put_contents($this->directory.'/v1.0.0.md', "沒有日期\n");

        $this->expectException(InvalidArgumentException::class);

        (new Changelog($this->directory))->releases();
    }

    public function test_invalid_date_throws(): void
    {
        $this->write('v1.0.0.md', '2026-13-01', 'bad');

        $this->expectException(InvalidArgumentException::class);

        (new Changelog($this->directory))->releases();
    }

    private function write(string $filename, string $date, string $body): void
    {
        file_put_contents($this->directory.'/'.$filename, "---\ndate: {$date}\n---\n\n{$body}\n");
    }
}
