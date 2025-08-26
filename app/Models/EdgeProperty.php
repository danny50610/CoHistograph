<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeProperty extends Model
{
    protected $fillable = [
        'edge_type_id',
        'name',
        'description',
        'age_property_name',
        'age_property_type',
    ];

    public function edgeType(): BelongsTo
    {
        return $this->belongsTo(EdgeType::class);
    }
}
