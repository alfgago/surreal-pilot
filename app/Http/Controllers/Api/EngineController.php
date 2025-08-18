<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EngineSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EngineController extends Controller
{
    public function __construct(
        private EngineSelectionService $engineSelectionService
    ) {}

    /**
     * Get available engines.
     */
    public function getEngines(): JsonResponse
    {
        try {
            $engines = $this->engineSelectionService->getAvailableEngines();
            
            return response()->json([
                'success' => true,
                'engines' => array_values($engines),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available engines',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set user engine preference.
     */
    public function setEnginePreference(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'engine_type' => [
                    'required',
                    'string',
                    Rule::in(['playcanvas', 'unreal'])
                ]
            ]);

            // Check if user can access this engine
            if (!$this->engineSelectionService->canUserAccessEngine($user, $validated['engine_type'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this engine type',
                ], 403);
            }

            $this->engineSelectionService->setUserEnginePreference($user, $validated['engine_type']);

            return response()->json([
                'success' => true,
                'message' => 'Engine preference updated successfully',
                'engine_type' => $validated['engine_type'],
                'engine_name' => $this->engineSelectionService->getEngineDisplayName($validated['engine_type']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set engine preference',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user engine preference.
     */
    public function getEnginePreference(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $engineType = $this->engineSelectionService->getUserEnginePreference($user);

            if (!$engineType) {
                return response()->json([
                    'success' => true,
                    'engine_type' => null,
                    'engine_name' => null,
                    'has_selection' => false,
                ]);
            }

            return response()->json([
                'success' => true,
                'engine_type' => $engineType,
                'engine_name' => $this->engineSelectionService->getEngineDisplayName($engineType),
                'has_selection' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve engine preference',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear user engine preference.
     */
    public function clearEnginePreference(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->update(['selected_engine_type' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Engine preference cleared successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear engine preference',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
