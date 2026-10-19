import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import Login from './pages/Login.vue';
import Floor from './pages/Floor.vue';
import OrderScreen from './pages/OrderScreen.vue';
import Kds from './pages/Kds.vue';
import Receipt from './pages/Receipt.vue';
import Drawer from './pages/Drawer.vue';
import Manager from './pages/Manager.vue';
import { restoreSession, session } from './api';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/floor' },
        { path: '/login', component: Login, meta: { public: true } },
        { path: '/floor', component: Floor, meta: { takesOrders: true } },
        { path: '/orders/:id', component: OrderScreen, props: true, meta: { takesOrders: true } },
        { path: '/orders/:id/receipt', component: Receipt, props: true, meta: { takesOrders: true } },
        { path: '/drawer', component: Drawer, meta: { takesOrders: true } },
        { path: '/manager', component: Manager, meta: { manages: true } },
        { path: '/kds/:station', component: Kds, props: true },
        { path: '/:pathMatch(.*)*', redirect: '/floor' },
    ],
});

let restored = false;

router.beforeEach(async (to) => {
    if (!restored) {
        restored = true;
        await restoreSession();
    }
    if (to.meta.public) return true;
    if (!session.employee) return '/login';
    if (to.meta.takesOrders && !session.employee.can_take_orders) return '/kds/kitchen';
    if (to.meta.manages && !session.employee.can_manage) return '/floor';

    return true;
});

createApp(App).use(router).mount('#app');
