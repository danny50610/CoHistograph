<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use Tests\TestCase;

class CustomContentSlotsTest extends TestCase
{
    public function test_pages_render_without_optional_custom_content_views(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertDontSee('<!-- custom-head-marker -->', false)
            ->assertDontSee('<!-- custom-content-top-marker -->', false)
            ->assertDontSee('<!-- custom-content-bottom-marker -->', false);
    }

    public function test_optional_custom_content_slots_render_when_views_exist(): void
    {
        $this->prependCustomContentFixtures();

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('<!-- custom-head-marker -->', false)
            ->assertSee('<!-- custom-content-top-marker -->', false)
            ->assertSee('<!-- custom-content-bottom-marker -->', false);
    }

    public function test_optional_custom_head_slot_appears_early_in_html_head(): void
    {
        $this->prependCustomContentFixtures();

        $content = $this->get(route('faq'))
            ->assertOk()
            ->getContent();

        $headOpenPosition = strpos($content, '<head>');
        $headMarkerPosition = strpos($content, '<!-- custom-head-marker -->');
        $titlePosition = strpos($content, '<title>');
        $headClosePosition = strpos($content, '</head>');

        $this->assertNotFalse($headOpenPosition);
        $this->assertNotFalse($headMarkerPosition);
        $this->assertNotFalse($titlePosition);
        $this->assertNotFalse($headClosePosition);

        $this->assertGreaterThan($headOpenPosition, $headMarkerPosition);
        $this->assertLessThan($titlePosition, $headMarkerPosition);
        $this->assertLessThan($headClosePosition, $headMarkerPosition);
    }

    public function test_optional_custom_content_slots_appear_around_main_content_area(): void
    {
        $this->prependCustomContentFixtures();

        $content = $this->get(route('faq'))
            ->assertOk()
            ->getContent();

        $topPosition = strpos($content, '<!-- custom-content-top-marker -->');
        $bottomPosition = strpos($content, '<!-- custom-content-bottom-marker -->');
        $mainContentPosition = strpos($content, 'min-height: calc(100vh');
        $footerPosition = strpos($content, 'Powered by');

        $this->assertNotFalse($topPosition);
        $this->assertNotFalse($bottomPosition);
        $this->assertNotFalse($mainContentPosition);
        $this->assertNotFalse($footerPosition);

        $this->assertLessThan($mainContentPosition, $topPosition);
        $this->assertGreaterThan($mainContentPosition, $bottomPosition);
        $this->assertLessThan($footerPosition, $bottomPosition);
    }

    private function prependCustomContentFixtures(): void
    {
        $finder = View::getFinder();
        $this->assertInstanceOf(FileViewFinder::class, $finder);
        $finder->prependLocation(base_path('tests/fixtures/views'));
    }
}
