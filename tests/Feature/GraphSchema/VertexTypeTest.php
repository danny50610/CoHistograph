<?php

namespace Tests\Feature\GraphSchema;

use App\Models\User;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VertexTypeTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->addRole('admin');
    }

    public function test_create()
    {
        $this->actingAs($this->user)
            ->post('/graph-schema/vertex-type', [
                'name' => 'Person',
                'age_label_name' => 'person',
                'description' => 'A person vertex type',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $vertexTypes = VertexType::where('name', 'Person')->get();
        $this->assertCount(1, $vertexTypes);

        $vertexType = $vertexTypes->first();
        $this->assertNotNull($vertexType);
        $this->assertEquals('person', $vertexType->age_label_name);
        $this->assertEquals('A person vertex type', $vertexType->description);
    }

    public function test_create_fail_when_name_or_age_label_name_not_unique()
    {
        VertexType::create([
            'name' => 'Person',
            'age_label_name' => 'person',
            'description' => 'A person vertex type',
        ]);

        $this->actingAs($this->user)
            ->post('/graph-schema/vertex-type', [
                'name' => 'Person',
                'age_label_name' => 'individual',
                'description' => 'Another person vertex type',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('name');

        $this->actingAs($this->user)
            ->post('/graph-schema/vertex-type', [
                'name' => 'Individual',
                'age_label_name' => 'person',
                'description' => 'Another person vertex type',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('age_label_name');
    }
}
