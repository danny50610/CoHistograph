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
 * @property string|null $locale
 * @property list<array{value: string, label: string, active: bool}>|null $enum_options
 * @property int|null $min_selections
 * @property int|null $max_selections
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
        'locale',
        'enum_options',
        'min_selections',
        'max_selections',
    ];

    protected function casts(): array
    {
        return [
            'age_property_type' => PropertyType::class,
            'enum_options' => 'array',
            'min_selections' => 'integer',
            'max_selections' => 'integer',
        ];
    }

    public function vertexType(): BelongsTo
    {
        return $this->belongsTo(VertexType::class);
    }
}
