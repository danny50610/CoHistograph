<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Enums\RevisionStatus;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Approve 套用測試：對應 .spec/cases.json Case 3-10。
 */
class RevisionCasesApplyTest extends TestCase
{
    use DatabaseTransactions;

    private string $graphConnection;

    private string $graphName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');

        $connection = DB::connection($this->graphConnection);
        if (! $connection->apacheAgeHasGraph($this->graphName)) {
            $connection->apacheAgeCreateGraph($this->graphName);
        }
    }

    /** Case 3: 更正演唱順序排名 */
    public function test_case_3_approve_applies_update_edge_property(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $artist1Id = $this->createAgeVertex($schema['artist']->age_label_name);
        $artist2Id = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $edge1Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artist1Id, $trackId, ['track_order' => 2]);
        $edge2Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artist2Id, $trackId, ['track_order' => 1]);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'update_edge_property', 'target_age_id' => $edge1Id, 'age_property_name' => 'track_order', 'value' => '1'],
            ['action' => 'update_edge_property', 'target_age_id' => $edge2Id, 'age_property_name' => 'track_order', 'value' => '2'],
        ]);

        $this->approveRevision($reviewer, $revision);

        $edge = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($edge1Id) {
                return $builder
                    ->matchNode('s')
                    ->withMatchEdge(Direction::BOTH, 'e')
                    ->withMatchNode('t')
                    ->where('id(e)', '=', $edge1Id)
                    ->where('e.track_order', '=', 1)
                    ->return('e');
            })
            ->first();

        $this->assertNotNull($edge);
    }

    /** Case 4: 補充缺失的頂點與邊屬性 */
    public function test_case_4_approve_applies_missing_vertex_and_edge_properties(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $edgeId = $this->createAgeEdge($schema['performs']->age_label_name, $artistId, $trackId);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'create_vertex_property', 'target_age_id' => $artistId, 'age_property_name' => 'nickname', 'value' => 'Vorryn'],
            ['action' => 'create_edge_property', 'target_age_id' => $edgeId, 'age_property_name' => 'track_order', 'value' => '1'],
        ]);

        $this->approveRevision($reviewer, $revision);

        $artist = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($schema, $artistId) {
                return $builder
                    ->matchNode('v', $schema['artist']->age_label_name)
                    ->where('id(v)', '=', $artistId)
                    ->where('v.nickname', '=', 'Vorryn')
                    ->return('v');
            })
            ->first();

        $this->assertNotNull($artist);
    }

    /** Case 5: 替換影音片段連結 */
    public function test_case_5_approve_applies_linked_clip_replacement(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $oldClipId = $this->createAgeVertexWithProperties($schema['video_clip']->age_label_name, ['clip_code' => 'ABC001']);
        $linkedClipEdgeId = $this->createAgeEdge($schema['linked_clip']->age_label_name, $trackId, $oldClipId);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'delete_edge', 'target_age_id' => $linkedClipEdgeId],
            ['action' => 'delete_vertex', 'target_age_id' => $oldClipId],
            ['action' => 'create_vertex', 'vertex_type_label' => $schema['video_clip']->age_label_name],
            ['action' => 'create_vertex_property', 'target_ref_order' => 2, 'age_property_name' => 'clip_code', 'value' => 'DEF088'],
            ['action' => 'create_edge', 'edge_type_label' => $schema['linked_clip']->age_label_name, 'start_vertex_age_id' => $trackId, 'end_vertex_ref_order' => 2],
        ]);

        $this->approveRevision($reviewer, $revision);

        $newClip = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($schema) {
                return $builder
                    ->matchNode('v', $schema['video_clip']->age_label_name)
                    ->where('v.clip_code', '=', 'DEF088')
                    ->return('v');
            })
            ->first();

        $this->assertNotNull($newClip);

        $oldClip = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($oldClipId) {
                return $builder
                    ->matchNode('v')
                    ->where('id(v)', '=', $oldClipId)
                    ->return('v');
            })
            ->first();

        $this->assertNull($oldClip);
    }

    /** Case 6: 完整移除藝術家 */
    public function test_case_6_approve_applies_artist_removal(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $bandId = $this->createAgeVertex($schema['band']->age_label_name);
        $artistId = $this->createAgeVertexWithProperties($schema['artist']->age_label_name, ['nickname' => 'Vorryn']);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $track2Id = $this->createAgeVertex($schema['track']->age_label_name);
        $belongsToId = $this->createAgeEdge($schema['belongs_to']->age_label_name, $artistId, $bandId);
        $performs1Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $trackId, ['track_order' => 1]);
        $performs2Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $track2Id, ['track_order' => 1]);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'delete_edge_property', 'target_age_id' => $performs1Id, 'age_property_name' => 'track_order'],
            ['action' => 'delete_edge_property', 'target_age_id' => $performs2Id, 'age_property_name' => 'track_order'],
            ['action' => 'delete_edge', 'target_age_id' => $performs1Id],
            ['action' => 'delete_edge', 'target_age_id' => $performs2Id],
            ['action' => 'delete_edge', 'target_age_id' => $belongsToId],
            ['action' => 'delete_vertex_property', 'target_age_id' => $artistId, 'age_property_name' => 'nickname'],
            ['action' => 'delete_vertex', 'target_age_id' => $artistId],
        ]);

        $this->approveRevision($reviewer, $revision);

        $artist = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($artistId) {
                return $builder
                    ->matchNode('v')
                    ->where('id(v)', '=', $artistId)
                    ->return('v');
            })
            ->first();

        $this->assertNull($artist);
    }

    /** Case 7: 批量修正資料品質 */
    public function test_case_7_approve_applies_batch_property_corrections(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $artist1Id = $this->createAgeVertexWithProperties($schema['artist']->age_label_name, ['nickname' => 'Luminea']);
        $artist2Id = $this->createAgeVertexWithProperties($schema['artist']->age_label_name, ['nickname' => 'vorryn']);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $track2Id = $this->createAgeVertex($schema['track']->age_label_name);
        $performs1Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artist1Id, $trackId, ['track_order' => 2]);
        $performs2Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artist2Id, $track2Id, ['track_order' => 2]);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'update_vertex_property', 'target_age_id' => $artist1Id, 'age_property_name' => 'nickname', 'value' => 'Luminae'],
            ['action' => 'update_vertex_property', 'target_age_id' => $artist2Id, 'age_property_name' => 'nickname', 'value' => 'Vorryn'],
            ['action' => 'update_edge_property', 'target_age_id' => $performs1Id, 'age_property_name' => 'track_order', 'value' => '1'],
            ['action' => 'update_edge_property', 'target_age_id' => $performs2Id, 'age_property_name' => 'track_order', 'value' => '1'],
        ]);

        $this->approveRevision($reviewer, $revision);

        $artist = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($schema, $artist1Id) {
                return $builder
                    ->matchNode('v', $schema['artist']->age_label_name)
                    ->where('id(v)', '=', $artist1Id)
                    ->where('v.nickname', '=', 'Luminae')
                    ->return('v');
            })
            ->first();

        $this->assertNotNull($artist);
    }

    /** Case 8: 補充曲目發行年份與演唱掛名 */
    public function test_case_8_approve_applies_new_integer_and_string_properties(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertexWithProperties($schema['track']->age_label_name, ['title' => 'Nebula Call']);
        $edgeId = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $trackId, ['track_order' => 1]);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'create_vertex_property', 'target_age_id' => $trackId, 'age_property_name' => 'release_year', 'value' => '2023'],
            ['action' => 'create_edge_property', 'target_age_id' => $edgeId, 'age_property_name' => 'featuring', 'value' => 'feat. Vorryn'],
        ]);

        $this->approveRevision($reviewer, $revision);

        $track = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($trackId) {
                return $builder
                    ->matchNode('v')
                    ->where('id(v)', '=', $trackId)
                    ->where('v.release_year', '=', 2023)
                    ->return('v');
            })
            ->first();

        $this->assertNotNull($track);
    }

    /** Case 9: 修正曲目發行年份與演唱掛名 */
    public function test_case_9_approve_applies_corrected_integer_and_string_properties(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertexWithProperties($schema['track']->age_label_name, ['title' => 'Nebula Call', 'release_year' => 2020]);
        $edgeId = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $trackId, ['track_order' => 1, 'featuring' => 'feat. Luminae']);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'update_vertex_property', 'target_age_id' => $trackId, 'age_property_name' => 'release_year', 'value' => '2023'],
            ['action' => 'update_edge_property', 'target_age_id' => $edgeId, 'age_property_name' => 'featuring', 'value' => 'feat. Vorryn'],
        ]);

        $this->approveRevision($reviewer, $revision);

        $track = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($trackId) {
                return $builder
                    ->matchNode('v')
                    ->where('id(v)', '=', $trackId)
                    ->where('v.release_year', '=', 2023)
                    ->return('v');
            })
            ->first();

        $this->assertNotNull($track);
    }

    /** Case 10: 移除曲目發行年份與演唱掛名 */
    public function test_case_10_approve_applies_property_deletions(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertexWithProperties($schema['track']->age_label_name, ['title' => 'Nebula Call', 'release_year' => 2023]);
        $edgeId = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $trackId, ['track_order' => 1, 'featuring' => 'feat. Vorryn']);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'delete_vertex_property', 'target_age_id' => $trackId, 'age_property_name' => 'release_year'],
            ['action' => 'delete_edge_property', 'target_age_id' => $edgeId, 'age_property_name' => 'featuring'],
        ]);

        $this->approveRevision($reviewer, $revision);

        $trackWithYear = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($trackId) {
                return $builder
                    ->matchNode('v')
                    ->where('id(v)', '=', $trackId)
                    ->where('v.release_year', '=', 2023)
                    ->return('v');
            })
            ->first();

        $this->assertNull($trackWithYear);

        $edgeWithFeaturing = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($edgeId) {
                return $builder
                    ->matchNode('s')
                    ->withMatchEdge(Direction::BOTH, 'e')
                    ->withMatchNode('t')
                    ->where('id(e)', '=', $edgeId)
                    ->where('e.featuring', '=', 'feat. Vorryn')
                    ->return('e');
            })
            ->first();

        $this->assertNull($edgeWithFeaturing);
    }

    private function approveRevision(User $reviewer, Revision $revision): void
    {
        $this->actingAs($reviewer)
            ->post(route('admin.revisions.approve', $revision))
            ->assertRedirect(route('admin.revisions.show', $revision))
            ->assertSessionHas('global', '修訂已接受並套用');

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::Approved->value,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     */
    private function createPendingRevision(User $owner, array $actions): Revision
    {
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => RevisionStatus::Draft,
            'user_id' => $owner->id,
        ]);

        foreach ($actions as $index => $action) {
            $revision->actions()->create([
                'order' => $index,
                'action' => $action['action'],
                'target_age_id' => $action['target_age_id'] ?? null,
                'target_ref_order' => $action['target_ref_order'] ?? null,
                'vertex_type_label' => $action['vertex_type_label'] ?? null,
                'edge_type_label' => $action['edge_type_label'] ?? null,
                'start_vertex_age_id' => $action['start_vertex_age_id'] ?? null,
                'start_vertex_ref_order' => $action['start_vertex_ref_order'] ?? null,
                'end_vertex_age_id' => $action['end_vertex_age_id'] ?? null,
                'end_vertex_ref_order' => $action['end_vertex_ref_order'] ?? null,
                'age_property_name' => $action['age_property_name'] ?? null,
                'value' => $action['value'] ?? null,
            ]);
        }

        $this->actingAs($owner)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision));

        return $revision->fresh(['actions']);
    }

    private function createReviewer(): User
    {
        $reviewer = User::factory()->createOne();
        $reviewer->givePermission('revision.review');

        return $reviewer;
    }

    /**
     * @return array{band: VertexType, artist: VertexType, track: VertexType, video_clip: VertexType, belongs_to: EdgeType, performs: EdgeType, linked_clip: EdgeType}
     */
    private function setupFullSchema(): array
    {
        $bandType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $videoClipType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);

        VertexProperty::factory()->createOne(['vertex_type_id' => $artistType->id, 'age_property_name' => 'nickname', 'age_property_type' => PropertyType::String]);
        VertexProperty::factory()->createOne(['vertex_type_id' => $trackType->id, 'age_property_name' => 'title', 'age_property_type' => PropertyType::String]);
        VertexProperty::factory()->createOne(['vertex_type_id' => $trackType->id, 'age_property_name' => 'release_year', 'age_property_type' => PropertyType::Integer]);
        VertexProperty::factory()->createOne(['vertex_type_id' => $videoClipType->id, 'age_property_name' => 'clip_code', 'age_property_type' => PropertyType::String]);

        $belongsToType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $artistType->id,
            'end_vertex_id' => $bandType->id,
        ]);
        $performsType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $artistType->id,
            'end_vertex_id' => $trackType->id,
        ]);
        EdgeProperty::factory()->createOne(['edge_type_id' => $performsType->id, 'age_property_name' => 'track_order', 'age_property_type' => PropertyType::Integer]);
        EdgeProperty::factory()->createOne(['edge_type_id' => $performsType->id, 'age_property_name' => 'featuring', 'age_property_type' => PropertyType::String]);
        $linkedClipType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $trackType->id,
            'end_vertex_id' => $videoClipType->id,
        ]);

        return [
            'band' => $bandType,
            'artist' => $artistType,
            'track' => $trackType,
            'video_clip' => $videoClipType,
            'belongs_to' => $belongsToType,
            'performs' => $performsType,
            'linked_clip' => $linkedClipType,
        ];
    }

    private function createAgeVertex(string $label): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label) {
                return $builder->createNode('v', $label)->return('v');
            })
            ->first();

        return (int) $result->v->id;
    }

    private function createAgeVertexWithProperties(string $label, array $properties): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $properties) {
                return $builder->createNode('v', $label, $properties)->return('v');
            })
            ->first();

        return (int) $result->v->id;
    }

    private function createAgeEdge(string $label, int $startVertexId, int $endVertexId): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $startVertexId, $endVertexId) {
                return $builder
                    ->matchNode('s')
                    ->where('id(s)', '=', $startVertexId)
                    ->matchNode('t')
                    ->where('id(t)', '=', $endVertexId)
                    ->createRaw("(s)-[e:{$label}]->(t)")
                    ->return('e');
            })
            ->first();

        return (int) $result->e->id;
    }

    private function createAgeEdgeWithProperties(string $label, int $startVertexId, int $endVertexId, array $properties): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $startVertexId, $endVertexId, $properties) {
                $builder = $builder
                    ->matchNode('s')
                    ->where('id(s)', '=', $startVertexId)
                    ->matchNode('t')
                    ->where('id(t)', '=', $endVertexId)
                    ->createRaw("(s)-[e:{$label}]->(t)");

                if ($properties !== []) {
                    $edgeProperties = [];
                    foreach ($properties as $key => $value) {
                        $edgeProperties["e.{$key}"] = $value;
                    }
                    $builder = $builder->set($edgeProperties);
                }

                return $builder->return('e');
            })
            ->first();

        return (int) $result->e->id;
    }

    private function graphLabel(): string
    {
        return 'label_'.fake()->unique()->lexify('??????');
    }
}
