<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\EdgeType;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgeLabelName;
use App\Rules\GraphSchema\ImmutableAgeLabelNameWhenGraphDataExists;
use App\Support\AgePropertyDataChecker;
use App\Support\LocalizedPropertyGrouper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EdgeTypeController extends Controller
{
    public function __construct(
        private AgePropertyDataChecker $agePropertyDataChecker,
    ) {
        $this->middleware('permission:graph-schema.manage')
            ->only([
                'create',
                'store',
                'edit',
                'update',
                'destroy',
            ]);
    }

    public function index()
    {
        $edgeTypeList = EdgeType::orderBy('id', 'desc')->paginate();

        return view('graph-schema.edge-type.index', compact('edgeTypeList'));
    }

    public function show(EdgeType $edgeType)
    {
        $edgeType->load('properties', 'vertexPairs.startVertex', 'vertexPairs.endVertex');

        $propertyGroups = app(LocalizedPropertyGrouper::class)->group($edgeType->properties);

        return view('graph-schema.edge-type.show', compact('edgeType', 'propertyGroups'));
    }

    protected function getVertexOptions()
    {
        return VertexType::orderBy('id')
            ->select(['id', 'name'])
            ->get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->name,
            ])
            ->toArray();
    }

    public function create()
    {
        $vertexOptions = $this->getVertexOptions();

        return view('graph-schema.edge-type.create-or-edit', compact('vertexOptions'));
    }

    public function store(Request $request)
    {
        // The names of labels between vertices and edges cannot overlap.
        $validated = $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('edge_types')],
            'reverse_name' => ['nullable', 'string'],
            'age_label_name' => ['required', 'string', new AgeLabelName, Rule::unique('vertex_types'), Rule::unique('edge_types')],
            'description' => ['nullable', 'string'],
            'vertex_pairs' => ['required', 'array', 'min:1'],
            'vertex_pairs.*.start_vertex_id' => ['required', 'exists:vertex_types,id'],
            'vertex_pairs.*.end_vertex_id' => ['required', 'exists:vertex_types,id'],
        ]);

        $this->assertUniqueVertexPairs($validated['vertex_pairs']);

        $edgeType = EdgeType::create([
            'name' => $validated['name'],
            'reverse_name' => $validated['reverse_name'] ?? '',
            'age_label_name' => $validated['age_label_name'],
            'description' => $validated['description'] ?? '',
        ]);

        $edgeType->syncVertexPairs($validated['vertex_pairs']);

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge Type「{$edgeType->name}」建立完成");
    }

    public function edit(EdgeType $edgeType)
    {
        $edgeType->load('vertexPairs');
        $vertexOptions = $this->getVertexOptions();
        $ageLabelNameLocked = $this->agePropertyDataChecker->edgeTypeHasData($edgeType);

        return view('graph-schema.edge-type.create-or-edit', compact('edgeType', 'vertexOptions', 'ageLabelNameLocked'));
    }

    public function update(Request $request, EdgeType $edgeType)
    {
        // The names of labels between vertices and edges cannot overlap.
        $validated = $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('edge_types')->ignore($edgeType)],
            'reverse_name' => ['nullable', 'string'],
            'age_label_name' => [
                'required',
                'string',
                new AgeLabelName,
                new ImmutableAgeLabelNameWhenGraphDataExists(
                    $edgeType->age_label_name,
                    fn (): bool => $this->agePropertyDataChecker->edgeTypeHasData($edgeType),
                ),
                Rule::unique('vertex_types'),
                Rule::unique('edge_types')->ignore($edgeType),
            ],
            'description' => ['nullable', 'string'],
            'vertex_pairs' => ['required', 'array', 'min:1'],
            'vertex_pairs.*.start_vertex_id' => ['required', 'exists:vertex_types,id'],
            'vertex_pairs.*.end_vertex_id' => ['required', 'exists:vertex_types,id'],
        ]);

        $this->assertUniqueVertexPairs($validated['vertex_pairs']);
        $this->assertRemovableVertexPairsDoNotHaveGraphData($edgeType, $validated['vertex_pairs']);

        $edgeType->update([
            'name' => $validated['name'],
            'reverse_name' => $validated['reverse_name'] ?? '',
            'age_label_name' => $validated['age_label_name'],
            'description' => $validated['description'] ?? '',
        ]);

        $edgeType->syncVertexPairs($validated['vertex_pairs']);

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge Type「{$edgeType->name}」更新完成");
    }

    public function destroy(EdgeType $edgeType)
    {
        if ($edgeType->properties()->exists()) {
            return redirect()->back()->with('warning', "無法刪除，因為 Edge Type「{$edgeType->name}」還有屬性");
        }

        if ($this->agePropertyDataChecker->edgeTypeHasData($edgeType)) {
            return redirect()->back()->with('warning', "無法刪除，因為圖資料庫中還有「{$edgeType->name}」類型的 Edge 資料");
        }

        $edgeType->delete();

        return redirect()->route('graph-schema.edge-type.index')
            ->with('global', "Edge Type「{$edgeType->name}」刪除完成");
    }

    /**
     * @param  list<array{start_vertex_id:int|string,end_vertex_id:int|string}>  $pairs
     */
    private function assertUniqueVertexPairs(array $pairs): void
    {
        $keys = [];

        foreach ($pairs as $index => $pair) {
            $key = ((int) $pair['start_vertex_id']).':'.((int) $pair['end_vertex_id']);
            if (isset($keys[$key])) {
                throw ValidationException::withMessages([
                    "vertex_pairs.{$index}.start_vertex_id" => '起迄節點組合不可重複',
                ]);
            }
            $keys[$key] = true;
        }
    }

    /**
     * @param  list<array{start_vertex_id:int|string,end_vertex_id:int|string}>  $incomingPairs
     */
    private function assertRemovableVertexPairsDoNotHaveGraphData(EdgeType $edgeType, array $incomingPairs): void
    {
        $edgeType->loadMissing('vertexPairs.startVertex', 'vertexPairs.endVertex');

        $incomingKeys = collect($incomingPairs)
            ->map(fn (array $pair): string => ((int) $pair['start_vertex_id']).':'.((int) $pair['end_vertex_id']))
            ->all();

        foreach ($edgeType->vertexPairs as $pair) {
            $key = $pair->start_vertex_id.':'.$pair->end_vertex_id;

            if (in_array($key, $incomingKeys, true)) {
                continue;
            }

            if ($this->agePropertyDataChecker->pairHasData($edgeType, $pair)) {
                $startName = $pair->startVertex->name;
                $endName = $pair->endVertex->name;

                throw ValidationException::withMessages([
                    'vertex_pairs' => "無法移除起迄組合「{$startName} → {$endName}」，因為圖資料庫中還有此組合的 Edge 資料",
                ]);
            }
        }
    }
}
