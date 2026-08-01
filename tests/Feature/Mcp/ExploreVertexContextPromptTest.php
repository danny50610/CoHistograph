<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Prompts\ExploreVertexContextPrompt;
use App\Mcp\Servers\CoHistographServer;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class ExploreVertexContextPromptTest extends TestCase
{
    #[Test]
    public function prompt_is_registered_on_the_server(): void
    {
        $prompts = (new ReflectionClass(CoHistographServer::class))
            ->getProperty('prompts')
            ->getDefaultValue();

        $this->assertContains(ExploreVertexContextPrompt::class, $prompts);
    }

    #[Test]
    public function prompt_guides_read_only_vertex_exploration(): void
    {
        $response = CoHistographServer::prompt(ExploreVertexContextPrompt::class, [
            'query' => 'Xinhai Revolution',
            'vertex_type_label' => 'event',
        ]);

        $response
            ->assertOk()
            ->assertName('explore-vertex-context')
            ->assertSee('Xinhai Revolution')
            ->assertSee('event')
            ->assertSee('search-vertices')
            ->assertSee('get-vertex-detail')
            ->assertSee('list-vertex-neighbors')
            ->assertSee('Do not create or edit revisions');
    }

    #[Test]
    public function prompt_requires_query_argument(): void
    {
        $response = CoHistographServer::prompt(ExploreVertexContextPrompt::class, []);

        $response->assertHasErrors();
    }

    #[Test]
    public function prompt_works_without_vertex_type_label(): void
    {
        $response = CoHistographServer::prompt(ExploreVertexContextPrompt::class, [
            'query' => 'Sun Yat-sen',
        ]);

        $response
            ->assertOk()
            ->assertSee('Sun Yat-sen')
            ->assertSee('search-vertex-types');
    }
}
