<?php

namespace App\Services;

use App\Models\User;

class EngineSelectionService
{
    /**
     * Get available engines with their descriptions.
     */
    public function getAvailableEngines(): array
    {
        return [
            'playcanvas' => [
                'type' => 'playcanvas',
                'name' => 'PlayCanvas',
                'description' => 'Web and mobile game development with instant preview and publishing',
                'icon' => 'playcanvas-icon',
                'features' => [
                    'Web and mobile games',
                    'Instant preview',
                    'One-click publishing',
                    'Touch-optimized',
                    'Real-time collaboration'
                ]
            ],
            'unreal' => [
                'type' => 'unreal',
                'name' => 'Unreal Engine',
                'description' => 'Advanced 3D game development with Blueprint and C++ support',
                'icon' => 'unreal-icon',
                'features' => [
                    'AAA game development',
                    'Blueprint visual scripting',
                    'C++ programming',
                    'Advanced rendering',
                    'VR/AR support'
                ]
            ]
        ];
    }

    /**
     * Set user engine preference.
     */
    public function setUserEnginePreference(User $user, string $engineType): void
    {
        if (!$this->validateEngineType($engineType)) {
            throw new \InvalidArgumentException("Invalid engine type: {$engineType}");
        }

        $user->setEnginePreference($engineType);
    }

    /**
     * Get user engine preference.
     */
    public function getUserEnginePreference(User $user): ?string
    {
        return $user->getSelectedEngineType();
    }

    /**
     * Validate engine type.
     */
    public function validateEngineType(string $engineType): bool
    {
        $availableEngines = array_keys($this->getAvailableEngines());
        return in_array($engineType, $availableEngines);
    }

    /**
     * Get engine display name.
     */
    public function getEngineDisplayName(string $engineType): string
    {
        $engines = $this->getAvailableEngines();
        return $engines[$engineType]['name'] ?? ucfirst($engineType);
    }

    /**
     * Check if user can access engine type based on their plan.
     */
    public function canUserAccessEngine(User $user, string $engineType): bool
    {
        // For now, all users can access all engines
        // This could be extended to check user's company plan
        return $this->validateEngineType($engineType);
    }
}