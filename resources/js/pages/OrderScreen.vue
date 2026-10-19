<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, session } from '../api';
import { money, setCurrency } from '../money';
import ModifierPicker from '../components/ModifierPicker.vue';
import PayPanel from '../components/PayPanel.vue';

const props = defineProps({ id: { type: String, required: true } });

const order = ref(null);
const categories = ref([]);
const activeCategory = ref(null);
const course = ref(1);
const picking = ref(null);
const paying = ref(false);
const error = ref('');
const flash = ref('');

const items = computed(() => categories.value.find((c) => c.id === activeCategory.value)?.items ?? []);
const liveLines = computed(() => order.value?.lines.filter((l) => l.status !== 'voided') ?? []);
const isOpen = computed(() => order.value?.status === 'open');
const unsentCourses = computed(() => [...new Set(liveLines.value.filter((l) => l.status === 'new').map((l) => l.course))].sort());

const statusStyle = {
    new: 'bg-sky-100 text-sky-800',
    fired: 'bg-amber-100 text-amber-800',
    ready: 'bg-emerald-100 text-emerald-800',
    voided: 'bg-slate-200 text-slate-500 line-through',
};

async function run(fn) {
    error.value = '';
    try {
        await fn();
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(() => run(async () => {
    const [menu, current] = await Promise.all([api('GET', '/menu'), api('GET', `/orders/${props.id}`)]);
    setCurrency(menu.currency);
    categories.value = menu.categories;
    activeCategory.value = menu.categories[0]?.id ?? null;
    order.value = current;
}));

function tapItem(item) {
    if (item.sold_out || !isOpen.value) return;
    if (item.modifier_groups.length) picking.value = item;
    else addLine(item, {});
}

function addLine(item, extra) {
    picking.value = null;
    run(async () => {
        order.value = await api('POST', `/orders/${props.id}/lines`, { menu_item_id: item.id, course: course.value, ...extra });
    });
}

function fire(onlyCourse) {
    run(async () => {
        const result = await api('POST', `/orders/${props.id}/fire`, onlyCourse ? { course: onlyCourse } : {});
        order.value = result.order;
        flash.value = `Sent ${result.tickets.length} ticket${result.tickets.length === 1 ? '' : 's'}`;
        setTimeout(() => (flash.value = ''), 2500);
    });
}

function voidLine(line) {
    let reason = null;
    if (line.status !== 'new') {
        reason = prompt(`Reason for voiding ${line.name}?`);
        if (!reason) return;
    }
    run(async () => {
        order.value = await api('POST', `/order-lines/${line.id}/void`, { reason });
    });
}

function toggleSoldOut(item) {
    run(async () => {
        const updated = await api('PATCH', `/menu-items/${item.id}/sold-out`, { sold_out: !item.sold_out });
        item.sold_out = updated.sold_out;
    });
}

function modifierText(line) {
    return (line.modifiers ?? []).map((m) => m.name).join(', ');
}
</script>

<template>
    <div v-if="order" class="grid h-[calc(100vh-56px)] grid-cols-1 md:grid-cols-[1fr_380px]">
        <section class="flex flex-col overflow-hidden p-4">
            <div class="mb-3 flex flex-wrap gap-2">
                <button v-for="c in categories" :key="c.id" @click="activeCategory = c.id"
                    class="rounded-full px-4 py-2 font-medium"
                    :class="c.id === activeCategory ? 'bg-slate-900 text-white' : 'bg-white'">{{ c.name }}</button>
                <div class="ml-auto flex items-center gap-1 text-sm">
                    <span class="mr-1 text-slate-500">Course</span>
                    <button v-for="n in [1, 2, 3]" :key="n" @click="course = n" class="h-9 w-9 rounded-lg font-semibold"
                        :class="course === n ? 'bg-sky-600 text-white' : 'bg-white'">{{ n }}</button>
                </div>
            </div>
            <div class="grid flex-1 auto-rows-min grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3 xl:grid-cols-4">
                <div v-for="item in items" :key="item.id" class="relative">
                    <button @click="tapItem(item)" :disabled="item.sold_out"
                        class="flex h-24 w-full flex-col justify-between rounded-xl bg-white p-3 text-left shadow-sm active:bg-slate-200 disabled:opacity-40">
                        <span class="font-medium leading-tight">{{ item.name }}</span>
                        <span class="text-sm text-slate-600">{{ item.sold_out ? 'Sold out' : money(item.price) }}</span>
                    </button>
                    <button v-if="session.employee?.can_manage" @click="toggleSoldOut(item)"
                        class="absolute right-1 top-1 rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                        :title="item.sold_out ? 'Back in stock' : 'Mark sold out'">{{ item.sold_out ? 'Restock' : '86' }}</button>
                </div>
            </div>
        </section>

        <aside class="flex flex-col border-l bg-white">
            <div class="border-b p-4">
                <RouterLink to="/floor" class="text-sm text-sky-700">← Floor</RouterLink>
                <h1 class="text-xl font-semibold">{{ order.label }}</h1>
                <p class="text-sm text-slate-500">
                    {{ order.guest_count ? `${order.guest_count} guests · ` : '' }}opened by {{ order.employee?.name }}
                </p>
            </div>
            <p v-if="error" class="mx-4 mt-3 rounded bg-red-100 px-3 py-2 text-sm text-red-700">{{ error }}</p>
            <p v-if="flash" class="mx-4 mt-3 rounded bg-emerald-100 px-3 py-2 text-sm text-emerald-800">{{ flash }}</p>
            <ul class="flex-1 divide-y overflow-y-auto px-4">
                <li v-for="line in order.lines" :key="line.id" class="flex items-start gap-2 py-3">
                    <span class="w-6 text-right font-semibold">{{ line.quantity }}</span>
                    <div class="flex-1">
                        <div :class="{ 'text-slate-400 line-through': line.status === 'voided' }">{{ line.name }}</div>
                        <div v-if="modifierText(line)" class="text-xs text-slate-500">{{ modifierText(line) }}</div>
                        <div v-if="line.notes" class="text-xs italic text-slate-500">“{{ line.notes }}”</div>
                        <div class="mt-1 flex gap-1 text-xs">
                            <span class="rounded px-1.5" :class="statusStyle[line.status]">{{ line.status }}</span>
                            <span class="rounded bg-slate-100 px-1.5">course {{ line.course }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div>{{ money(line.quantity * line.unit_price) }}</div>
                        <button v-if="line.status !== 'voided' && isOpen" class="text-xs text-red-600" @click="voidLine(line)">Void</button>
                    </div>
                </li>
                <li v-if="!order.lines.length" class="py-8 text-center text-slate-400">Tap items to add them.</li>
            </ul>
            <div class="space-y-2 border-t p-4">
                <div class="flex justify-between text-lg font-semibold"><span>Total</span><span>{{ money(order.bill.total) }}</span></div>
                <div v-if="order.bill.paid" class="flex justify-between text-sm text-emerald-700"><span>Paid</span><span>{{ money(order.bill.paid) }}</span></div>
                <div v-if="unsentCourses.length > 1 && isOpen" class="flex gap-2">
                    <button v-for="c in unsentCourses" :key="c" @click="fire(c)"
                        class="h-11 flex-1 rounded-xl bg-sky-100 font-medium text-sky-900">Send course {{ c }}</button>
                </div>
                <div v-if="isOpen" class="flex gap-2">
                    <button @click="fire(null)" :disabled="!unsentCourses.length"
                        class="h-14 flex-1 rounded-xl bg-sky-600 text-lg font-semibold text-white disabled:opacity-40">Send all</button>
                    <button @click="paying = true" :disabled="!order.bill.total"
                        class="h-14 flex-1 rounded-xl bg-emerald-600 text-lg font-semibold text-white disabled:opacity-40">Pay</button>
                </div>
                <div v-else class="flex gap-2">
                    <span class="flex-1 rounded-xl bg-slate-100 py-3 text-center font-semibold capitalize">{{ order.status }}</span>
                    <RouterLink :to="`/orders/${order.id}/receipt`" class="flex-1 rounded-xl bg-slate-900 py-3 text-center font-semibold text-white">Receipt</RouterLink>
                </div>
                <RouterLink v-if="isOpen && order.lines.length" :to="`/orders/${order.id}/receipt`" class="block text-center text-sm text-sky-700">Print bill</RouterLink>
            </div>
        </aside>

        <PayPanel v-if="paying" :order="order" @paid="(o) => (order = o)" @close="paying = false" />
        <ModifierPicker v-if="picking" :item="picking" @cancel="picking = null" @add="(extra) => addLine(picking, extra)" />
    </div>
    <p v-else-if="error" class="p-6 text-red-700">{{ error }}</p>
</template>
