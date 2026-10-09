import { createRouter, createWebHistory } from 'vue-router'
import HomeView from './views/HomeView.vue'
import AuthView from './views/AuthView.vue'
import DashboardView from './views/DashboardView.vue'
import CreateView from './views/CreateView.vue'
import AlbumView from './views/AlbumView.vue'
import InviteView from './views/InviteView.vue'

export default createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    { path: '/', component: HomeView },
    { path: '/login', component: AuthView, props: { mode: 'login' } },
    { path: '/signup', component: AuthView, props: { mode: 'signup' } },
    { path: '/app', component: DashboardView },
    { path: '/new', component: CreateView },
    { path: '/a/:id', component: AlbumView },
    { path: '/invite/:token', component: InviteView },
    { path: '/:pathMatch(.*)*', redirect: '/' }
  ]
})
