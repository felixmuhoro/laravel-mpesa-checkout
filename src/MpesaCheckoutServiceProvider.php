<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout;

use FelixMuhoro\Mpesa\Mpesa;
use FelixMuhoro\MpesaCheckout\Http\Controllers\CheckoutController;
use FelixMuhoro\MpesaCheckout\View\Components\MpesaButton;
use FelixMuhoro\MpesaCheckout\View\Components\MpesaCheckoutModal;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class MpesaCheckoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mpesa-checkout.php',
            'mpesa-checkout',
        );

        $this->app->singleton(CheckoutManager::class, function ($app) {
            $config     = $app['config']['mpesa-checkout'];
            $cacheStore = $config['cache_store'] ?? null;
            $cache      = $cacheStore
                ? Cache::store($cacheStore)
                : $app->make('cache.store');

            return new CheckoutManager(
                mpesa:  $app->make(Mpesa::class),
                cache:  $cache,
                config: $config,
            );
        });

        $this->app->alias(CheckoutManager::class, 'mpesa-checkout');
    }

    public function boot(): void
    {
        // Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mpesa-checkout');

        // Blade components
        Blade::component('mpesa-button', MpesaButton::class);
        Blade::component('mpesa-checkout-modal', MpesaCheckoutModal::class);

        // Routes
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishAssets();
        }
    }

    private function registerRoutes(): void
    {
        $config = $this->app['config']['mpesa-checkout'];

        $this->app['router']->group([
            'prefix'     => $config['route_prefix'],
            'middleware' => $config['middleware'],
            'as'         => 'mpesa-checkout.',
        ], function ($router) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }

    private function publishAssets(): void
    {
        // Config
        $this->publishes([
            __DIR__ . '/../config/mpesa-checkout.php' => config_path('mpesa-checkout.php'),
        ], 'mpesa-checkout-config');

        // Views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/mpesa-checkout'),
        ], 'mpesa-checkout-views');

        // JS
        $this->publishes([
            __DIR__ . '/../resources/js/mpesa-checkout.js' => public_path('vendor/mpesa-checkout/mpesa-checkout.js'),
        ], 'mpesa-checkout-assets');
    }
}
