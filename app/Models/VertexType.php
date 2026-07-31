<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VertexType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'age_label_name',
        'overview_order',
        'show_property_name',
    ];

    /** @return HasMany<VertexProperty, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(VertexProperty::class);
    }

    /** @return BelongsToMany<EdgeType, $this> */
    public function startEdgeTypes(): BelongsToMany
    {
        return $this->belongsToMany(EdgeType::class, 'edge_type_vertex_pairs', 'start_vertex_id', 'edge_type_id')
            ->distinct();
    }

    /** @return BelongsToMany<EdgeType, $this> */
    public function endEdgeTypes(): BelongsToMany
    {
        return $this->belongsToMany(EdgeType::class, 'edge_type_vertex_pairs', 'end_vertex_id', 'edge_type_id')
            ->distinct();
    }

    protected function childRouteBindingRelationshipName($childType)
    {
        if ($childType == 'vertex_property') {
            return 'properties';
        }

        return parent::childRouteBindingRelationshipName($childType);
    }
}
