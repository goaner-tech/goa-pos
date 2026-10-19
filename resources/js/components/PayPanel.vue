<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api';
import { fromMinor, money, tenderNames, toMinor } from '../money';

const props = defineProps({ order: { type: Object, required: true } });
const emit = defineEmits(['paid', 'close']);

const settings = ref(null);
const tender = ref('cash_ssp');
const amount = ref(fromMinor(props.order.bill.balance));
const tendered = ref('');
const tip = ref('');
const reference = ref('');
const error = ref('');
const busy = ref(false);
const lastChange = ref(null);

const bill = computed(() => props.order.bill);
const rate = computed(() => settings.value?.ssp_per_usd ?? 0);
const isCash = computed(() => tender.value === 'cash_ssp' || tender.value === 'cash_usd');
const due = computed(() => (toMinor(amount.value) || 0) + (toMinor(tip.value) || 0));

// What the cash in hand is worth in SSP, and the change owed in SSP.
const tenderedSsp = computed(() => {
    const t = toMinor(tendered.value);
    if (!t) return tender.value === 'cash_ssp' ? due.value : 0;

    return tender.value === 'cash_usd' ? t * rate.value : t;
});
const change = computed(() => Math.max(0, tenderedSsp.value - due.value));
const usdNeeded = computed(() => (rate.value ? Math.ceil(due.value / rate.value) : 0));

const quickCash = computed(() => {
    if (tender.value === 'cash_usd') return [usdNeeded.value, 1000, 2000, 5000, 10000].filter((v, i, a) => v >= usdNeeded.value && a.indexOf(v) === i);
    const notes = [100000, 200000, 500000, 1000000];
    const exact = due.value;
    const rounded = notes.map((n) => Math.ceil(exact / n) * n);

    return [exact, ...rounded].filter((v, i, a) => a.indexOf(v) === i).slice(0, 5);
});

watch(tender, () => (tendered.value = ''));

onMounted(async () => {
    settings.value = await api('GET', '/settings');
});

async function pay() {
    error.value = '';
    busy.value = true;
    try {
        const body = { tender: tender.value, amount: toMinor(amount.value), drawer: 'main' };
        if (toMinor(tip.value)) body.tip = toMinor(tip.value);
        if (isCash.value && toMinor(tendered.value)) body.tendered = toMinor(tendered.value);
        if (!isCash.value) body.reference = reference.value;
        const result = await api('POST', `/orders/${props.order.id}/payments`, body);
        lastChange.value = result.payment.change_given;
        amount.value = fromMinor(result.order.bill.balance);
        tendered.value = '';
        tip.value = '';
        reference.value = '';
        emit('paid', result.order);
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-10 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
        <div class="max-h-full w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-5">
            <div class="flex items-baseline justify-between">
                <h2 class="text-xl font-semibold">Pay {{ order.label }}</h2>
                <button class="text-slate-500" @click="emit('close')">Close</button>
            </div>

            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ money(bill.subtotal) }}</dd></div>
                <div v-if="bill.service_charge" class="flex justify-between"><dt>Service charge</dt><dd>{{ money(bill.service_charge) }}</dd></div>
                <div v-if="bill.tax" class="flex justify-between text-slate-500">
                    <dt>{{ settings?.tax_name ?? 'Tax' }}{{ bill.tax_inclusive ? ' (included)' : '' }}</dt><dd>{{ money(bill.tax) }}</dd>
                </div>
                <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd>{{ money(bill.total) }}</dd></div>
                <div v-if="bill.paid" class="flex justify-between text-emerald-700"><dt>Paid</dt><dd>{{ money(bill.paid) }}</dd></div>
                <div class="flex justify-between text-lg font-bold"><dt>Balance due</dt><dd>{{ money(bill.balance) }}</dd></div>
            </dl>

            <p v-if="lastChange" class="mt-3 rounded-lg bg-amber-100 px-3 py-3 text-center text-lg font-semibold text-amber-900">
                Give change: {{ money(lastChange) }}
            </p>

            <template v-if="order.status === 'open'">
                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button v-for="(name, key) in tenderNames" :key="key" @click="tender = key"
                        class="h-12 rounded-xl border-2 font-medium"
                        :class="tender === key ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200'">{{ name }}</button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="text-sm">Amount to pay (SSP)
                        <input v-model="amount" inputmode="decimal" class="mt-1 w-full rounded-lg border px-3 py-2 text-lg" />
                    </label>
                    <label class="text-sm">Tip (SSP)
                        <input v-model="tip" inputmode="decimal" placeholder="0" class="mt-1 w-full rounded-lg border px-3 py-2 text-lg" />
                    </label>
                </div>

                <template v-if="isCash">
                    <p v-if="tender === 'cash_usd'" class="mt-3 text-sm text-slate-600">
                        <template v-if="rate">Rate: USD 1 = SSP {{ rate.toLocaleString() }}. Needed: {{ money(usdNeeded, 'USD') }}.</template>
                        <span v-else class="text-red-600">A manager must set today's dollar rate first.</span>
                    </p>
                    <label class="mt-3 block text-sm">Cash received ({{ tender === 'cash_usd' ? 'USD' : 'SSP' }})
                        <input v-model="tendered" inputmode="decimal" :placeholder="tender === 'cash_ssp' ? 'Exact amount' : ''"
                            class="mt-1 w-full rounded-lg border px-3 py-2 text-lg" />
                    </label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button v-for="v in quickCash" :key="v" class="rounded-lg bg-slate-100 px-3 py-2"
                            @click="tendered = fromMinor(v)">{{ money(v, tender === 'cash_usd' ? 'USD' : undefined) }}</button>
                    </div>
                    <p class="mt-2 text-sm">Change to give: <strong>{{ money(change) }}</strong></p>
                </template>
                <label v-else class="mt-3 block text-sm">Transaction reference
                    <input v-model="reference" class="mt-1 w-full rounded-lg border px-3 py-2 text-lg" placeholder="From the SMS or slip" />
                </label>

                <p v-if="error" class="mt-3 rounded bg-red-100 px-3 py-2 text-sm text-red-700">{{ error }}</p>
                <button :disabled="busy" @click="pay"
                    class="mt-4 h-14 w-full rounded-xl bg-emerald-600 text-lg font-semibold text-white disabled:opacity-40">
                    Take {{ money(due) }}
                </button>
            </template>

            <div v-else class="mt-4 space-y-2">
                <p class="text-center font-semibold text-emerald-700">Paid in full. The order is closed.</p>
                <RouterLink :to="`/orders/${order.id}/receipt`" class="block h-12 rounded-xl bg-slate-900 pt-3 text-center font-semibold text-white">Print receipt</RouterLink>
                <RouterLink to="/floor" class="block h-12 rounded-xl bg-slate-100 pt-3 text-center">Back to floor</RouterLink>
            </div>
        </div>
    </div>
</template>
