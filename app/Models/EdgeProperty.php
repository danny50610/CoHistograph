<?php

namespace App\Models;

use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $edge_type_id
 * @property string $age_property_name
 * @property \App\Enums\PropertyType $age_property_type
 */
class EdgeProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'edge_type_id',
        'name',
        'description',
        'age_property_name',
        'age_property_type',
    ];

    protected function casts(): array
    {
        return [
            'age_property_type' => PropertyType::class,
        ];
    }

    public function edgeType(): BelongsTo
    {
        return $this->belongsTo(EdgeType::class);
    }
}
