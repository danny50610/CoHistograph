<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use Tests\TestCase;

class CustomContentSlotsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $finder = View::getFinder();
        $this->assertInstanceOf(FileViewFinder::class, $finder);
        $finder->prependLocation(base_path('tests/fixtures/views'));
    }

    public function test_custom_content_slots_render_above_and_below_main_content(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('<!-- custom-content-before-marker -->', false)
            ->assertSee('<!-- custom-content-after-marker -->', false);
    }

    public function test_custom_content_slots_appear_around_main_content_area(): void
    {
        $content = $this->get(route('faq'))
            ->assertOk()
            ->getContent();

        $beforePosition = strpos($content, '<!-- custom-content-before-marker -->');
        $afterPosition = strpos($content, '<!-- custom-content-after-marker -->');
        $mainContentPosition = strpos($content, 'min-height: calc(100vh');
        $footerPosition = strpos($content, 'Powered by');

        $this->assertNotFalse($beforePosition);
        $this->assertNotFalse($afterPosition);
        $this->assertNotFalse($mainContentPosition);
        $this->assertNotFalse($footerPosition);

        $this->assertLessThan($mainContentPosition, $beforePosition);
        $this->assertGreaterThan($mainContentPosition, $afterPosition);
        $this->assertLessThan($footerPosition, $afterPosition);
    }
}
