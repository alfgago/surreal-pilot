<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameBuild extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'game_id',
        'version',
        'status',
        'build_log',
        'build_url',
        'commit_hash',
        'build_config',
        'assets_manifest',
        'file_count',
        'total_size',
        'build_duration',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'build_config' => 'array',
            'assets_manifest' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the game that owns this build.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Check if the build is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the build is in progress.
     */
    public function isBuilding(): bool
    {
        return $this->status === 'building';
    }

    /**
     * Check if the build failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the build duration in a human-readable format.
     */
    public function getBuildDurationFormatted(): string
    {
        if (!$this->build_duration) {
            return 'Unknown';
        }

        $minutes = floor($this->build_duration / 60);
        $seconds = $this->build_duration % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }

    /**
     * Get the total size in a human-readable format.
     */
    public function getTotalSizeFormatted(): string
    {
        $bytes = $this->total_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }
}