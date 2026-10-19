<script setup>
import { computed, ref } from 'vue';
import { money } from '../money';

const props = defineProps({ item: { type: Object, required: true } });
const emit = defineEmits(['add', 'cancel']);

const chosen = ref(new Set());
const quantity = ref(1);
const notes = ref('');

function toggle(group, modifier) {
    const set = new Set(chosen.value);
    if (set.has(modifier.id)) {
        set.delete(modifier.id);
    } else {
        // A pick-one group swaps the choice instead of refusing it.
        if (group.max_select === 1) group.modifiers.forEach((m) => set.delete(m.id));
        else if (countIn(group, set) >= group.max_select) return;
        set.add(modifier.id);
    }
    chosen.value = set;
}

function countIn(group, set = chosen.value) {
    return group.modifiers.filter((m) => set.has(m.id)).length;
}

const missing = computed(() => props.item.modifier_groups.filter((g) => countIn(g) < g.min_select));

const unitPrice = computed(() => props.item.price + props.item.modifier_groups
    .flatMap((g) => g.modifiers)
    .filter((m) => chosen.value.has(m.id))
    .reduce((sum, m) => sum + m.price_delta, 0));

function add() {
    if (missing.value.length) return;
    emit('add', { modifier_ids: [...chosen.value], quantity: quantity.value, notes: notes.value || null });
}
</script>

<template>
    <div class="fixed inset-0 z-10 flex items-center justify-center bg-black/50 p-4" @click.self="emit('cancel')">
        <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5">
            <h2 class="text-xl font-semibold">{{ item.name }}</h2>
            <div v-for="group in item.modifier_groups" :key="group.id" class="mt-4">
                <h3 class="mb-2 text-sm font-semibold text-slate-600">
                    {{ group.name }}
                    <span class="font-normal">{{ group.min_select ? '(required)' : '(optional)' }}</span>
                </h3>
                <div class="flex flex-wrap gap-2">
                    <button v-for="m in group.modifiers" :key="m.id" type="button" @click="toggle(group, m)"
                        class="rounded-lg border-2 px-3 py-2"
                        :class="chosen.has(m.id) ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200'">
                        {{ m.name }}<span v-if="m.price_delta" class="ml-1 text-xs text-slate-500">+{{ money(m.price_delta) }}</span>
                    </button>
                </div>
            </div>
            <input v-model="notes" placeholder="Note for the kitchen or bar" class="mt-4 w-full rounded-lg border px-3 py-2" />
            <div class="mt-4 flex items-center gap-3">
                <button type="button" class="h-11 w-11 rounded-lg bg-slate-100 text-xl" @click="quantity = Math.max(1, quantity - 1)">−</button>
                <span class="w-6 text-center text-lg">{{ quantity }}</span>
                <button type="button" class="h-11 w-11 rounded-lg bg-slate-100 text-xl" @click="quantity++">+</button>
                <span class="ml-auto text-lg font-semibold">{{ money(unitPrice * quantity) }}</span>
            </div>
            <p v-if="missing.length" class="mt-3 text-sm text-amber-700">Choose {{ missing.map((g) => g.name).join(', ') }}.</p>
            <div class="mt-4 flex gap-2">
                <button type="button" class="h-12 flex-1 rounded-xl bg-slate-100" @click="emit('cancel')">Cancel</button>
                <button type="button" class="h-12 flex-1 rounded-xl bg-emerald-600 font-semibold text-white disabled:opacity-40"
                    :disabled="missing.length > 0" @click="add">Add</button>
            </div>
        </div>
    </div>
</template>
