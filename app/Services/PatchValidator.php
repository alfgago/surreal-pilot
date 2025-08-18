<?php

namespace App\Services;

class PatchValidator
{
    /**
     * Lightweight schema validation without external deps.
     * Returns [isValid, errorDetails]
     */
    public function validate(array $patch): array
    {
        $requiredTop = ['v','engine','intent','actions','constraints'];
        foreach ($requiredTop as $key) {
            if (!array_key_exists($key, $patch)) {
                return [false, ['missing' => $key]];
            }
        }

        if ($patch['v'] !== '1.0') {
            return [false, ['field' => 'v', 'expected' => '1.0']];
        }

        if (!in_array($patch['engine'], ['ue','playcanvas'], true)) {
            return [false, ['field' => 'engine']];
        }

        if (!in_array($patch['intent'], ['refactor','scene_edit','fix_errors'], true)) {
            return [false, ['field' => 'intent']];
        }

        if (!is_array($patch['actions']) || count($patch['actions']) < 1) {
            return [false, ['field' => 'actions']];
        }

        if (!is_array($patch['constraints'])) {
            return [false, ['field' => 'constraints']];
        }
        $constraints = $patch['constraints'];
        if (!array_key_exists('dryRun', $constraints) || !is_bool($constraints['dryRun'])) {
            return [false, ['field' => 'constraints.dryRun']];
        }
        if (!array_key_exists('maxOps', $constraints) || !is_int($constraints['maxOps'])) {
            return [false, ['field' => 'constraints.maxOps']];
        }
        if ($constraints['maxOps'] < 1 || $constraints['maxOps'] > 200) {
            return [false, ['field' => 'constraints.maxOps', 'range' => '[1,200]']];
        }

        return [true, []];
    }
}

