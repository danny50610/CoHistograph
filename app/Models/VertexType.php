<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    /** @return HasMany<EdgeType, $this> */
    public function startEdgeTypes(): HasMany
    {
        return $this->hasMany(EdgeType::class, 'start_vertex_id');
    }

    /** @return HasMany<EdgeType, $this> */
    public function endEdgeTypes(): HasMany
    {
        return $this->hasMany(EdgeType::class, 'end_vertex_id');
    }

    protected function childRouteBindingRelationshipName($childType)
    {
        if ($childType == 'vertex_property') {
            return 'properties';
        }

        return parent::childRouteBindingRelationshipName($childType);
    }
}
