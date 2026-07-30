<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeTypeVertexPair extends Model
{
    /** @use HasFactory<\Database\Factories\EdgeTypeVertexPairFactory> */
    use HasFactory;

    protected $fillable = [
        'edge_type_id',
        'start_vertex_id',
        'end_vertex_id',
    ];

    /** @return BelongsTo<EdgeType, $this> */
    public function edgeType(): BelongsTo
    {
        return $this->belongsTo(EdgeType::class);
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
}
