
import { createRouter, createWebHistory } from 'vue-router'

import Home from '../views/home.vue'
import Login from '../views/login.vue'
import register from '../views/register.vue'
import Dashboard from '../views/dashboard.vue'
import markets from '../views/markets.vue'
import coin from '../views/coin.vue'
import orders from '../views/orders.vue'
// import { p } from 'vue-router/dist/router-CWoNjPRp.mjs'

const routes = [
  {
    path: '/',
    name: 'home',
    component: Home,
  },
  {
    path: '/login',
    name: 'login',
    component: Login,
  },
  {
    path: '/register',
    name: 'register',
    component: register,
  },
  {
  path: '/dashboard',
  name: 'dashboard',
  component: Dashboard,
},
{path: '/markets',
name: 'markets',
component: markets,
},
{
    path: '/coin/:symbol',
    name: 'coin',
    component: coin,
    props: true,
  },
  {
    path: '/orders',
    name: 'orders',
    component: orders,
  },


]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
