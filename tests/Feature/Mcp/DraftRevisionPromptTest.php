<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Prompts\DraftRevisionPrompt;
use App\Mcp\Servers\CoHistographServer;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class DraftRevisionPromptTest extends TestCase
{
    #[Test]
    public function prompt_is_registered_on_the_server(): void
    {
        $prompts = (new ReflectionClass(CoHistographServer::class))
            ->getProperty('prompts')
            ->getDefaultValue();

        $this->assertContains(DraftRevisionPrompt::class, $prompts);
    }

    #[Test]
    public function prompt_guides_draft_revision_workflow(): void
    {
        $response = CoHistographServer::prompt(DraftRevisionPrompt::class, [
            'goal' => 'Add the Xinhai Revolution event and link Sun Yat-sen',
            'title' => '新增辛亥革命',
        ]);

        $response
            ->assertOk()
            ->assertName('draft-revision')
            ->assertSee('Add the Xinhai Revolution event and link Sun Yat-sen')
            ->assertSee('新增辛亥革命')
            ->assertSee('search-vertex-types')
            ->assertSee('search-edge-types')
            ->assertSee('create-revision')
            ->assertSee('add-revision-action')
            ->assertSee('validate-revision')
            ->assertSee('Ask the user before submit-revision')
            ->assertSee('Never write Apache AGE directly');
    }

    #[Test]
    public function prompt_requires_goal_argument(): void
    {
        $response = CoHistographServer::prompt(DraftRevisionPrompt::class, []);

        $response->assertHasErrors();
    }

    #[Test]
    public function prompt_works_without_title(): void
    {
        $response = CoHistographServer::prompt(DraftRevisionPrompt::class, [
            'goal' => 'Update Sun Yat-sen birth year',
        ]);

        $response
            ->assertOk()
            ->assertSee('Update Sun Yat-sen birth year')
            ->assertSee('Derive a concise revision title');
    }
}
