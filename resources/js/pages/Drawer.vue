<script setup>
import { onMounted, ref } from 'vue';
import { api, session as auth } from '../api';
import { money, toMinor } from '../money';

const drawer = 'main';
const current = ref(null);
const floatSsp = ref('');
const floatUsd = ref('');
const countedSsp = ref('');
const countedUsd = ref('');
const result = ref(null);
const error = ref('');

async function load() {
    current.value = (await api('GET', `/cash-sessions/${drawer}`)).session;
}

async function run(fn) {
    error.value = '';
    try {
        await fn();
    } catch (e) {
        error.value = e.message;
    }
}

const open = () => run(async () => {
    await api('POST', '/cash-sessions', { drawer, float_ssp: toMinor(floatSsp.value || 0), float_usd: toMinor(floatUsd.value || 0) });
    result.value = null;
    await load();
});

const close = () => run(async () => {
    result.value = await api('POST', `/cash-sessions/${current.value.id}/close`, {
        counted_ssp: toMinor(countedSsp.value || 0),
        counted_usd: toMinor(countedUsd.value || 0),
    });
    await load();
});

onMounted(() => run(load));

const variance = (v, code) => (v === 0 ? 'Exact' : `${v > 0 ? 'Over' : 'Short'} ${money(Math.abs(v), code)}`);
</script>

<template>
    <div class="mx-auto max-w-lg space-y-4 p-4">
        <h1 class="text-2xl font-semibold">Cash drawer</h1>
        <p v-if="error" class="rounded bg-red-100 px-3 py-2 text-red-700">{{ error }}</p>

        <div v-if="result" class="rounded-xl bg-white p-4">
            <h2 class="font-semibold">Drawer counted</h2>
            <p>SSP: {{ variance(result.variance.ssp) }}</p>
            <p>USD: {{ variance(result.variance.usd, 'USD') }}</p>
        </div>

        <form v-if="!current" class="space-y-3 rounded-xl bg-white p-4" @submit.prevent="open">
            <h2 class="font-semibold">Open the drawer</h2>
            <label class="block text-sm">Starting cash (SSP)<input v-model="floatSsp" inputmode="decimal" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <label class="block text-sm">Starting cash (USD)<input v-model="floatUsd" inputmode="decimal" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <button class="h-12 w-full rounded-xl bg-emerald-600 font-semibold text-white">Open drawer</button>
        </form>

        <template v-else>
            <div class="rounded-xl bg-white p-4">
                <h2 class="font-semibold">Open since {{ new Date(current.created_at).toLocaleTimeString() }}</h2>
                <p class="text-sm text-slate-600">Float {{ money(current.float_ssp) }} and {{ money(current.float_usd, 'USD') }}</p>
                <p v-if="auth.employee?.can_manage" class="mt-2">Expected now: <strong>{{ money(current.expected_ssp) }}</strong> and <strong>{{ money(current.expected_usd, 'USD') }}</strong></p>
            </div>
            <form v-if="auth.employee?.can_manage" class="space-y-3 rounded-xl bg-white p-4" @submit.prevent="close">
                <h2 class="font-semibold">Count up and close</h2>
                <label class="block text-sm">Counted SSP<input v-model="countedSsp" inputmode="decimal" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <label class="block text-sm">Counted USD<input v-model="countedUsd" inputmode="decimal" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <button class="h-12 w-full rounded-xl bg-slate-900 font-semibold text-white">Close drawer</button>
            </form>
            <p v-else class="text-sm text-slate-500">A manager counts up and closes the drawer.</p>
        </template>
    </div>
</template>
