<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Patch extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'patch_id',
        'envelope_json',
        'diff_json_gz',
        'tokens_used',
        'success',
        'timings',
        'etag',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'timings' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}

