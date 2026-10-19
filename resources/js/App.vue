<script setup>
import { useRouter } from 'vue-router';
import { logout, session } from './api';

const router = useRouter();

async function signOut() {
    await logout();
    router.push('/login');
}
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header v-if="session.employee" class="flex items-center print:hidden gap-2 bg-slate-900 px-4 py-2 text-white">
            <span class="mr-4 font-semibold">GOA POS</span>
            <RouterLink v-if="session.employee.can_take_orders" to="/floor" class="rounded px-3 py-2 hover:bg-slate-700" active-class="bg-slate-700">Floor</RouterLink>
            <RouterLink v-if="session.employee.can_take_orders" to="/drawer" class="rounded px-3 py-2 hover:bg-slate-700" active-class="bg-slate-700">Drawer</RouterLink>
            <RouterLink v-if="session.employee.can_manage" to="/manager" class="rounded px-3 py-2 hover:bg-slate-700" active-class="bg-slate-700">Manager</RouterLink>
            <RouterLink to="/kds/kitchen" class="rounded px-3 py-2 hover:bg-slate-700" active-class="bg-slate-700">Kitchen</RouterLink>
            <RouterLink to="/kds/bar" class="rounded px-3 py-2 hover:bg-slate-700" active-class="bg-slate-700">Bar</RouterLink>
            <RouterLink to="/kds/barista" class="rounded px-3 py-2 hover:bg-slate-700" active-class="bg-slate-700">Barista</RouterLink>
            <span class="ml-auto text-sm text-slate-300">{{ session.employee.name }}</span>
            <button class="rounded bg-slate-700 px-3 py-2 text-sm hover:bg-slate-600" @click="signOut">Log out</button>
        </header>
        <main class="flex-1">
            <RouterView :key="$route.fullPath" />
        </main>
    </div>
</template>
