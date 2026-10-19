<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../api';

const router = useRouter();
const pin = ref('');
const error = ref('');
const busy = ref(false);

function press(key) {
    error.value = '';
    if (key === 'clear') pin.value = '';
    else if (key === 'back') pin.value = pin.value.slice(0, -1);
    else if (pin.value.length < 8) pin.value += key;
}

async function submit() {
    if (pin.value.length < 4 || busy.value) return;
    busy.value = true;
    try {
        const employee = await login(pin.value);
        router.push(employee.can_take_orders ? '/floor' : '/kds/kitchen');
    } catch (e) {
        error.value = e.message;
        pin.value = '';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-900">
        <form class="w-80 rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="submit">
            <h1 class="text-center text-xl font-semibold">Enter your PIN</h1>
            <div class="my-4 flex h-12 items-center justify-center gap-2 text-3xl tracking-widest">
                <span v-for="n in pin.length" :key="n">•</span>
            </div>
            <p v-if="error" class="mb-2 text-center text-sm text-red-600">{{ error }}</p>
            <div class="grid grid-cols-3 gap-2">
                <button v-for="k in ['1', '2', '3', '4', '5', '6', '7', '8', '9']" :key="k" type="button"
                    class="h-16 rounded-xl bg-slate-100 text-2xl font-medium active:bg-slate-300" @click="press(k)">{{ k }}</button>
                <button type="button" class="h-16 rounded-xl bg-slate-100 text-sm active:bg-slate-300" @click="press('clear')">Clear</button>
                <button type="button" class="h-16 rounded-xl bg-slate-100 text-2xl font-medium active:bg-slate-300" @click="press('0')">0</button>
                <button type="button" class="h-16 rounded-xl bg-slate-100 text-sm active:bg-slate-300" @click="press('back')">⌫</button>
            </div>
            <button type="submit" :disabled="pin.length < 4 || busy"
                class="mt-4 h-14 w-full rounded-xl bg-emerald-600 text-lg font-semibold text-white disabled:opacity-40">Log in</button>
        </form>
    </div>
</template>
