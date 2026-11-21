<?php

namespace Sellissimo\FlatRate;

use Sellissimo\Core\PostalRegions;
use Sellissimo\Core\Contracts\ShippingProvider;
use Sellissimo\Core\Store;

class FlatRate implements ShippingProvider, \Sellissimo\Core\Contracts\ReportsShippingCurrencyCoverage
{
    public const SETTINGS = 'flat_rate.zones';

    public function settings(): array
    {
        $row = app(Store::class)->table('extension_settings')->where('handle', self::SETTINGS)->first();

        return $row ? [json_decode($row->settings, true, flags: JSON_THROW_ON_ERROR), (int) $row->revision]
            : [config('sellissimo-flat-rate.zones', []), 0];
    }

    public function currencyCoverage(string $site, string $currency): array
    {
        $destinations = app(\Sellissimo\Core\CheckoutCountries::class)->options($site, 'shipping');
        $services = [];
        foreach ($this->settings()[0] as $zone) {
            if (!array_intersect(array_keys($destinations), $zone['countries'])) {
                continue;
            }
            $amount = $zone['amounts'][$currency] ?? null;
            $services[] = ['label' => $zone['label'], 'priced' => is_int($amount) && $amount >= 0,
                'free' => $amount === 0, 'pay_on_delivery' => (bool) ($zone['pay_on_delivery'] ?? false)];
        }
        return $services;
    }

    public function quotes(array $physicalLines, array $address, string $currency): array
    {
        return $physicalLines ? $this->matching($this->settings()[0], $address, $currency) : [];
    }

    public function matching(array $zones, array $address, string $currency): array
    {
        $quotes = [];
        $country = strtoupper(trim($address['country'] ?? ''));
        $region = mb_strtoupper(trim($address['region'] ?? ''));
        $postcode = strtoupper(trim($address['postal_code'] ?? ''));
        foreach ($zones as $key => $zone) {
            $amount = $zone['amounts'][$currency] ?? null;
            if (! is_int($amount) || $amount < 0 || ! in_array($country, $zone['countries'], true)) {
                continue;
            }
            if (! empty($zone['regions']) && ! collect($zone['regions'])->contains(fn ($rule) => PostalRegions::matches($country, $rule, $region))) {
                continue;
            }
            if (! empty($zone['postcodes']) && ! PostalRegions::postcode($zone['postcodes'], $postcode)) {
                continue;
            }
            $quotes[$key] = ['label' => $zone['label'], 'amount' => $amount];
            if (($zone['pay_on_delivery'] ?? false) === true) {
                $quotes[$key]['pay_on_delivery'] = true;
            }
        }

        return $quotes;
    }
}
