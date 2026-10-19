<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import { money, setCurrency, tenderNames } from '../money';

const props = defineProps({ id: { type: String, required: true } });
const order = ref(null);
const settings = ref(null);
const venue = document.title;

onMounted(async () => {
    [order.value, settings.value] = await Promise.all([api('GET', `/orders/${props.id}`), api('GET', '/settings')]);
    setCurrency(settings.value.currency);
});

const print = () => window.print();
const when = (iso) => new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <div class="flex flex-col items-center gap-4 p-4 print:p-0">
        <div class="flex gap-2 print:hidden">
            <button class="rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white" @click="print">Print</button>
            <RouterLink :to="`/orders/${id}`" class="rounded-lg bg-white px-5 py-3">Back to order</RouterLink>
        </div>

        <!-- Sized for 80 mm thermal receipt paper. -->
        <article v-if="order" class="w-[72mm] bg-white p-3 font-mono text-[12px] leading-snug text-black shadow print:shadow-none">
            <h1 class="text-center text-base font-bold">{{ venue }}</h1>
            <p class="text-center">{{ order.status === 'closed' ? 'RECEIPT' : 'BILL' }} #{{ order.id }}</p>
            <p class="text-center">{{ order.label }} · {{ when(order.closed_at ?? order.created_at) }}</p>
            <p class="text-center">Served by {{ order.employee?.name }}</p>
            <hr class="my-2 border-dashed border-black" />
            <div v-for="line in order.lines.filter((l) => l.status !== 'voided')" :key="line.id" class="mb-1">
                <div class="flex justify-between gap-2"><span>{{ line.quantity }} {{ line.name }}</span><span>{{ money(line.quantity * line.unit_price) }}</span></div>
                <div v-for="m in line.modifiers ?? []" :key="m.id" class="pl-3">+ {{ m.name }}</div>
            </div>
            <hr class="my-2 border-dashed border-black" />
            <div class="flex justify-between"><span>Subtotal</span><span>{{ money(order.bill.subtotal) }}</span></div>
            <div v-if="order.bill.service_charge" class="flex justify-between"><span>Service charge</span><span>{{ money(order.bill.service_charge) }}</span></div>
            <div v-if="order.bill.tax" class="flex justify-between">
                <span>{{ settings.tax_name }}{{ order.bill.tax_inclusive ? ' incl.' : '' }}</span><span>{{ money(order.bill.tax) }}</span>
            </div>
            <div class="flex justify-between text-sm font-bold"><span>TOTAL</span><span>{{ money(order.bill.total) }}</span></div>
            <template v-if="order.payments.length">
                <hr class="my-2 border-dashed border-black" />
                <div v-for="p in order.payments" :key="p.id">
                    <div class="flex justify-between"><span>{{ tenderNames[p.tender] }}</span><span>{{ money(p.amount) }}</span></div>
                    <div v-if="p.tender === 'cash_usd'" class="pl-3">{{ money(p.tendered, 'USD') }} @ {{ p.exchange_rate.toLocaleString() }}</div>
                    <div v-if="p.reference" class="pl-3">Ref {{ p.reference }}</div>
                    <div v-if="p.tip" class="flex justify-between pl-3"><span>Tip</span><span>{{ money(p.tip) }}</span></div>
                    <div v-if="p.change_given" class="flex justify-between pl-3"><span>Change</span><span>{{ money(p.change_given) }}</span></div>
                </div>
            </template>
            <p v-if="order.bill.balance" class="mt-2 flex justify-between font-bold"><span>BALANCE DUE</span><span>{{ money(order.bill.balance) }}</span></p>
            <p v-if="settings.receipt_footer" class="mt-3 text-center">{{ settings.receipt_footer }}</p>
        </article>
    </div>
</template>
