<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EdgeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'reverse_name',
        'description',
        'age_label_name',
        'start_vertex_id',
        'end_vertex_id',
    ];

    /** @return HasMany<EdgeProperty, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(EdgeProperty::class);
    }

    /** @return BelongsTo<VertexType, $this> */
    public function startVertex(): BelongsTo
    {
        return $this->belongsTo(VertexType::class, 'start_vertex_id');
    }

    /** @return BelongsTo<VertexType, $this> */
    public function endVertex(): BelongsTo
    {
        return $this->belongsTo(VertexType::class, 'end_vertex_id');
    }

    protected function childRouteBindingRelationshipName($childType)
    {
        if ($childType == 'edge_property') {
            return 'properties';
        }

        return parent::childRouteBindingRelationshipName($childType);
    }
}
