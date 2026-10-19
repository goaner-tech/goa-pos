<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api';
import { money } from '../money';

const router = useRouter();
const floor = ref({ tables: [], tabs: [], counter: [] });
const tabName = ref('');
const customerName = ref('');
const error = ref('');

const sections = computed(() => {
    const groups = {};
    for (const table of floor.value.tables) {
        (groups[table.section || 'Tables'] ??= []).push(table);
    }

    return groups;
});

async function load() {
    try {
        floor.value = await api('GET', '/floor');
    } catch (e) {
        error.value = e.message;
    }
}

async function open(payload) {
    error.value = '';
    try {
        const order = await api('POST', '/orders', payload);
        router.push(`/orders/${order.id}`);
    } catch (e) {
        error.value = e.message;
    }
}

function tapTable(table) {
    if (table.open_order) {
        router.push(`/orders/${table.open_order.id}`);
        return;
    }
    const guests = Number(prompt(`Guests at table ${table.name}?`, String(table.seats)));
    if (guests > 0) open({ service_type: 'table', dining_table_id: table.id, guest_count: guests });
}

function openTab() {
    if (tabName.value.trim()) open({ service_type: 'tab', tab_name: tabName.value.trim() });
}

function openCounter() {
    open({ service_type: 'counter', customer_name: customerName.value.trim() || null });
}

let timer;
onMounted(() => {
    load();
    timer = setInterval(load, 10000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div class="grid gap-6 p-4 lg:grid-cols-[2fr_1fr]">
        <section>
            <p v-if="error" class="mb-3 rounded bg-red-100 px-3 py-2 text-red-700">{{ error }}</p>
            <div v-for="(tables, section) in sections" :key="section" class="mb-6">
                <h2 class="mb-2 text-sm font-semibold uppercase text-slate-500">{{ section }}</h2>
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 xl:grid-cols-6">
                    <button v-for="table in tables" :key="table.id" @click="tapTable(table)"
                        class="flex h-24 flex-col items-center justify-center rounded-xl border-2 text-center"
                        :class="table.open_order ? 'border-amber-500 bg-amber-50' : 'border-slate-300 bg-white'">
                        <span class="text-xl font-semibold">{{ table.name }}</span>
                        <span v-if="table.open_order" class="text-xs text-amber-700">{{ money(table.open_order.total) }}</span>
                        <span v-else class="text-xs text-slate-500">{{ table.seats }} seats</span>
                    </button>
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <div class="rounded-xl bg-white p-4">
                <h2 class="mb-2 font-semibold">Bar tabs</h2>
                <form class="mb-3 flex gap-2" @submit.prevent="openTab">
                    <input v-model="tabName" placeholder="Guest name" class="flex-1 rounded-lg border px-3 py-2" />
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-white">Open tab</button>
                </form>
                <button v-for="order in floor.tabs" :key="order.id" @click="router.push(`/orders/${order.id}`)"
                    class="mb-2 flex w-full justify-between rounded-lg bg-slate-50 px-3 py-3 text-left hover:bg-slate-100">
                    <span>{{ order.tab_name }}</span><span class="text-slate-600">{{ money(order.total) }}</span>
                </button>
            </div>

            <div class="rounded-xl bg-white p-4">
                <h2 class="mb-2 font-semibold">Counter</h2>
                <form class="mb-3 flex gap-2" @submit.prevent="openCounter">
                    <input v-model="customerName" placeholder="Name to call (optional)" class="flex-1 rounded-lg border px-3 py-2" />
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-white">New order</button>
                </form>
                <button v-for="order in floor.counter" :key="order.id" @click="router.push(`/orders/${order.id}`)"
                    class="mb-2 flex w-full justify-between rounded-lg bg-slate-50 px-3 py-3 text-left hover:bg-slate-100">
                    <span>{{ order.label }}</span><span class="text-slate-600">{{ money(order.total) }}</span>
                </button>
            </div>
        </aside>
    </div>
</template>
