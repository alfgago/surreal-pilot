<?php

namespace App\Http\Controllers;

use App\Models\DemoTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplatesController extends Controller
{
    /**
     * Display the templates library.
     */
    public function index(Request $request): Response
    {
        $engineType = $request->get('engine', 'all');
        $difficulty = $request->get('difficulty', 'all');
        $search = $request->get('search', '');

        $query = DemoTemplate::active()
            ->orderBy('difficulty_level')
            ->orderBy('name');

        // Filter by engine type
        if ($engineType !== 'all') {
            $query->byEngine($engineType);
        }

        // Filter by difficulty
        if ($difficulty !== 'all') {
            $query->byDifficulty($difficulty);
        }

        // Search by name or description
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $templates = $query->get()->map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'engine_type' => $template->engine_type,
                'preview_image' => $template->getPreviewImageUrl(),
                'tags' => $template->tags,
                'difficulty_level' => $template->difficulty_level,
                'estimated_setup_time' => $template->estimated_setup_time,
                'repository_url' => $template->repository_url,
            ];
        });

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
            'filters' => [
                'engine' => $engineType,
                'difficulty' => $difficulty,
                'search' => $search,
            ],
            'stats' => [
                'total' => DemoTemplate::active()->count(),
                'playcanvas' => DemoTemplate::active()->playCanvas()->count(),
                'unreal' => DemoTemplate::active()->unreal()->count(),
            ],
        ]);
    }

    /**
     * Show a specific template.
     */
    public function show(DemoTemplate $template): Response
    {
        // Get related templates for suggestions
        $relatedTemplates = DemoTemplate::active()
            ->where('id', '!=', $template->id)
            ->where('engine_type', $template->engine_type)
            ->limit(3)
            ->get()
            ->map(function ($relatedTemplate) {
                return [
                    'id' => $relatedTemplate->id,
                    'name' => $relatedTemplate->name,
                    'description' => $relatedTemplate->description,
                    'preview_image' => $relatedTemplate->getPreviewImageUrl(),
                    'difficulty_level' => $relatedTemplate->difficulty_level,
                ];
            });

        return Inertia::render('Templates/Show', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'engine_type' => $template->engine_type,
                'preview_image' => $template->getPreviewImageUrl(),
                'tags' => $template->tags,
                'difficulty_level' => $template->difficulty_level,
                'estimated_setup_time' => $template->estimated_setup_time,
                'repository_url' => $template->repository_url,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ],
            'relatedTemplates' => $relatedTemplates,
        ]);
    }
}