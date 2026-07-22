<?php

namespace App\Http\Controllers\Graph;

use App\Http\Controllers\Controller;
use App\Services\Graph\GraphEntitySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphSearchController extends Controller
{
    public function __construct(
        private GraphEntitySearchService $searchService,
    ) {}

    public function vertices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:255'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if (isset($validated['id'])) {
            $vertex = $this->searchService->findVertex((int) $validated['id']);

            return response()->json([
                'data' => $vertex === null ? [] : [$vertex],
            ]);
        }

        $typeLabels = $this->normalizeTypeLabels(
            $validated['types'] ?? null,
            $validated['type'] ?? null,
        );

        $results = $this->searchService->searchVertices(
            $validated['q'] ?? '',
            $typeLabels,
            (int) ($validated['limit'] ?? 20),
        );

        return response()->json([
            'data' => $results,
        ]);
    }

    public function edges(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:255'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if (isset($validated['id'])) {
            $edge = $this->searchService->findEdge((int) $validated['id']);

            return response()->json([
                'data' => $edge === null ? [] : [$edge],
            ]);
        }

        $typeLabels = $this->normalizeTypeLabels(
            $validated['types'] ?? null,
            $validated['type'] ?? null,
        );

        $results = $this->searchService->searchEdges(
            $validated['q'] ?? '',
            $typeLabels,
            (int) ($validated['limit'] ?? 20),
        );

        return response()->json([
            'data' => $results,
        ]);
    }

    /**
     * @param  list<string>|null  $types
     * @return list<string>|null
     */
    private function normalizeTypeLabels(?array $types, ?string $type): ?array
    {
        $labels = [];

        if (is_array($types)) {
            foreach ($types as $label) {
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        if ($type !== null && $type !== '') {
            $labels[] = $type;
        }

        $labels = array_values(array_unique($labels));

        return $labels === [] ? null : $labels;
    }
}
