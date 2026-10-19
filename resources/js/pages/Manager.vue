<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import { money, tenderNames } from '../money';

const settings = ref(null);
const form = ref({});
const saved = ref('');
const date = ref(new Date().toLocaleDateString('en-CA'));
const report = ref(null);
const error = ref('');

async function run(fn) {
    error.value = '';
    try {
        await fn();
    } catch (e) {
        error.value = e.message;
    }
}

function fill(s) {
    settings.value = s;
    form.value = {
        ssp_per_usd: s.ssp_per_usd || '',
        tax_name: s.tax_name,
        tax_rate: s.tax_rate_bp / 100,
        tax_inclusive: s.tax_inclusive,
        service_charge: s.service_charge_bp / 100,
        receipt_footer: s.receipt_footer,
    };
}

const save = () => run(async () => {
    const f = form.value;
    fill(await api('PATCH', '/settings', {
        ...(f.ssp_per_usd ? { ssp_per_usd: Number(f.ssp_per_usd) } : {}),
        tax_name: f.tax_name,
        tax_rate_bp: Math.round(Number(f.tax_rate) * 100),
        tax_inclusive: f.tax_inclusive,
        service_charge_bp: Math.round(Number(f.service_charge) * 100),
        receipt_footer: f.receipt_footer,
    }));
    saved.value = 'Saved';
    setTimeout(() => (saved.value = ''), 2000);
});

const loadReport = () => run(async () => {
    report.value = await api('GET', `/reports/day?date=${date.value}`);
});

onMounted(() => run(async () => {
    fill(await api('GET', '/settings'));
    await loadReport();
}));
</script>

<template>
    <div class="grid gap-4 p-4 lg:grid-cols-2">
        <p v-if="error" class="rounded bg-red-100 px-3 py-2 text-red-700 lg:col-span-2">{{ error }}</p>

        <form v-if="settings" class="space-y-3 rounded-xl bg-white p-4" @submit.prevent="save">
            <h2 class="text-lg font-semibold">Settings</h2>
            <label class="block text-sm">Dollar rate: SSP per USD 1
                <input v-model="form.ssp_per_usd" inputmode="numeric" class="mt-1 w-full rounded-lg border px-3 py-2" placeholder="Not set" />
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">Tax name<input v-model="form.tax_name" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <label class="block text-sm">Tax rate (%)<input v-model="form.tax_rate" inputmode="decimal" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.tax_inclusive" type="checkbox" class="h-5 w-5" /> Menu prices already include tax</label>
            <label class="block text-sm">Service charge (%)<input v-model="form.service_charge" inputmode="decimal" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <label class="block text-sm">Receipt footer<input v-model="form.receipt_footer" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <button class="h-12 w-full rounded-xl bg-slate-900 font-semibold text-white">Save settings</button>
            <p v-if="saved" class="text-center text-sm text-emerald-700">{{ saved }}</p>
        </form>

        <section class="rounded-xl bg-white p-4">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold">End-of-day report</h2>
                <input v-model="date" type="date" class="ml-auto rounded-lg border px-2 py-1" @change="loadReport" />
            </div>
            <template v-if="report">
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Bills closed</dt><dd class="text-xl font-semibold">{{ report.orders }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Collected</dt><dd class="text-xl font-semibold">{{ money(report.collected) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Sales before charges</dt><dd class="font-semibold">{{ money(report.subtotal) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Service charge / tax</dt><dd class="font-semibold">{{ money(report.service_charge) }} / {{ money(report.tax) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Tips</dt><dd class="font-semibold">{{ money(report.tips) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Voids</dt><dd class="font-semibold">{{ report.voids.count }} · {{ money(report.voids.amount) }}</dd></div>
                </dl>
                <p v-if="report.open_orders" class="mt-3 rounded bg-amber-100 px-3 py-2 text-sm text-amber-900">{{ report.open_orders }} order(s) still open.</p>
                <h3 class="mt-4 font-semibold">By payment type</h3>
                <table class="mt-1 w-full text-sm">
                    <tr v-for="t in report.tenders" :key="t.tender" class="border-b">
                        <td class="py-1">{{ tenderNames[t.tender] }}</td>
                        <td class="text-right">{{ t.count }}</td>
                        <td class="text-right">{{ money(t.amount) }}</td>
                        <td class="text-right text-slate-500">{{ t.currency === 'USD' ? money(t.tendered, 'USD') : '' }}</td>
                    </tr>
                </table>
                <h3 class="mt-4 font-semibold">Top items</h3>
                <table class="mt-1 w-full text-sm">
                    <tr v-for="i in report.top_items" :key="i.name" class="border-b">
                        <td class="py-1">{{ i.name }}</td><td class="text-right">{{ i.quantity }}</td><td class="text-right">{{ money(i.sales) }}</td>
                    </tr>
                </table>
            </template>
        </section>
    </div>
</template>
