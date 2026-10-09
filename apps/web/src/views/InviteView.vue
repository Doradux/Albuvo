<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowUpRight, Check, LockKeyhole, UsersRound, Heart, ArrowLeft } from '@lucide/vue'
import { api, saveGuest } from '../lib/api'
const route=useRoute(); const router=useRouter()
const token=String(route.params.token)
const albumId=ref(''); const albumTitle=ref(''); const name=ref('')
const accepted=ref(false);const busy=ref(false);const loading=ref(true);const error=ref('')
onMounted(async()=>{
  try{
    const response=await api<{album:{id:string;title:string}}> ('POST','/album-invites/preview',{token})
    albumId.value=response.album.id;albumTitle.value=response.album.title
  }catch(e){error.value=(e as Error).message}finally{loading.value=false}
})
async function join(){
  busy.value=true;error.value=''
  try{
    const result=await api<{guest_token:string;album_id:string}>('POST','/album-invites/resolve',
      {token,display_name:name.value,accept_rules:accepted.value})
    saveGuest(result.album_id,result.guest_token)
    await router.replace('/a/'+result.album_id)
  }catch(e){error.value=(e as Error).message}finally{busy.value=false}
}
</script>
<template>
  <div class="invite-page page-pad"><div class="container narrow">
    <RouterLink to="/" class="back-link"><ArrowLeft :size="17"/> Inicio</RouterLink>
    <div class="invite-card surface">
      <div class="invite-art"><span>✿</span></div>
      <div v-if="loading" class="loading-placeholder">Comprobando invitación...</div>
      <div v-else-if="!albumId"><h1>Esta invitación ya no está disponible.</h1><p>{{error||'Puede haber caducado o haber sido revocada.'}}</p><RouterLink to="/" class="btn btn-dark">Volver al inicio</RouterLink></div>
      <template v-else><span class="pill"><Heart :size="14"/> Te han invitado</span><h1>Los mejores recuerdos se comparten.</h1><p>Alguien quiere compartir contigo el álbum <strong>{{albumTitle}}</strong>.</p><div class="guest-benefits"><span><UsersRound :size="19"/> Colabora con tus propias fotografías</span><span><LockKeyhole :size="19"/> Solo con este enlace privado</span><span><Check :size="19"/> No necesitas crear una cuenta</span></div>
        <form class="guest-form" @submit.prevent="join"><div v-if="error" class="form-error">{{error}}</div><div class="form-row"><label class="form-label" for="guest-name">¿Cómo te llamamos?</label><input id="guest-name" v-model="name" class="field" placeholder="Tu nombre o apodo" minlength="2" maxlength="80" required/></div><label class="form-check"><input type="checkbox" v-model="accepted" required/><span>Acepto las normas de la comunidad. (Borrador de desarrollo).</span></label><button type="submit" class="btn btn-dark auth-submit" :disabled="busy">{{busy?'Entrando...':'Entrar al álbum'}} <ArrowUpRight :size="19"/></button></form>
      </template>
    </div>
  </div></div>
</template>
