<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { api } from '../api';

const props = defineProps({ station: { type: String, required: true } });

const open = ref([]);
const recent = ref([]);
const now = ref(Date.now());
const error = ref('');

async function load() {
    try {
        const data = await api('GET', `/stations/${props.station}/tickets`);
        open.value = data.open;
        recent.value = data.recent;
        error.value = '';
    } catch (e) {
        error.value = e.message;
    }
}

async function act(ticket, action) {
    try {
        await api('POST', `/tickets/${ticket.id}/${action}`);
        await load();
    } catch (e) {
        error.value = e.message;
    }
}

function minutes(ticket) {
    return Math.max(0, Math.floor((now.value - new Date(ticket.created_at).getTime()) / 60000));
}

function ageStyle(ticket) {
    const m = minutes(ticket);
    if (m >= 20) return 'border-red-500 bg-red-50';
    if (m >= 10) return 'border-amber-500 bg-amber-50';

    return 'border-slate-300 bg-white';
}

function label(ticket) {
    const o = ticket.order;
    if (o.service_type === 'table') return `Table ${o.dining_table?.name ?? ''}`;
    if (o.service_type === 'tab') return `Tab: ${o.tab_name}`;

    return `Counter #${o.id}${o.customer_name ? ` (${o.customer_name})` : ''}`;
}

// Polling over the venue network keeps working with no internet. Live push (Reverb) can replace it later.
let poll;
let clock;
onMounted(() => {
    load();
    poll = setInterval(load, 3000);
    clock = setInterval(() => (now.value = Date.now()), 15000);
});
onUnmounted(() => {
    clearInterval(poll);
    clearInterval(clock);
});
</script>

<template>
    <div class="min-h-[calc(100vh-56px)] bg-slate-800 p-4">
        <div class="mb-3 flex items-center text-white">
            <h1 class="text-2xl font-semibold capitalize">{{ station }}</h1>
            <span class="ml-3 rounded-full bg-slate-700 px-3 py-1 text-sm">{{ open.length }} open</span>
            <span v-if="error" class="ml-auto text-sm text-red-300">{{ error }}</span>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div v-for="ticket in open" :key="ticket.id" class="flex flex-col rounded-xl border-4" :class="ageStyle(ticket)">
                <div class="flex items-baseline justify-between border-b px-3 py-2">
                    <span class="text-lg font-bold">{{ label(ticket) }}</span>
                    <span class="font-mono text-sm">{{ minutes(ticket) }} min</span>
                </div>
                <ul class="flex-1 space-y-2 px-3 py-2">
                    <li v-for="line in ticket.lines" :key="line.id" :class="{ 'text-red-600 line-through': line.status === 'voided' }">
                        <span class="font-bold">{{ line.quantity }}×</span> {{ line.name }}
                        <span v-if="line.status === 'voided'" class="text-xs font-semibold no-underline">VOID</span>
                        <div v-for="m in line.modifiers ?? []" :key="m.id" class="pl-6 text-sm text-slate-600">{{ m.name }}</div>
                        <div v-if="line.notes" class="pl-6 text-sm italic text-slate-600">“{{ line.notes }}”</div>
                    </li>
                </ul>
                <button class="m-2 h-14 rounded-lg bg-emerald-600 text-lg font-semibold text-white active:bg-emerald-700" @click="act(ticket, 'bump')">Done</button>
            </div>
            <p v-if="!open.length" class="col-span-full py-16 text-center text-slate-400">No open tickets.</p>
        </div>

        <div v-if="recent.length" class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase text-slate-400">Recently done</h2>
            <div class="flex flex-wrap gap-2">
                <button v-for="ticket in recent" :key="ticket.id" class="rounded-lg bg-slate-700 px-3 py-2 text-sm text-slate-200" @click="act(ticket, 'recall')">
                    {{ label(ticket) }} · recall
                </button>
            </div>
        </div>
    </div>
</template>
