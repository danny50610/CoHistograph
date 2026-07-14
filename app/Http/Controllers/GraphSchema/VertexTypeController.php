<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgeLabelName;
use App\Rules\GraphSchema\ImmutableAgeLabelNameWhenGraphDataExists;
use App\Rules\GraphSchema\ValidShowPropertyName;
use App\Support\LocalizedPropertyGrouper;
use App\Support\ShowPropertyNamePresenter;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VertexTypeController extends Controller
{
    public function __construct()
    {
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
        $vertexTypeList = VertexType::orderBy('id', 'desc')->paginate();

        return view('graph-schema.vertex-type.index', compact('vertexTypeList'));
    }

    public function show(VertexType $vertexType)
    {
        $vertexType->load([
            'properties',
            'startEdgeTypes',
            'startEdgeTypes.endVertex',
            'endEdgeTypes',
            'endEdgeTypes.startVertex',
        ]);

        $propertyGroups = app(LocalizedPropertyGrouper::class)->group($vertexType->properties);

        $showPropertyNameLabel = app(ShowPropertyNamePresenter::class)->displayLabel($vertexType);

        return view('graph-schema.vertex-type.show', compact('vertexType', 'propertyGroups', 'showPropertyNameLabel'));
    }

    public function create()
    {
        return view('graph-schema.vertex-type.create-or-edit');
    }

    public function store(Request $request)
    {
        // The names of labels between vertices and edges cannot overlap.
        $this->validate($request, array_merge([
            'name' => ['required', 'string', Rule::unique('vertex_types')],
            'age_label_name' => ['required', 'string', new AgeLabelName, Rule::unique('vertex_types'), Rule::unique('edge_types')],
            'description' => ['nullable', 'string'],
            'usage_guidelines' => ['nullable', 'string'],
        ], $this->overviewOrderValidationRules($request)));

        $vertexType = VertexType::create([
            'name' => $request->input('name'),
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description') ?? '',
            'usage_guidelines' => $request->input('usage_guidelines'),
            'overview_order' => $this->resolveOverviewOrder($request),
        ]);

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex「{$vertexType->name}」建立完成");
    }

    public function edit(VertexType $vertexType)
    {
        $vertexType->load('properties');

        $propertyOptions = app(ShowPropertyNamePresenter::class)->options($vertexType->properties);
        $ageLabelNameLocked = $this->hasAgeGraphData($vertexType);

        return view('graph-schema.vertex-type.create-or-edit', compact('vertexType', 'propertyOptions', 'ageLabelNameLocked'));
    }

    public function update(Request $request, VertexType $vertexType)
    {
        // The names of labels between vertices and edges cannot overlap.
        $this->validate($request, array_merge([
            'name' => ['required', 'string', Rule::unique('vertex_types')->ignore($vertexType)],
            'age_label_name' => [
                'required',
                'string',
                new AgeLabelName,
                new ImmutableAgeLabelNameWhenGraphDataExists(
                    $vertexType->age_label_name,
                    fn (): bool => $this->hasAgeGraphData($vertexType),
                ),
                Rule::unique('vertex_types')->ignore($vertexType),
                Rule::unique('edge_types'),
            ],
            'description' => ['nullable', 'string'],
            'usage_guidelines' => ['nullable', 'string'],
            'show_property_name' => ['nullable', 'string', ValidShowPropertyName::forVertexType($vertexType)],
        ], $this->overviewOrderValidationRules($request)));

        $vertexType->update([
            'name' => $request->input('name'),
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description') ?? '',
            'usage_guidelines' => $request->input('usage_guidelines'),
            'show_property_name' => $request->input('show_property_name', null),
            'overview_order' => $this->resolveOverviewOrder($request),
        ]);

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex「{$vertexType->name}」更新完成");
    }

    public function destroy(VertexType $vertexType)
    {
        if ($vertexType->startEdgeTypes()->exists() || $vertexType->endEdgeTypes()->exists()) {
            return redirect()->back()->with('warning', "無法刪除，因為 Vertex「{$vertexType->name}」還有 Edge 類型關聯");
        }

        if ($vertexType->properties()->exists()) {
            return redirect()->back()->with('warning', "無法刪除，因為 Vertex「{$vertexType->name}」還有屬性");
        }

        if ($this->hasAgeGraphData($vertexType)) {
            return redirect()->back()->with('warning', "無法刪除，因為圖資料庫中還有「{$vertexType->name}」類型的 Vertex 資料");
        }

        $vertexType->delete();

        return redirect()->route('graph-schema.vertex-type.index')
            ->with('global', "Vertex「{$vertexType->name}」刪除完成");
    }

    private function hasAgeGraphData(VertexType $vertexType): bool
    {
        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType) {
                return $builder->matchNode('v', $vertexType->age_label_name)
                    ->return('v')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function overviewOrderValidationRules(Request $request): array
    {
        return [
            'show_on_overview' => ['nullable', 'boolean'],
            'overview_order' => [
                Rule::requiredIf(fn () => $request->boolean('show_on_overview')),
                'nullable',
                'integer',
                'min:1',
                'max:255',
            ],
        ];
    }

    private function resolveOverviewOrder(Request $request): ?int
    {
        if (! $request->boolean('show_on_overview')) {
            return null;
        }

        $overviewOrder = $request->input('overview_order');

        return $overviewOrder === null || $overviewOrder === '' ? null : (int) $overviewOrder;
    }
}
