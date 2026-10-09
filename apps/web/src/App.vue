<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useSession } from './stores/session'
import { ArrowUpRight, LogOut } from '@lucide/vue'
const session = useSession()
const router = useRouter()
onMounted(()=>session.hydrate())
async function signOut() { await session.logout(); router.push('/') }
</script>

<template>
  <div class="site-shell">
    <header class="topbar">
      <div class="container topbar-inner">
        <RouterLink to="/" class="wordmark" aria-label="Albuvo, inicio">
          <span class="brand-symbol"><i></i><i></i><i></i><i></i><b></b></span>
          <span>albuvo<span class="brand-dot">.</span></span>
        </RouterLink>
        <nav class="desktop-nav" aria-label="Navegación principal">
          <RouterLink to="/#funciona">Cómo funciona</RouterLink>
          <RouterLink to="/#privacidad">Privacidad</RouterLink>
          <RouterLink to="/#planes">Planes</RouterLink>
        </nav>
        <div class="nav-actions">
          <template v-if="session.user">
            <RouterLink to="/app" class="nav-login">Mis álbumes</RouterLink>
            <button class="icon-button nav-logout" @click="signOut" aria-label="Cerrar sesión" title="Cerrar sesión"><LogOut :size="19"/></button>
            <RouterLink to="/new" class="btn btn-dark btn-small">Crear álbum <ArrowUpRight :size="16"/></RouterLink>
          </template>
          <template v-else>
            <RouterLink to="/login" class="nav-login">Entrar</RouterLink>
            <RouterLink to="/signup" class="btn btn-dark btn-small">Empezar gratis <ArrowUpRight :size="16"/></RouterLink>
          </template>
        </div>
      </div>
    </header>
    <main><RouterView/></main>
    <footer class="footer">
      <div class="container footer-inner">
        <div><span class="footer-brand">albuvo<span class="brand-dot">.</span></span><p>Tus recuerdos, entre todos.</p></div>
        <div class="footer-links"><RouterLink to="/">Inicio</RouterLink><RouterLink to="/#privacidad">Privacidad</RouterLink><a href="mailto:contacto@albuvo.local">Contacto de desarrollo</a></div>
      </div>
      <div class="container footnote">Proyecto en desarrollo · Los textos legales y planes comerciales aún no están aprobados para lanzamiento.</div>
    </footer>
  </div>
</template>
