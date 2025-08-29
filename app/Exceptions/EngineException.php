<?php

namespace App\Exceptions;

class EngineException extends ApiException
{
    public static function engineNotSelected(): self
    {
        return new self(
            'No engine type selected',
            'ENGINE_NOT_SELECTED',
            'Please select an engine type (PlayCanvas or Unreal Engine) before proceeding.',
            [
                'actions' => [
                    'select_engine' => '/api/engines',
                    'set_preference' => '/api/user/engine-preference',
                ],
            ],
            400
        );
    }

    public static function engineMismatch(string $required, string $current): self
    {
        return new self(
            "This action requires {$required} engine, but you have {$current} selected",
            'ENGINE_MISMATCH',
            "This feature is only available for {$required} projects. Please switch your engine preference or use a {$required} workspace.",
            [
                'required_engine' => $required,
                'current_engine' => $current,
                'actions' => [
                    'change_engine' => '/api/user/engine-preference',
                    'view_workspaces' => '/api/workspaces',
                ],
            ],
            409
        );
    }

    public static function engineAccessDenied(string $engine): self
    {
        return new self(
            "You don't have access to {$engine} engine",
            'ENGINE_ACCESS_DENIED',
            "Your current plan doesn't include access to {$engine}. Please upgrade your plan or contact your administrator.",
            [
                'engine' => $engine,
                'actions' => [
                    'upgrade_plan' => '/dashboard/billing/plans',
                    'contact_admin' => 'Contact your company administrator',
                    'select_different_engine' => '/api/engines',
                ],
            ],
            403
        );
    }

    public static function engineUnavailable(string $engine): self
    {
        return new self(
            "Engine {$engine} is currently unavailable",
            'ENGINE_UNAVAILABLE',
            "The {$engine} engine is temporarily unavailable. Please try again later or select a different engine.",
            [
                'engine' => $engine,
                'actions' => [
                    'retry_later' => 'Try again in a few minutes',
                    'select_different_engine' => '/api/engines',
                    'check_status' => '/api/engines',
                ],
            ],
            503
        );
    }

    public static function invalidEngineType(string $engineType): self
    {
        return new self(
            "Invalid engine type: {$engineType}",
            'INVALID_ENGINE_TYPE',
            'The selected engine type is not supported. Please choose either PlayCanvas or Unreal Engine.',
            [
                'provided_engine' => $engineType,
                'supported_engines' => ['playcanvas', 'unreal'],
                'actions' => [
                    'select_valid_engine' => '/api/engines',
                ],
            ],
            422
        );
    }
}