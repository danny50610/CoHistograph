<?php

namespace Tests\Feature;

use Tests\TestCase;

class FooterTest extends TestCase
{
    public function test_footer_contains_powered_by_cohistograph_link(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Powered by', false)
            ->assertSee('CoHistograph', false)
            ->assertSee('https://github.com/danny50610/CoHistograph', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }
}
