<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VertexProperty extends Model
{
    protected $fillable = [
        'vertex_type_id',
        'name',
        'description',
        'age_property_name',
        'age_property_type',
    ];

    public function vertexType(): BelongsTo
    {
        return $this->belongsTo(VertexType::class);
    }
}
