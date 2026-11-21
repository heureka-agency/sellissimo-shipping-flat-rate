<?php

namespace Sellissimo\FlatRate;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Sellissimo\Core\Http\StoreCountries;
use Sellissimo\Core\Money;
use Sellissimo\Core\Store;
use Statamic\Dictionaries\Currencies;
use Statamic\Facades\CP\Toast;

class ZoneController
{
    public function index(FlatRate $shipping)
    {
        abort_unless(\Sellissimo\Core\StaffSites::storewide(), 403);
        $site = request()->validate(['site' => 'sometimes|required|string|max:100'])['site'] ?? \Sellissimo\Core\StaffSites::selected();
        \Sellissimo\Core\StaffSites::authorize($site);
        [$settings, $revision] = $shipping->settings();
        $zones = [];
        foreach ($settings as $handle => $zone) {
            $rates = [];
            foreach ($zone['amounts'] as $currency => $amount) {
                $rates[] = ['currency' => $currency, 'price' => Money::decimal($amount, $currency)];
            }
            $zones[] = ['handle' => $handle, 'label' => $zone['label'], 'countries' => implode(', ', $zone['countries']),
                'regions' => implode(', ', $zone['regions'] ?? []), 'postcodes' => implode(', ', $zone['postcodes'] ?? []),
                'rates' => $rates, 'pay_on_delivery' => $zone['pay_on_delivery'] ?? false];
        }

        return Inertia::render('sellissimo-flat-rate::Zones', [
            'hubUrl' => \Illuminate\Support\Facades\Route::has('statamic.cp.sellissimo.settings.section') ? cp_route('sellissimo.settings.section', ['section' => 'shipping', 'site' => $site]) : null,
            'countries' => StoreCountries::labels(true),
            'currencies' => collect((new Currencies)->optionItems())->map(fn ($row) => ['value' => $row['code'], 'label' => $row['name'].' ('.$row['code'].')'])->values(),
            'currency' => \Sellissimo\Core\StoreSettings::forSection($site, 'currency', 'currency', config("sellissimo.currencies.$site")) ?: 'USD',
            'zones' => $zones, 'revision' => $revision, 'regions' => array_map(
                fn ($rows) => array_map(fn ($row) => [$row[0], $row[1]], $rows), \Sellissimo\Core\PostalRegions::countries()),
            'saveUrl' => cp_route('sellissimo-shipping.save', compact('site')),
            'previewUrl' => cp_route('sellissimo-shipping.preview'), 'csrf' => csrf_token(),
        ]);
    }

    private function zones(Request $request): array
    {
        $data = $request->validate([
            'zones' => 'present|array|max:200', 'zones.*.handle' => ['required', 'distinct', 'regex:/^[a-z0-9][a-z0-9_-]{0,69}$/D'],
            'zones.*.label' => 'required|string|max:120', 'zones.*.countries' => 'required|string|max:1200',
            'zones.*.regions' => 'nullable|string|max:1200', 'zones.*.postcodes' => 'nullable|string|max:2400',
            'zones.*.pay_on_delivery' => 'required|boolean', 'zones.*.rates' => 'required|array|min:1|max:100',
            'zones.*.rates.*.currency' => ['required', 'regex:/^[A-Z]{3}$/D'], 'zones.*.rates.*.price' => 'required|string|max:16',
        ]);
        $split = fn ($value) => array_values(array_unique(array_filter(array_map('trim', explode(',', mb_strtoupper($value ?? ''))), 'strlen')));
        $countries = StoreCountries::labels();
        $zones = [];
        foreach ($data['zones'] as $index => $zone) {
            $destinations = $split($zone['countries']);
            if (! $destinations || array_diff($destinations, array_keys($countries))) {
                throw ValidationException::withMessages(["zones.$index.countries" => 'Enter valid two-letter country codes.']);
            }
            $amounts = [];
            foreach ($zone['rates'] as $rateIndex => $rate) {
                $currency = $rate['currency'];
                $amount = Money::parseDecimal($rate['price'], Money::fractionDigits($currency), "zones.$index.rates.$rateIndex.price");
                if (isset($amounts[$currency]) || $amount > 99999999) {
                    throw ValidationException::withMessages(["zones.$index.rates" => 'Enter one rate per currency, up to 99,999,999 minor units.']);
                }
                $amounts[$currency] = $amount;
            }
            $zones[$zone['handle']] = ['label' => $zone['label'], 'countries' => $destinations, 'amounts' => $amounts,
                'regions' => $split($zone['regions'] ?? null), 'postcodes' => $split($zone['postcodes'] ?? null), 'pay_on_delivery' => (bool) $zone['pay_on_delivery']];
        }

        return $zones;
    }

    public function preview(Request $request, FlatRate $shipping)
    {
        abort_unless(\Sellissimo\Core\StaffSites::storewide(), 403);
        $address = $request->validate(['country' => ['required', Rule::in(array_keys(StoreCountries::labels()))],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/D'], 'region' => 'nullable|string|max:100', 'postal_code' => 'nullable|string|max:40']);
        $quotes = $shipping->matching($this->zones($request), $address, $address['currency']);

        return response()->json(['options' => collect($quotes)->map(fn ($quote) => [...$quote, 'price' => Money::display($quote['amount'], $address['currency'])])->values()]);
    }

    public function save(Request $request, Store $store)
    {
        abort_unless(\Sellissimo\Core\StaffSites::storewide(), 403);
        $revision = (int) $request->validate(['revision' => 'required|integer|min:0'])['revision'];
        $values = ['settings' => json_encode($this->zones($request), JSON_THROW_ON_ERROR), 'revision' => $revision + 1];
        $settings = $store->table('extension_settings');
        $saved = $revision ? $settings->where('handle', FlatRate::SETTINGS)->where('revision', $revision)->update($values)
            : $settings->insertOrIgnore(['handle' => FlatRate::SETTINGS, ...$values]);
        if (! $saved) {
            throw ValidationException::withMessages(['revision' => 'Shipping zones changed while you were editing. Reload before saving.']);
        }
        Toast::success(__('Shipping zones saved.'));

        return redirect(cp_route('sellissimo-shipping.zones', ['site' => $request->query('site') ?: \Sellissimo\Core\StaffSites::selected()]));
    }
}
