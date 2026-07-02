<?php

namespace App\Models;

use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vertex_type_id
 * @property string $age_property_name
 * @property \App\Enums\PropertyType $age_property_type
 */
class VertexProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'vertex_type_id',
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

    public function vertexType(): BelongsTo
    {
        return $this->belongsTo(VertexType::class);
    }
}
