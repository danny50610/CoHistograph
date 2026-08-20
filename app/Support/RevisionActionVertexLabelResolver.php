<?php

namespace App\Support;

use App\Enums\RevisionActionType;
use App\Models\RevisionAction;
use App\Services\Graph\GraphEntitySearchService;
use Illuminate\Support\Collection;

class RevisionActionVertexLabelResolver
{
    public function __construct(
        private GraphEntitySearchService $graphEntitySearchService,
    ) {}

    /**
     * Resolve AGE vertex IDs used by create_edge actions to human-readable labels.
     *
     * @param  Collection<int, RevisionAction>  $actions
     * @return array<string, string> Map of age vertex id (string) => display label
     */
    public function labelsForActions(Collection $actions): array
    {
        $ids = $actions
            ->filter(fn (RevisionAction $action) => $action->action === RevisionActionType::CreateEdge)
            ->flatMap(fn (RevisionAction $action) => array_filter([
                $action->start_vertex_age_id,
                $action->end_vertex_age_id,
            ]))
            ->unique()
            ->values();

        $labels = [];

        foreach ($ids as $id) {
            $labels[(string) $id] = $this->labelForAgeId((string) $id);
        }

        return $labels;
    }

    public function labelForAgeId(string $ageId): string
    {
        if ($ageId === '' || ! ctype_digit($ageId)) {
            return $ageId !== '' ? "ID:{$ageId}" : '—';
        }

        $vertex = $this->graphEntitySearchService->findVertex((int) $ageId);

        if ($vertex === null) {
            return "ID:{$ageId}";
        }

        $displayName = $vertex['display_name'];

        if ($displayName === '' || $displayName === "(ID: {$ageId})") {
            return "ID:{$ageId}";
        }

        return $displayName;
    }
}
