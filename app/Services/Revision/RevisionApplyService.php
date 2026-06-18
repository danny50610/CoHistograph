<?php

namespace App\Services\Revision;

use App\Enums\PropertyType;
use App\Enums\RevisionActionType;
use App\Models\EdgeProperty;
use App\Models\Revision;
use App\Models\RevisionAction;
use App\Models\VertexProperty;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class RevisionApplyService
{
    private string $graphConnection;

    private string $graphName;

    /** @var array<int, int> */
    private array $vertexIdsByOrder = [];

    /** @var array<int, int> */
    private array $edgeIdsByOrder = [];

    /** @var Collection<int, RevisionAction> */
    private Collection $actionsByOrder;

    public function __construct(private AgeGraphStateManager $graphManager)
    {
        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');
    }

    public function apply(Revision $revision): void
    {
        $revision->load([
            'actions' => fn ($query) => $query->orderBy('order'),
        ]);

        $this->graphManager->bootSchemaMaps();
        $this->vertexIdsByOrder = [];
        $this->edgeIdsByOrder = [];
        $this->actionsByOrder = $revision->actions->keyBy('order');

        DB::connection($this->graphConnection)->transaction(function () use ($revision): void {
            foreach ($revision->actions as $action) {
                $this->applyAction($action);
            }
        });
    }

    private function applyAction(RevisionAction $action): void
    {
        match ($action->action) {
            RevisionActionType::CreateVertex => $this->applyCreateVertex($action),
            RevisionActionType::DeleteVertex => $this->applyDeleteVertex($action),
            RevisionActionType::CreateEdge => $this->applyCreateEdge($action),
            RevisionActionType::DeleteEdge => $this->applyDeleteEdge($action),
            RevisionActionType::CreateVertexProperty,
            RevisionActionType::UpdateVertexProperty => $this->applySetVertexProperty($action),
            RevisionActionType::DeleteVertexProperty => $this->applyDeleteVertexProperty($action),
            RevisionActionType::CreateEdgeProperty,
            RevisionActionType::UpdateEdgeProperty => $this->applySetEdgeProperty($action),
            RevisionActionType::DeleteEdgeProperty => $this->applyDeleteEdgeProperty($action),
        };
    }

    private function applyCreateVertex(RevisionAction $action): void
    {
        $label = (string) $action->vertex_type_label;

        $result = $this->graphConnection()->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label) {
            return $builder->createNode('v', $label)->return('v');
        })->first();

        $this->vertexIdsByOrder[(int) $action->order] = (int) $result->v->id;
    }

    private function applyDeleteVertex(RevisionAction $action): void
    {
        $vertexId = $this->resolveVertexId($action);

        $this->graphConnection()->apacheAgeCypher($this->graphName, function (Builder $builder) use ($vertexId) {
            return $builder
                ->matchNode('v')
                ->where('id(v)', '=', $vertexId)
                ->delete('v')
                ->return('v');
        })->get();
    }

    private function applyCreateEdge(RevisionAction $action): void
    {
        $startVertexId = $this->resolveStartVertexId($action);
        $endVertexId = $this->resolveEndVertexId($action);
        $label = (string) $action->edge_type_label;

        $result = $this->graphConnection()->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $startVertexId, $endVertexId) {
            return $builder
                ->matchNode('s')
                ->where('id(s)', '=', $startVertexId)
                ->matchNode('t')
                ->where('id(t)', '=', $endVertexId)
                ->createRaw("(s)-[e:{$label}]->(t)")
                ->return('e');
        })->first();

        $this->edgeIdsByOrder[(int) $action->order] = (int) $result->e->id;
    }

    private function applyDeleteEdge(RevisionAction $action): void
    {
        $edgeId = $this->resolveEdgeId($action);

        $this->graphConnection()->apacheAgeCypher($this->graphName, function (Builder $builder) use ($edgeId) {
            return $builder
                ->matchNode('s')
                ->withMatchEdge(Direction::BOTH, 'e')
                ->withMatchNode('t')
                ->where('id(e)', '=', $edgeId)
                ->delete('e')
                ->return('e');
        })->get();
    }

    private function applySetVertexProperty(RevisionAction $action): void
    {
        $vertexId = $this->resolveVertexId($action);
        $propertyName = (string) $action->age_property_name;
        $property = $this->resolveVertexProperty($action, $vertexId);
        $value = $this->castPropertyValue((string) $action->value, $property->age_property_type);

        $this->graphConnection()->apacheAgeCypher($this->graphName, function (Builder $builder) use ($vertexId, $propertyName, $value) {
            return $builder
                ->matchNode('v')
                ->where('id(v)', '=', $vertexId)
                ->set(["v.{$propertyName}" => $value])
                ->return('v');
        })->get();
    }

    private function applyDeleteVertexProperty(RevisionAction $action): void
    {
        $vertexId = $this->resolveVertexId($action);
        $propertyName = (string) $action->age_property_name;

        $this->graphConnection()->apacheAgeCypher($this->graphName, function (Builder $builder) use ($vertexId, $propertyName) {
            return $builder
                ->matchNode('v')
                ->where('id(v)', '=', $vertexId)
                ->remove("v.{$propertyName}")
                ->return('v');
        })->get();
    }

    private function applySetEdgeProperty(RevisionAction $action): void
    {
        $edgeId = $this->resolveEdgeId($action);
        $propertyName = (string) $action->age_property_name;
        $property = $this->resolveEdgeProperty($action, $edgeId);
        $value = $this->castPropertyValue((string) $action->value, $property->age_property_type);

        $this->graphConnection()->apacheAgeCypher($this->graphName, function (Builder $builder) use ($edgeId, $propertyName, $value) {
            return $builder
                ->matchNode('s')
                ->withMatchEdge(Direction::BOTH, 'e')
                ->withMatchNode('t')
                ->where('id(e)', '=', $edgeId)
                ->set(["e.{$propertyName}" => $value])
                ->return('e');
        })->get();
    }

    private function applyDeleteEdgeProperty(RevisionAction $action): void
    {
        $edgeId = $this->resolveEdgeId($action);
        $propertyName = (string) $action->age_property_name;

        $this->graphConnection()->apacheAgeCypher($this->graphName, function (Builder $builder) use ($edgeId, $propertyName) {
            return $builder
                ->matchNode('s')
                ->withMatchEdge(Direction::BOTH, 'e')
                ->withMatchNode('t')
                ->where('id(e)', '=', $edgeId)
                ->remove("e.{$propertyName}")
                ->return('e');
        })->get();
    }

    private function resolveVertexId(RevisionAction $action): int
    {
        if (! is_null($action->target_ref_order)) {
            $refOrder = (int) $action->target_ref_order;

            if (! isset($this->vertexIdsByOrder[$refOrder])) {
                throw new LogicException("Referenced vertex at order {$refOrder} has not been created yet.");
            }

            return $this->vertexIdsByOrder[$refOrder];
        }

        return (int) $action->target_age_id;
    }

    private function resolveEdgeId(RevisionAction $action): int
    {
        if (! is_null($action->target_ref_order)) {
            $refOrder = (int) $action->target_ref_order;

            if (! isset($this->edgeIdsByOrder[$refOrder])) {
                throw new LogicException("Referenced edge at order {$refOrder} has not been created yet.");
            }

            return $this->edgeIdsByOrder[$refOrder];
        }

        return (int) $action->target_age_id;
    }

    private function resolveStartVertexId(RevisionAction $action): int
    {
        if (! is_null($action->start_vertex_ref_order)) {
            $refOrder = (int) $action->start_vertex_ref_order;

            if (! isset($this->vertexIdsByOrder[$refOrder])) {
                throw new LogicException("Referenced start vertex at order {$refOrder} has not been created yet.");
            }

            return $this->vertexIdsByOrder[$refOrder];
        }

        return (int) $action->start_vertex_age_id;
    }

    private function resolveEndVertexId(RevisionAction $action): int
    {
        if (! is_null($action->end_vertex_ref_order)) {
            $refOrder = (int) $action->end_vertex_ref_order;

            if (! isset($this->vertexIdsByOrder[$refOrder])) {
                throw new LogicException("Referenced end vertex at order {$refOrder} has not been created yet.");
            }

            return $this->vertexIdsByOrder[$refOrder];
        }

        return (int) $action->end_vertex_age_id;
    }

    private function resolveVertexProperty(RevisionAction $action, int $vertexId): VertexProperty
    {
        $vertexTypeLabel = $this->resolveVertexTypeLabel($action, $vertexId);
        $vertexType = $this->graphManager->getVertexTypeByLabel()[$vertexTypeLabel] ?? null;
        $property = $vertexType?->properties->firstWhere('age_property_name', $action->age_property_name);

        if ($property === null) {
            throw new LogicException("Vertex property {$action->age_property_name} not found for label {$vertexTypeLabel}.");
        }

        return $property;
    }

    private function resolveEdgeProperty(RevisionAction $action, int $edgeId): EdgeProperty
    {
        $edgeTypeLabel = $this->resolveEdgeTypeLabel($action, $edgeId);
        $edgeType = $this->graphManager->getEdgeTypeByLabel()[$edgeTypeLabel] ?? null;
        $property = $edgeType?->properties->firstWhere('age_property_name', $action->age_property_name);

        if ($property === null) {
            throw new LogicException("Edge property {$action->age_property_name} not found for label {$edgeTypeLabel}.");
        }

        return $property;
    }

    private function resolveVertexTypeLabel(RevisionAction $action, int $vertexId): string
    {
        if (! is_null($action->target_ref_order)) {
            /** @var RevisionAction|null $createAction */
            $createAction = $this->actionsByOrder->get((int) $action->target_ref_order);

            return (string) $createAction?->vertex_type_label;
        }

        return $this->graphManager->loadAgeVertexState($vertexId)['type_label'];
    }

    private function resolveEdgeTypeLabel(RevisionAction $action, int $edgeId): string
    {
        if (! is_null($action->target_ref_order)) {
            /** @var RevisionAction|null $createAction */
            $createAction = $this->actionsByOrder->get((int) $action->target_ref_order);

            return (string) $createAction?->edge_type_label;
        }

        return $this->graphManager->loadAgeEdgeState($edgeId)['type_label'];
    }

    private function castPropertyValue(string $value, PropertyType $propertyType): mixed
    {
        return match ($propertyType) {
            PropertyType::Integer => (int) $value,
            PropertyType::Float, PropertyType::Numeric => (float) $value,
            PropertyType::Boolean => strtolower($value) === 'true',
            PropertyType::String => $value,
        };
    }

    private function graphConnection(): PostgresConnection
    {
        /** @var PostgresConnection $connection */
        $connection = DB::connection($this->graphConnection);

        return $connection;
    }
}
