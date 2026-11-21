<?php

namespace Sellissimo\FlatRate;

use Sellissimo\Core\Registry;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $config = false;
    protected $vite = ['publicDirectory' => 'resources/dist', 'input' => ['resources/js/cp.js']];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flat-rate.php', 'sellissimo-flat-rate');
    }

    public function bootAddon()
    {
        if (class_exists(\Sellissimo\Core\SettingsRegistry::class)) {
            app(\Sellissimo\Core\SettingsRegistry::class)->action('shipping', 'Delivery zones, rates and quote preview',
                'sellissimo-shipping.zones', 'manage sellissimo shipping', true);
        } else {
            \Statamic\Facades\CP\Nav::extend(function ($nav) {
                $nav->create('Shipping zones')->id('sellissimo-shipping-zones')->section('Store settings')->icon('package-box-crate')
                    ->route('sellissimo-shipping.zones')->can('manage sellissimo shipping');
            });
        }
        app(Registry::class)->shipping('flat_rate', new FlatRate);
        $this->publishes([__DIR__.'/../config/flat-rate.php' => config_path('sellissimo-flat-rate.php')], 'sellissimo-flat-rate-config');
    }
}
