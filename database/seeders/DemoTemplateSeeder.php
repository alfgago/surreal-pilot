<?php

namespace Database\Seeders;

use App\Models\DemoTemplate;
use Illuminate\Database\Seeder;

class DemoTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'id' => 'playcanvas-fps-starter',
                'name' => 'FPS Starter',
                'description' => 'A complete first-person shooter template with player movement, shooting mechanics, and enemy AI. Perfect for creating action games.',
                'engine_type' => 'playcanvas',
                'repository_url' => 'https://github.com/playcanvas/fps-starter.git',
                'preview_image' => 'templates/playcanvas-fps-starter.jpg',
                'tags' => ['fps', '3d', 'shooter', 'action'],
                'difficulty_level' => 'intermediate',
                'estimated_setup_time' => 300, // 5 minutes
                'is_active' => true,
            ],
            [
                'id' => 'playcanvas-third-person',
                'name' => 'Third-Person Adventure',
                'description' => 'A third-person adventure game template with character controller, camera system, and basic interaction mechanics.',
                'engine_type' => 'playcanvas',
                'repository_url' => 'https://github.com/playcanvas/third-person-starter.git',
                'preview_image' => 'templates/playcanvas-third-person.jpg',
                'tags' => ['3d', 'adventure', 'third-person', 'exploration'],
                'difficulty_level' => 'beginner',
                'estimated_setup_time' => 240, // 4 minutes
                'is_active' => true,
            ],
            [
                'id' => 'playcanvas-2d-platformer',
                'name' => '2D Platformer',
                'description' => 'A classic 2D side-scrolling platformer with physics, collectibles, and level progression. Great for retro-style games.',
                'engine_type' => 'playcanvas',
                'repository_url' => 'https://github.com/playcanvas/2d-platformer-starter.git',
                'preview_image' => 'templates/playcanvas-2d-platformer.jpg',
                'tags' => ['2d', 'platformer', 'retro', 'physics'],
                'difficulty_level' => 'beginner',
                'estimated_setup_time' => 180, // 3 minutes
                'is_active' => true,
            ],
            [
                'id' => 'playcanvas-racing-game',
                'name' => 'Racing Game',
                'description' => 'A 3D racing game template with vehicle physics, track system, and lap timing. Perfect for arcade-style racing games.',
                'engine_type' => 'playcanvas',
                'repository_url' => 'https://github.com/playcanvas/racing-starter.git',
                'preview_image' => 'templates/playcanvas-racing-game.jpg',
                'tags' => ['3d', 'racing', 'vehicles', 'arcade'],
                'difficulty_level' => 'advanced',
                'estimated_setup_time' => 420, // 7 minutes
                'is_active' => true,
            ],
            [
                'id' => 'playcanvas-puzzle-game',
                'name' => 'Puzzle Game',
                'description' => 'A match-3 style puzzle game template with grid system, scoring, and level progression mechanics.',
                'engine_type' => 'playcanvas',
                'repository_url' => 'https://github.com/playcanvas/puzzle-starter.git',
                'preview_image' => 'templates/playcanvas-puzzle-game.jpg',
                'tags' => ['2d', 'puzzle', 'match-3', 'casual'],
                'difficulty_level' => 'intermediate',
                'estimated_setup_time' => 360, // 6 minutes
                'is_active' => true,
            ],
            [
                'id' => 'playcanvas-tower-defense',
                'name' => 'Tower Defense',
                'description' => 'A strategic tower defense game template with enemy waves, tower placement, upgrades, and resource management.',
                'engine_type' => 'playcanvas',
                'repository_url' => 'https://github.com/playcanvas/tower-defense-starter.git',
                'preview_image' => 'templates/playcanvas-tower-defense.jpg',
                'tags' => ['2d', 'strategy', 'tower-defense', 'tactical'],
                'difficulty_level' => 'advanced',
                'estimated_setup_time' => 480, // 8 minutes
                'is_active' => true,
            ],
            [
                'id' => 'unreal-fps-template',
                'name' => 'Unreal FPS Template',
                'description' => 'Minimal Unreal FPS template placeholder for tests.',
                'engine_type' => 'unreal',
                'repository_url' => 'https://example.com/unreal-fps.git',
                'preview_image' => null,
                'tags' => ['unreal', 'fps'],
                'difficulty_level' => 'beginner',
                'estimated_setup_time' => 60,
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            DemoTemplate::updateOrCreate(
                ['id' => $template['id']],
                $template
            );
        }

        $this->command->info('Demo templates seeded successfully.');
    }
}