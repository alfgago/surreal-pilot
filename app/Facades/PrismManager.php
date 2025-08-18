<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string resolveProvider(string $preferredProvider = null)
 * @method static bool isProviderAvailable(string $provider)
 * @method static array getAvailableProviders()
 * @method static array getProviderConfig(string $provider)
 * @method static \PrismPHP\Prism\Prism createPrismInstance(string $preferredProvider = null)
 * @method static array getProviderStats()
 *
 * @see \App\Services\PrismProviderManager
 */
class PrismManager extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'prism.manager';
    }
}