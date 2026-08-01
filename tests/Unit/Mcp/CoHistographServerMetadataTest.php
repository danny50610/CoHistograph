<?php

namespace Tests\Unit\Mcp;

use App\Enums\RevisionActionType;
use App\Mcp\Concerns\ProvidesRevisionActionSchema;
use App\Mcp\Servers\CoHistographServer;
use App\Mcp\Tools\Revision\AddRevisionActionTool;
use App\Mcp\Tools\Revision\SubmitRevisionTool;
use App\Mcp\Tools\Schema\SearchVertexTypesTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Instructions;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class CoHistographServerMetadataTest extends TestCase
{
    #[Test]
    public function server_instructions_document_workflow_and_all_action_types(): void
    {
        $instructions = $this->attributeValue(CoHistographServer::class, Instructions::class);

        $this->assertStringContainsString('Never write Apache AGE directly', $instructions);
        $this->assertStringContainsString('Recommended workflow:', $instructions);
        $this->assertStringContainsString('validate-revision', $instructions);
        $this->assertStringContainsString('submit-revision', $instructions);
        $this->assertStringContainsString('target_age_id XOR target_ref_order', $instructions);

        foreach (RevisionActionType::values() as $action) {
            $this->assertStringContainsString($action, $instructions);
        }
    }

    #[Test]
    public function key_tool_descriptions_include_when_to_use_guidance(): void
    {
        $this->assertStringContainsString(
            'include_properties=true',
            $this->attributeValue(SearchVertexTypesTool::class, Description::class),
        );
        $this->assertStringContainsString(
            'create_vertex',
            $this->attributeValue(AddRevisionActionTool::class, Description::class),
        );
        $this->assertStringContainsString(
            'validate-revision',
            $this->attributeValue(SubmitRevisionTool::class, Description::class),
        );
    }

    #[Test]
    public function revision_action_schema_enumerates_action_types(): void
    {
        $schemaProvider = new class
        {
            use ProvidesRevisionActionSchema;

            public function expose(): array
            {
                return $this->revisionActionSchema(new JsonSchemaTypeFactory)->toArray();
            }
        };

        $schema = $schemaProvider->expose();

        $this->assertSame(RevisionActionType::values(), $schema['properties']['action']['enum']);
        $this->assertStringContainsString('create_vertex', $schema['properties']['action']['description']);
    }

    /**
     * @param  class-string  $class
     * @param  class-string  $attribute
     */
    private function attributeValue(string $class, string $attribute): string
    {
        $attributes = (new ReflectionClass($class))->getAttributes($attribute);

        $this->assertNotEmpty($attributes, "Missing {$attribute} on {$class}");

        return $attributes[0]->newInstance()->value;
    }
}
