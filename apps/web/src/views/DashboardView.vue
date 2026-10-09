<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ArrowUpRight, Plus, FolderOpen, Images, Search, ShieldCheck, ArrowRight, Mail } from '@lucide/vue'
import { api, bytes, type Album } from '../lib/api'
import { useSession } from '../stores/session'
const session=useSession(); const router=useRouter()
const albums=ref<Album[]>([]); const filter=ref(''); const loading=ref(true); const error=ref('')
const filtered=computed(()=>albums.value.filter(a=>a.title.toLowerCase().includes(filter.value.toLowerCase())))
const colors=['#dcead8','#f6e2c3','#e5def4','#f4d9d9','#d7e7f0']
async function load() {
  await session.hydrate()
  if(!session.user) { await router.replace('/login');return }
  try { albums.value=(await api<{albums:Album[]}>('GET','/albums')).albums }
  catch(e) { error.value=(e as Error).message }
  finally{loading.value=false}
}
async function resend() { try { await api('POST','/auth/resend'); error.value='Correo de verificación reenviado.' } catch(e) { error.value=(e as Error).message } }
onMounted(load)
</script>
<template>
  <div class="dashboard page-pad"><div class="container">
    <div class="dashboard-welcome"><div><span class="eyebrow">Tu pequeño rincón de recuerdos</span><h1>Hola, {{session.user?.name?.split(' ')[0]||'de nuevo'}} <span class="welcome-flower">✳</span></h1><p>Todos tus momentos especiales, reunidos aquí.</p></div><RouterLink class="btn btn-dark" to="/new"><Plus :size="18"/> Nuevo álbum</RouterLink></div>
    <div v-if="session.user && !session.user.email_verified_at" class="notice"><Mail :size="21"/><div><strong>Verifica tu correo para crear álbumes.</strong><p>Te hemos enviado un enlace de confirmación.</p></div><button class="btn btn-ghost btn-small" @click="resend">Reenviar</button></div>
    <div class="album-heading"><div><h2>Mis álbumes <span v-if="albums.length" class="album-count">{{albums.length}}</span></h2><p>Recuerdos que van creciendo contigo.</p></div><label class="search-field"><Search :size="18"/><input v-model="filter" placeholder="Buscar álbum..." aria-label="Buscar álbum"/></label></div>
    <div v-if="error" class="form-error">{{error}}</div>
    <div v-if="loading" class="loading-placeholder">Buscando tus recuerdos...</div>
    <div v-else-if="filtered.length" class="album-grid">
      <RouterLink v-for="(album,i) in filtered" :key="album.id" :to="'/a/'+album.id" class="album-card">
        <div class="album-card-art" :style="{background: colors[i % colors.length]}">
          <span class="album-card-flower">{{['✿','☼','✺','❀','✳'][i%5]}}</span><span class="album-card-corner"><ArrowUpRight :size="19"/></span>
          <div class="album-card-details"><ShieldCheck :size="16"/> Privado</div>
        </div>
        <div class="album-card-info"><span class="album-date">{{new Date(album.created_at).toLocaleDateString('es-ES',{day:'numeric',month:'long',year:'numeric'})}}</span><h3>{{album.title}}</h3><p>{{album.description||'Un lugar para compartir momentos.'}}</p><div class="album-stat"><span><Images :size="15"/> {{album.media_count||0}} fotos</span><span>{{bytes(album.used_bytes)}} / {{bytes(album.quota_bytes)}}</span></div></div>
      </RouterLink>
      <RouterLink to="/new" class="add-album-card"><div class="add-icon"><Plus :size="30"/></div><h3>Una nueva historia</h3><p>¿Qué recuerdos vamos a guardar ahora?</p><span>Crear álbum <ArrowRight :size="16"/></span></RouterLink>
    </div>
    <div v-else class="empty-state dashboard-empty"><div class="empty-art"><FolderOpen :size="57" :stroke-width="1.4"/></div><h3>{{filter?'No hemos encontrado ese álbum':'Aquí empieza tu historia'}}</h3><p>{{filter?'Prueba con otro nombre.':'Crea tu primer álbum e invita a tus personas favoritas.'}}</p><RouterLink v-if="!filter" to="/new" class="btn btn-dark"><Plus :size="17"/> Crear mi primer álbum</RouterLink></div>
  </div></div>
</template>
