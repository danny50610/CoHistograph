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
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 格式正確、但業務邏輯驗證應失敗的 RevisionAction 測試情境。
 * 對應 .spec/case-failed.json 中的 F1-F23 cases。
 */
class RevisionSubmitValidationFailedTest extends TestCase
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

    /** F1: create_vertex 使用不存在的 vertex_type_label */
    public function test_create_vertex_fails_when_vertex_type_label_not_found(): void
    {
        $user = User::factory()->createOne();
        // 僅建立 band、artist 等合法型別，不建立 concert
        VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex', 'vertex_type_label' => 'concert_type_not_exist_'.uniqid()],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('VERTEX_TYPE_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F2: delete_vertex 目標頂點不存在 */
    public function test_delete_vertex_fails_when_target_not_found(): void
    {
        $user = User::factory()->createOne();

        $revision = $this->createDraftRevision($user, [
            ['action' => 'delete_vertex', 'target_age_id' => 999999999],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('TARGET_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F4: create_edge 使用不存在的 edge_type_label */
    public function test_create_edge_fails_when_edge_type_label_not_found(): void
    {
        $user = User::factory()->createOne();
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $artistId = $this->createAgeVertex($artistType->age_label_name);
        $trackId = $this->createAgeVertex($trackType->age_label_name);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_edge',
                'edge_type_label' => 'composed_by_not_exist_'.uniqid(),
                'start_vertex_age_id' => $artistId,
                'end_vertex_age_id' => $trackId,
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('EDGE_TYPE_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F5: create_edge belongs_to 的起點頂點型別不符（起點應為 artist，但提供 band） */
    public function test_create_edge_fails_when_start_vertex_type_mismatches(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupBelongsToSchema();

        // band 頂點用作起點（應為 artist）
        $bandId = $this->createAgeVertex($schema['band']->age_label_name);
        $bandId2 = $this->createAgeVertex($schema['band']->age_label_name);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_edge',
                'edge_type_label' => $schema['belongs_to']->age_label_name,
                'start_vertex_age_id' => $bandId,
                'end_vertex_age_id' => $bandId2,
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('EDGE_VERTEX_TYPE_MISMATCH', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F6: create_vertex_property 屬性名稱不屬於該頂點類型 */
    public function test_create_vertex_property_fails_when_property_not_in_vertex_type(): void
    {
        $user = User::factory()->createOne();
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $trackType->id,
            'age_property_name' => 'title',
            'age_property_type' => PropertyType::String,
        ]);
        // performs edge type has track_order, not the vertex
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $performsType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $artistType->id,
            'end_vertex_id' => $trackType->id,
        ]);
        EdgeProperty::factory()->createOne([
            'edge_type_id' => $performsType->id,
            'age_property_name' => 'track_order',
            'age_property_type' => PropertyType::Integer,
        ]);
        $trackId = $this->createAgeVertex($trackType->age_label_name);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_vertex_property',
                'target_age_id' => $trackId,
                'age_property_name' => 'track_order', // 屬於邊，不屬於 track 頂點
                'value' => '1',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F7: create_vertex_property 屬性已有值，不可重複建立 */
    public function test_create_vertex_property_fails_when_property_already_exists(): void
    {
        $user = User::factory()->createOne();
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $artistType->id,
            'age_property_name' => 'nickname',
            'age_property_type' => PropertyType::String,
        ]);
        $artistId = $this->createAgeVertexWithProperties($artistType->age_label_name, ['nickname' => 'Luminae']);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_vertex_property',
                'target_age_id' => $artistId,
                'age_property_name' => 'nickname',
                'value' => 'Echo',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_ALREADY_EXISTS', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F9: update_vertex_property 屬性尚無值，不可執行 update */
    public function test_update_vertex_property_fails_when_property_has_no_value(): void
    {
        $user = User::factory()->createOne();
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $trackType->id,
            'age_property_name' => 'release_year',
            'age_property_type' => PropertyType::Integer,
        ]);
        $trackId = $this->createAgeVertex($trackType->age_label_name); // 無 release_year 屬性值

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'update_vertex_property',
                'target_age_id' => $trackId,
                'age_property_name' => 'release_year',
                'value' => '2023',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_NOT_EXISTS', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F10: update_vertex_property value 型別與屬性定義不符（INTEGER） */
    public function test_update_vertex_property_fails_when_value_type_mismatches(): void
    {
        $user = User::factory()->createOne();
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $trackType->id,
            'age_property_name' => 'release_year',
            'age_property_type' => PropertyType::Integer,
        ]);
        $trackId = $this->createAgeVertexWithProperties($trackType->age_label_name, ['release_year' => 2020]);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'update_vertex_property',
                'target_age_id' => $trackId,
                'age_property_name' => 'release_year',
                'value' => 'not-a-number',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_TYPE_MISMATCH', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F11: delete_vertex_property 屬性尚無值，不可執行 delete */
    public function test_delete_vertex_property_fails_when_property_has_no_value(): void
    {
        $user = User::factory()->createOne();
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $trackType->id,
            'age_property_name' => 'release_year',
            'age_property_type' => PropertyType::Integer,
        ]);
        $trackId = $this->createAgeVertex($trackType->age_label_name); // 無 release_year 屬性值

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'delete_vertex_property',
                'target_age_id' => $trackId,
                'age_property_name' => 'release_year',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_NOT_EXISTS', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F12: delete_vertex_property value 應為 null，但提供了非 null 值 */
    public function test_delete_vertex_property_fails_when_value_is_not_null(): void
    {
        $user = User::factory()->createOne();
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $artistType->id,
            'age_property_name' => 'nickname',
            'age_property_type' => PropertyType::String,
        ]);
        $artistId = $this->createAgeVertexWithProperties($artistType->age_label_name, ['nickname' => 'Luminae']);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'delete_vertex_property',
                'target_age_id' => $artistId,
                'age_property_name' => 'nickname',
                'value' => 'Luminae', // 應為 null
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('DELETE_VALUE_MUST_BE_NULL', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F13: create_edge_property 屬性已有值，不可重複建立 */
    public function test_create_edge_property_fails_when_property_already_exists(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupPerformsSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $performsId = $this->createAgeEdgeWithProperties(
            $schema['performs']->age_label_name,
            $artistId,
            $trackId,
            ['track_order' => 1],
        );

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_edge_property',
                'target_age_id' => $performsId,
                'age_property_name' => 'track_order',
                'value' => '2',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_ALREADY_EXISTS', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F14: delete_edge 目標邊不存在 */
    public function test_delete_edge_fails_when_target_not_found(): void
    {
        $user = User::factory()->createOne();

        $revision = $this->createDraftRevision($user, [
            ['action' => 'delete_edge', 'target_age_id' => 999999999],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('TARGET_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F15: update_edge_property 屬性尚無值，不可執行 update */
    public function test_update_edge_property_fails_when_property_has_no_value(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupPerformsSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $performsId = $this->createAgeEdgeWithProperties(
            $schema['performs']->age_label_name,
            $artistId,
            $trackId,
            ['track_order' => 1],
        ); // featuring 屬性尚無值

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'update_edge_property',
                'target_age_id' => $performsId,
                'age_property_name' => 'featuring',
                'value' => 'Vorryn',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_NOT_EXISTS', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F16: update_edge_property value 型別與屬性定義不符（INTEGER） */
    public function test_update_edge_property_fails_when_value_type_mismatches(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupPerformsSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $performsId = $this->createAgeEdgeWithProperties(
            $schema['performs']->age_label_name,
            $artistId,
            $trackId,
            ['track_order' => 1],
        );

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'update_edge_property',
                'target_age_id' => $performsId,
                'age_property_name' => 'track_order',
                'value' => 'two', // 應為整數
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_TYPE_MISMATCH', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F17: delete_edge_property 屬性尚無值，不可執行 delete */
    public function test_delete_edge_property_fails_when_property_has_no_value(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupPerformsSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $performsId = $this->createAgeEdgeWithProperties(
            $schema['performs']->age_label_name,
            $artistId,
            $trackId,
            ['track_order' => 1],
        ); // featuring 尚無值

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'delete_edge_property',
                'target_age_id' => $performsId,
                'age_property_name' => 'featuring',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_NOT_EXISTS', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F18: delete_edge_property value 應為 null，但提供了非 null 值 */
    public function test_delete_edge_property_fails_when_value_is_not_null(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupPerformsSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $performsId = $this->createAgeEdgeWithProperties(
            $schema['performs']->age_label_name,
            $artistId,
            $trackId,
            ['track_order' => 1],
        );

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'delete_edge_property',
                'target_age_id' => $performsId,
                'age_property_name' => 'track_order',
                'value' => '1', // 應為 null
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('DELETE_VALUE_MUST_BE_NULL', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F19: create_edge belongs_to 的終點頂點型別不符（終點應為 band，但提供了 artist） */
    public function test_create_edge_fails_when_end_vertex_type_mismatches(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupBelongsToSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $anotherArtistId = $this->createAgeVertex($schema['artist']->age_label_name);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_edge',
                'edge_type_label' => $schema['belongs_to']->age_label_name,
                'start_vertex_age_id' => $artistId,
                'end_vertex_age_id' => $anotherArtistId, // 終點應為 band
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('EDGE_VERTEX_TYPE_MISMATCH', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F21: create_vertex_property 目標頂點不存在 */
    public function test_create_vertex_property_fails_when_target_vertex_not_found(): void
    {
        $user = User::factory()->createOne();
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $artistType->id,
            'age_property_name' => 'nickname',
            'age_property_type' => PropertyType::String,
        ]);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_vertex_property',
                'target_age_id' => 999999999,
                'age_property_name' => 'nickname',
                'value' => 'Ghost',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('TARGET_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F22: create_edge_property 目標邊不存在 */
    public function test_create_edge_property_fails_when_target_edge_not_found(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupPerformsSchema();

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_edge_property',
                'target_age_id' => 999999999,
                'age_property_name' => 'track_order',
                'value' => '1',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('TARGET_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    /** F23: create_edge_property 屬性名稱不屬於該邊類型 */
    public function test_create_edge_property_fails_when_property_not_in_edge_type(): void
    {
        $user = User::factory()->createOne();
        // track type has 'title'; performs edge does NOT have 'title'
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $trackType->id,
            'age_property_name' => 'title',
            'age_property_type' => PropertyType::String,
        ]);
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $performsType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $artistType->id,
            'end_vertex_id' => $trackType->id,
        ]);
        EdgeProperty::factory()->createOne([
            'edge_type_id' => $performsType->id,
            'age_property_name' => 'track_order',
            'age_property_type' => PropertyType::Integer,
        ]);
        $artistId = $this->createAgeVertex($artistType->age_label_name);
        $trackId = $this->createAgeVertex($trackType->age_label_name);
        $performsId = $this->createAgeEdge($performsType->age_label_name, $artistId, $trackId);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_edge_property',
                'target_age_id' => $performsId,
                'age_property_name' => 'title', // 屬於 track 頂點，不屬於 performs 邊
                'value' => 'Nebula Call',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::Draft->value]);
    }

    // -------------------------------------------------------------------------
    // Schema helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{artist: VertexType, band: VertexType, belongs_to: EdgeType}
     */
    private function setupBelongsToSchema(): array
    {
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $bandType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $belongsToType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $artistType->id,
            'end_vertex_id' => $bandType->id,
        ]);

        return ['artist' => $artistType, 'band' => $bandType, 'belongs_to' => $belongsToType];
    }

    /**
     * @return array{artist: VertexType, track: VertexType, performs: EdgeType}
     */
    private function setupPerformsSchema(): array
    {
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $performsType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $artistType->id,
            'end_vertex_id' => $trackType->id,
        ]);
        EdgeProperty::factory()->createOne([
            'edge_type_id' => $performsType->id,
            'age_property_name' => 'track_order',
            'age_property_type' => PropertyType::Integer,
        ]);
        EdgeProperty::factory()->createOne([
            'edge_type_id' => $performsType->id,
            'age_property_name' => 'featuring',
            'age_property_type' => PropertyType::String,
        ]);

        return ['artist' => $artistType, 'track' => $trackType, 'performs' => $performsType];
    }

    // -------------------------------------------------------------------------
    // AGE helpers
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Test setup helpers
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string, mixed>>  $actions
     */
    private function createDraftRevision(User $user, array $actions): Revision
    {
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
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

        return $revision;
    }

    private function actAs(User $user): static
    {
        return $this->actingAs($user);
    }
}
