<?php

namespace Tests\Feature;

use App\Support\Changelog;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_public_changelog_page_shows_releases_newest_first(): void
    {
        $content = $this->get(route('changelog'))
            ->assertOk()
            ->assertSee('更新紀錄')
            ->assertSee('<h1>v0.7.0</h1>', false)
            ->assertSee('<h3>新功能</h3>', false)
            ->assertSee('<h4>TIME 屬性型別</h4>', false)
            ->assertSee('2026-08-10')
            ->assertSee('TIME 屬性型別')
            ->assertSee('v0.1.1')
            ->assertSee('First MVP version')
            ->assertSee('https://github.com/danny50610/CoHistograph/releases/tag/v0.7.0', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->getContent();

        $this->assertLessThan(strpos($content, 'v0.6.0'), strpos($content, 'v0.7.0'));
        $this->assertLessThan(strpos($content, 'v0.3.3'), strpos($content, 'v0.4.0'));
        $this->assertMatchesRegularExpression(
            '/class="nav-link\s+active\s*" href="[^"]*\/changelog">\s*更新紀錄\s*<\/a>/',
            $content,
        );
    }

    public function test_changelog_page_shows_empty_state_when_directory_is_empty(): void
    {
        $directory = sys_get_temp_dir().'/changelog-empty-'.uniqid('', true);
        mkdir($directory);
        $this->app->instance(Changelog::class, new Changelog($directory));

        try {
            $this->get(route('changelog'))
                ->assertOk()
                ->assertSee('目前尚無更新紀錄')
                ->assertDontSee('v0.7.0');
        } finally {
            rmdir($directory);
        }
    }

    public function test_changelog_page_strips_html_from_release_notes(): void
    {
        $directory = sys_get_temp_dir().'/changelog-html-'.uniqid('', true);
        mkdir($directory);
        file_put_contents($directory.'/v1.0.0.md', "---\ndate: 2026-01-01\n---\n\n<script>alert(1)</script>\n\n[點我](javascript:alert(1))\n");
        $this->app->instance(Changelog::class, new Changelog($directory));

        try {
            $this->get(route('changelog'))
                ->assertOk()
                ->assertSee('v1.0.0')
                ->assertDontSee('alert(1)', false)
                ->assertDontSee('javascript:', false);
        } finally {
            unlink($directory.'/v1.0.0.md');
            rmdir($directory);
        }
    }
}
