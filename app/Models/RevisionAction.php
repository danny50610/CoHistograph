<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function revision(): BelongsTo
    {
        return $this->belongsTo(Revision::class);
    }
}
