<?php

namespace Tests\Unit\Mcp;

use App\Enums\RevisionActionType;
use App\Mcp\Concerns\ProvidesRevisionActionSchema;
use App\Mcp\Servers\CoHistographServer;
use App\Mcp\Tools\Revision\AddRevisionActionTool;
use App\Mcp\Tools\Revision\SubmitRevisionTool;
use App\Mcp\Tools\Revision\ValidateRevisionTool;
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
        $this->assertStringContainsString('validation.is_valid', $instructions);
        $this->assertStringContainsString('validate-revision', $instructions);
        $this->assertStringContainsString('submit-revision', $instructions);
        $this->assertStringContainsString('target_age_id XOR target_ref_order', $instructions);
        $this->assertStringContainsString('do not assume you must call validate-revision after every edit', $instructions);
        $this->assertStringContainsString('submitted=false', $instructions);

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
            'JSON array',
            $this->attributeValue(AddRevisionActionTool::class, Description::class),
        );
        $this->assertStringContainsString(
            'submitted=false',
            $this->attributeValue(SubmitRevisionTool::class, Description::class),
        );
        $this->assertStringContainsString(
            'Prefer checking validation from the latest',
            $this->attributeValue(ValidateRevisionTool::class, Description::class),
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
        $this->assertSame(
            ['array', 'string', 'integer', 'number', 'boolean'],
            $schema['properties']['value']['type'],
        );
        $this->assertStringContainsString('ENUM', $schema['properties']['value']['description']);
        $this->assertStringContainsString('["rock","jazz"]', $schema['properties']['value']['description']);
        $this->assertStringContainsString('never a scalar string', $schema['properties']['value']['description']);
    }

    #[Test]
    public function server_instructions_document_enum_property_values(): void
    {
        $instructions = $this->attributeValue(CoHistographServer::class, Instructions::class);

        $this->assertStringContainsString('enum_options', $instructions);
        $this->assertStringContainsString('JSON array of option value strings', $instructions);
        $this->assertStringContainsString('["rock","jazz"]', $instructions);
        $this->assertStringContainsString('never a scalar string', $instructions);
    }

    #[Test]
    public function schema_search_tool_descriptions_mention_enum_options(): void
    {
        $this->assertStringContainsString(
            'enum_options',
            $this->attributeValue(SearchVertexTypesTool::class, Description::class),
        );
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
