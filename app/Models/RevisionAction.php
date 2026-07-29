<?php

namespace App\Models;

use App\Enums\RevisionActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $revision_id
 * @property int $order
 * @property \App\Enums\RevisionActionType $action
 * @property string|null $target_age_id
 * @property int|null $target_ref_order
 * @property string|null $vertex_type_label
 * @property string|null $edge_type_label
 * @property string|null $start_vertex_age_id
 * @property int|null $start_vertex_ref_order
 * @property string|null $end_vertex_age_id
 * @property int|null $end_vertex_ref_order
 * @property string|null $age_property_name
 * @property string|null $value
 */
class RevisionAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'revision_id',
        'order',
        'action',
        'target_age_id',
        'target_ref_order',
        'vertex_type_label',
        'edge_type_label',
        'start_vertex_age_id',
        'start_vertex_ref_order',
        'end_vertex_age_id',
        'end_vertex_ref_order',
        'age_property_name',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'action' => RevisionActionType::class,
            // Keep AGE graphids as strings so Inertia/JSON does not lose precision in JS.
            'target_age_id' => 'string',
            'start_vertex_age_id' => 'string',
            'end_vertex_age_id' => 'string',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(Revision::class);
    }
}
