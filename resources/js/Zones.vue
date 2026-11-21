<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@statamic/cms/inertia';
import { Alert, Button, CardPanel, Checkbox, Combobox, Field, Input, Select } from '@statamic/cms/ui';
import SettingsHeader from '../../../core/resources/js/admin/SettingsHeader.vue';
import SettingsErrors from '../../../core/resources/js/admin/SettingsErrors.vue';
import { useSettingsChanges } from '../../../core/resources/js/admin/useSettingsChanges';
const props = defineProps({ hubUrl: String, countries: Object, currency: String, currencies: Array, zones: Array, regions: Object, revision: Number, saveUrl: String, previewUrl: String, csrf: String });
const form = useForm({ zones: JSON.parse(JSON.stringify(props.zones)), revision: props.revision });
const address = ref({ country: '', region: '', postal_code: '', currency: props.currency });
const quotes = ref(null);
const checking = ref(false);
const previewErrors = ref({});
const changes = useSettingsChanges(() => form.isDirty);
const countryOptions = computed(() => Object.entries(props.countries).map(([value, label]) => ({ value, label })));
const currencyOptions = computed(() => {
    const choices = [...props.currencies];
    const codes = [props.currency, ...form.zones.flatMap(zone => zone.rates.map(rate => rate.currency))];
    for (const value of new Set(codes.filter(Boolean))) {
        if (!choices.some(choice => choice.value === value)) choices.push({ value, label: value });
    }
    return choices;
});
const codes = value => value.split(',').map(code => code.trim().toUpperCase()).filter(Boolean);
const fieldError = key => form.errors[key] || previewErrors.value[key]?.[0];
let previewVersion = 0;
watch([address, () => form.zones], () => {
    previewVersion++;
    quotes.value = null;
    previewErrors.value = {};
    checking.value = false;
}, { deep: true });
function addRegion(zone, value) {
    if (!value) return;
    const codes = zone.regions.split(',').map(value => value.trim()).filter(Boolean);
    if (!codes.includes(value)) codes.push(value);
    zone.regions = codes.join(', ');
}
function add() {
    form.zones.push({ handle: '', label: '', countries: '', regions: '', postcodes: '', pay_on_delivery: false, rates: [{ currency: props.currency, price: '' }] });
}
async function preview() {
    const version = ++previewVersion;
    checking.value = true;
    quotes.value = null;
    previewErrors.value = {};
    try {
        const response = await fetch(props.previewUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': props.csrf },
            body: JSON.stringify({ zones: form.zones, ...address.value }) });
        const data = await response.json();
        if (version !== previewVersion) return;
        if (!response.ok) {
            previewErrors.value = data.errors || { preview: [data.message || __('Preview unavailable.')] };
            return;
        }
        quotes.value = data.options;
    } catch {
        if (version === previewVersion) previewErrors.value = { preview: [__('Preview unavailable.')] };
    } finally {
        if (version === previewVersion) checking.value = false;
    }
}
function save() {
    changes.submit(() => form.post(props.saveUrl, { preserveScroll: true, onSuccess: page => { form.revision = page.props.revision; form.defaults(); } }));
}
</script>
<template>
    <main class="ss-page ss-settings">
        <SettingsHeader :title="__('Shipping zones')" icon="package-box-crate" :parent-url="hubUrl" :parent-title="__('Shipping and pickup')" :scope="__('All sites')">
            <Button type="button" :text="__('Add zone')" @click="add" />
            <Button type="submit" form="shipping-zones" variant="primary" :text="__('Save changes')" :disabled="form.processing || !form.isDirty" />
        </SettingsHeader>
        <div class="zone-layout">
            <form id="shipping-zones" class="zone-layout" @submit.prevent="save">
                <SettingsErrors :errors="form.errors" />
                <Alert v-if="!form.zones.length" :text="__('No flat-rate shipping services are configured.')" />
                <CardPanel v-for="(zone, i) in form.zones" :key="i" :heading="zone.label || __('Shipping zone :number', { number: i+1 })" :level="2">
                    <div class="zone-fields">
                        <Field :label="__('Service name')" :error="fieldError('zones.'+i+'.label')"><Input v-model="zone.label" required /></Field>
                        <Field :label="__('Service handle')" :error="fieldError('zones.'+i+'.handle')"><Input v-model="zone.handle" required pattern="[a-z0-9][a-z0-9_\-]*" /></Field>
                        <Field :label="__('Countries')" :error="fieldError('zones.'+i+'.countries')" required>
                            <Combobox :model-value="codes(zone.countries)" :options="countryOptions" multiple searchable
                                option-label="label" option-value="value" :search-keys="['label','value']"
                                @update:model-value="value => zone.countries = value.join(', ')" />
                        </Field>
                        <Field :label="__('Regions (optional)')" :error="fieldError('zones.'+i+'.regions')"><Input v-model="zone.regions" :placeholder="__('NY, NJ')" /></Field>
                        <Field v-if="regions[zone.countries.trim().toUpperCase()]" :label="__('Add a region')">
                            <Select :key="zone.regions" :placeholder="__('Choose a region')" :options="regions[zone.countries.trim().toUpperCase()].map(([value,label]) => ({value,label}))"
                                @update:model-value="value => addRegion(zone, value)" />
                        </Field>
                        <Field :label="__('Postcodes (optional)')" :error="fieldError('zones.'+i+'.postcodes')"><Input v-model="zone.postcodes" :placeholder="__('100*, 10101')" /></Field>
                    </div>
                    <p class="zone-help">{{ __('Separate regions or postcodes with commas. Use * for any characters and ? for one postcode character.') }}</p>
                    <Checkbox v-model="zone.pay_on_delivery" :label="__('This service collects payment on delivery')" />
                    <div v-for="(rate, j) in zone.rates" :key="j" class="zone-rate">
                        <Field :label="__('Currency')" :error="fieldError('zones.'+i+'.rates.'+j+'.currency')" required><Combobox v-model="rate.currency" :options="currencyOptions"
                            option-label="label" option-value="value" searchable :search-keys="['label','value']" /></Field>
                        <Field :label="__('Rate')" :error="fieldError('zones.'+i+'.rates.'+j+'.price')"><Input v-model="rate.price" inputmode="decimal" required /></Field>
                        <Button v-if="zone.rates.length > 1" type="button" size="sm" variant="ghost" :text="__('Remove rate')" @click="zone.rates.splice(j, 1)" />
                    </div>
                    <Alert v-if="fieldError('zones.'+i+'.rates')" variant="error" :text="fieldError('zones.'+i+'.rates')" />
                    <div class="zone-actions">
                        <Button type="button" size="sm" :text="__('Add currency')" @click="zone.rates.push({ currency: '', price: '' })" />
                        <Button type="button" size="sm" variant="ghost" :text="__('Remove zone')" @click="form.zones.splice(i, 1)" />
                    </div>
                </CardPanel>
            </form>
            <CardPanel :heading="__('Preview delivery options')" :level="2"><form class="zone-layout" :aria-busy="checking" @submit.prevent="preview">
                <div class="zone-fields">
                    <Field :label="__('Destination country')"><Combobox v-model="address.country" :options="countryOptions"
                        option-label="label" option-value="value" searchable :search-keys="['label','value']" /></Field>
                    <Field :label="__('Region')"><Input v-model="address.region" list="rate-preview-regions" /><datalist id="rate-preview-regions">
                        <option v-for="[value,label] in regions[address.country] || []" :key="value" :value="value">{{ label }}</option>
                    </datalist></Field>
                    <Field :label="__('Postcode')"><Input v-model="address.postal_code" /></Field>
                    <Field :label="__('Order currency')" :error="fieldError('currency')" required><Combobox v-model="address.currency" :options="currencyOptions"
                        option-label="label" option-value="value" searchable :search-keys="['label','value']" /></Field>
                </div>
                <div><Button type="submit" :disabled="!address.country || checking" :text="checking ? __('Calculating…') : __('Preview options')" /></div>
                <SettingsErrors :errors="previewErrors" />
                <Alert v-if="quotes" :variant="quotes.length ? 'success' : 'warning'" live>
                    <p v-if="!quotes.length">{{ __('No matching services.') }}</p>
                    <p v-for="quote in quotes" :key="quote.label">{{ quote.label }} · {{ quote.price }}<span v-if="quote.pay_on_delivery"> {{ __('· Pay on delivery') }}</span></p>
                </Alert>
            </form></CardPanel>
        </div>
    </main>
</template>
<style scoped>
.zone-layout { display: grid; gap: 1rem; min-width: 0; }
.zone-fields { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr)); }
.zone-help { color: var(--ss-muted); font-size: .875rem; margin-block: .75rem; }
.zone-rate { display: flex; flex-wrap: wrap; align-items: end; gap: .75rem; margin-block: 1rem; }
.zone-rate > div { flex: 1 1 8rem; min-width: 0; }
.zone-actions { display: flex; justify-content: space-between; flex-wrap: wrap; gap: .5rem; }
</style>
