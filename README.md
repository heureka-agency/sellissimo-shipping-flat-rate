# Flat Rate Shipping for Sellissimo

## Licensing

Flat Rate Shipping for Sellissimo is a free extension for Sellissimo Core. Install it on any site covered by your Core license. Development and staging are free. [License agreement](LICENSE) · [Site licensing guide](https://sellissimo.heureka-kreativ.hu/docs/licensing)

## Setup

Install the matching packages from your Statamic site:

```sh
composer require sellissimo/core sellissimo/shipping-flat-rate
php please sellissimo:install
```

The shipping extension uses rates configured in Sellissimo and needs no carrier account.

Install alongside Sellissimo core, run `php please sellissimo:install`, and include `flat_rate` in `sellissimo.shipping`.

In **Store → Settings → Shipping and pickup**, staff with **Manage shipping zones and rates** permission can add a service, enter its stable handle, choose countries and set rates in each supported currency. Enter amounts in major units. A zero rate provides free delivery. Restrict a service further with regions or postcodes. Separate values with commas and use an asterisk for postcode prefixes. Every matching service is offered to the buyer. An unsupported destination or currency receives no quote.

Enable **This service collects payment on delivery** only for services that collect money from the buyer. The store also needs the pay-on-delivery gateway enabled. Use the destination preview to check changes before saving. Saving the full list replaces the flat-rate plugin's active settings. An empty list disables all flat-rate services.

Existing stores start with their configured zones. Before staff save settings, the plugin reads `config/sellissimo-flat-rate.php`. Publish the example with `php artisan vendor:publish --tag=sellissimo-flat-rate-config`. Config-file amounts use integer minor units. Each zone accepts `label`, `countries`, `amounts`, optional `regions` and `postcodes`, and an optional boolean `pay_on_delivery`. After the first control panel save, the commerce database owns the rates.

The default example offers US delivery for USD 5.00. Digital-only carts skip shipping.

State and province restrictions match both recognized names and codes for each destination country, including existing saved values. Postal restrictions accept `*` for a sequence and `?` for one character. For example, `94?0*` matches `94103`. Use the region selector and rate preview in the shipping zone editor to check the destination before saving.

For product support, email [the Sellissimo team](mailto:hello@heureka-kreativ.hu).
