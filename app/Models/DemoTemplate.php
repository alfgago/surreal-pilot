<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DemoTemplate extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'description',
        'engine_type',
        'repository_url',
        'preview_image',
        'tags',
        'difficulty_level',
        'estimated_setup_time',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_active' => 'boolean',
            'estimated_setup_time' => 'integer',
        ];
    }

    /**
     * Check if this template is for PlayCanvas engine.
     */
    public function isPlayCanvas(): bool
    {
        return $this->engine_type === 'playcanvas';
    }

    /**
     * Check if this template is for Unreal Engine.
     */
    public function isUnreal(): bool
    {
        return $this->engine_type === 'unreal';
    }

    /**
     * Clone the template repository to a target path.
     */
    public function clone(string $targetPath): bool
    {
        try {
            // Ensure the target directory exists
            if (!is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), 0755, true);
            }

            // Clone the repository
            $result = Process::run([
                'git', 'clone', $this->repository_url, $targetPath
            ]);

            return $result->successful();
        } catch (\Exception $e) {
            \Log::error("Failed to clone template {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate that the template has the required structure.
     */
    public function validateStructure(string $templatePath): bool
    {
        if (!is_dir($templatePath)) {
            return false;
        }

        if ($this->isPlayCanvas()) {
            // Check for PlayCanvas-specific files
            $requiredFiles = [
                'package.json',
                'src',
            ];

            foreach ($requiredFiles as $file) {
                if (!file_exists($templatePath . '/' . $file)) {
                    return false;
                }
            }

            // Check if package.json contains PlayCanvas dependencies
            $packageJsonPath = $templatePath . '/package.json';
            if (file_exists($packageJsonPath)) {
                $packageJson = json_decode(file_get_contents($packageJsonPath), true);
                return isset($packageJson['dependencies']['playcanvas']) || 
                       isset($packageJson['devDependencies']['playcanvas']);
            }
        }

        return true;
    }

    /**
     * Get the preview image URL.
     */
    public function getPreviewImageUrl(): ?string
    {
        if (!$this->preview_image) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($this->preview_image, 'http')) {
            return $this->preview_image;
        }

        // Otherwise, assume it's stored in our storage
        return Storage::url($this->preview_image);
    }

    /**
     * Get templates by engine type.
     */
    public function scopeByEngine($query, string $engineType)
    {
        return $query->where('engine_type', $engineType);
    }

    /**
     * Get only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get templates by difficulty level.
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    /**
     * Get PlayCanvas-specific templates.
     */
    public function scopePlayCanvas($query)
    {
        return $query->where('engine_type', 'playcanvas');
    }

    /**
     * Get Unreal Engine-specific templates.
     */
    public function scopeUnreal($query)
    {
        return $query->where('engine_type', 'unreal');
    }

    /**
     * Validate engine type compatibility before saving.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($template) {
            // Validate engine type
            if (!in_array($template->engine_type, ['playcanvas', 'unreal'])) {
                throw new \InvalidArgumentException("Invalid engine type: {$template->engine_type}. Must be 'playcanvas' or 'unreal'.");
            }
            
            // Validate difficulty level
            if (!in_array($template->difficulty_level, ['beginner', 'intermediate', 'advanced'])) {
                throw new \InvalidArgumentException("Invalid difficulty level: {$template->difficulty_level}. Must be 'beginner', 'intermediate', or 'advanced'.");
            }
            
            // Validate repository URL format
            if (!filter_var($template->repository_url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("Invalid repository URL format: {$template->repository_url}");
            }
            
            // Validate estimated setup time is reasonable
            if ($template->estimated_setup_time < 0 || $template->estimated_setup_time > 3600) {
                throw new \InvalidArgumentException("Estimated setup time must be between 0 and 3600 seconds.");
            }
        });
        
        static::updating(function ($template) {
            // Prevent engine type changes after creation to maintain data integrity
            if ($template->isDirty('engine_type') && $template->exists) {
                throw new \InvalidArgumentException("Engine type cannot be changed after template creation for cross-engine compatibility.");
            }
        });
    }
}
