<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class EdgeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'reverse_name',
        'description',
        'age_label_name',
    ];

    /** @return HasMany<EdgeProperty, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(EdgeProperty::class);
    }

    /** @return HasMany<EdgeTypeVertexPair, $this> */
    public function vertexPairs(): HasMany
    {
        return $this->hasMany(EdgeTypeVertexPair::class);
    }

    /** @return BelongsToMany<VertexType, $this> */
    public function startVertices(): BelongsToMany
    {
        return $this->belongsToMany(VertexType::class, 'edge_type_vertex_pairs', 'edge_type_id', 'start_vertex_id')
            ->distinct();
    }

    /** @return BelongsToMany<VertexType, $this> */
    public function endVertices(): BelongsToMany
    {
        return $this->belongsToMany(VertexType::class, 'edge_type_vertex_pairs', 'edge_type_id', 'end_vertex_id')
            ->distinct();
    }

    /**
     * @return list<string>
     */
    public function startVertexLabels(): array
    {
        return $this->vertexPairs
            ->map(fn (EdgeTypeVertexPair $pair) => $pair->startVertex?->age_label_name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function endVertexLabels(): array
    {
        return $this->vertexPairs
            ->map(fn (EdgeTypeVertexPair $pair) => $pair->endVertex?->age_label_name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function allowsEndpointPair(?string $startLabel, ?string $endLabel): bool
    {
        if ($startLabel === null || $endLabel === null || $startLabel === '' || $endLabel === '') {
            return false;
        }

        return $this->vertexPairs->contains(
            fn (EdgeTypeVertexPair $pair): bool => $pair->startVertex?->age_label_name === $startLabel
                && $pair->endVertex?->age_label_name === $endLabel
        );
    }

    /**
     * @param  Collection<int, array{start_vertex_id:int,end_vertex_id:int}>|list<array{start_vertex_id:int|string,end_vertex_id:int|string}>  $pairs
     */
    public function syncVertexPairs(array|Collection $pairs): void
    {
        $normalized = collect($pairs)
            ->map(fn (array $pair): array => [
                'start_vertex_id' => (int) $pair['start_vertex_id'],
                'end_vertex_id' => (int) $pair['end_vertex_id'],
            ])
            ->unique(fn (array $pair): string => $pair['start_vertex_id'].':'.$pair['end_vertex_id'])
            ->values();

        $this->vertexPairs()->delete();

        foreach ($normalized as $pair) {
            $this->vertexPairs()->create($pair);
        }
    }

    protected function childRouteBindingRelationshipName($childType)
    {
        if ($childType == 'edge_property') {
            return 'properties';
        }

        return parent::childRouteBindingRelationshipName($childType);
    }
}
