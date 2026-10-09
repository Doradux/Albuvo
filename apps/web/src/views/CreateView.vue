<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ArrowLeft, ArrowRight, Check, LockKeyhole, UsersRound, ShieldCheck, Sparkles, WandSparkles } from '@lucide/vue'
import { api, type Album } from '../lib/api'
import { useSession } from '../stores/session'
const router=useRouter(); const session=useSession()
const step=ref(1); const title=ref(''); const description=ref(''); const template=ref('Amigos')
const allowGuests=ref(true); const moderation=ref(true); const busy=ref(false); const error=ref('')
const templates=[{name:'Amigos',symbol:'♡'},{name:'Viaje',symbol:'☼'},{name:'Familia',symbol:'✿'},{name:'Fiesta',symbol:'✦'},{name:'Otro',symbol:'✳'}]
onMounted(async()=>{await session.hydrate();if(!session.user) await router.replace('/login')})
function next() { if(step.value===1 && title.value.trim().length===0) {error.value='Ponle un nombre al álbum.';return} error.value='';step.value++ }
async function create() {
  busy.value=true;error.value=''
  try {
    const {album}=await api<{album:Album}>('POST','/albums',{title:title.value.trim(),description:description.value.trim()||null,
      allow_guest_upload:allowGuests.value,require_upload_approval:moderation.value})
    router.replace('/a/'+album.id+'?new=1')
  } catch(e){error.value=(e as Error).message} finally{busy.value=false}
}
</script>
<template>
  <div class="create-page page-pad"><div class="container narrow">
    <RouterLink to="/app" class="back-link"><ArrowLeft :size="17"/> Mis álbumes</RouterLink>
    <div class="wizard-progress"><span v-for="s in 3" :key="s" :class="{active:s<=step}"></span></div>
    <div class="wizard-heading"><span class="eyebrow">Paso {{step}} de 3</span><h1>{{step===1?'Toda historia merece su propio álbum.':step===2?'Tú decides cómo compartirlo.':'¡Tu álbum casi está listo!'}}</h1><p>{{step===1?'Empecemos poniendo un nombre a esos momentos.':step===2?'Un rincón seguro para compartir con quien tú quieras.':'Confirma que todo está a tu gusto y empezamos.'}}</p></div>
    <form class="wizard-card surface" @submit.prevent="step<3?next():create()">
      <div v-if="error" class="form-error" role="alert">{{error}}</div>
      <template v-if="step===1">
        <label class="form-label">¿Qué tipo de recuerdos vais a guardar?</label>
        <div class="template-grid"><button v-for="t in templates" :key="t.name" type="button" :class="['template-option',{selected:template===t.name}]" @click="template=t.name"><span>{{t.symbol}}</span>{{t.name}}</button></div>
        <div class="form-row"><label class="form-label" for="album-title">Nombre del álbum <span class="required">*</span></label><input id="album-title" class="field" v-model="title" maxlength="100" placeholder="Por ejemplo, nuestro verano de 2026" required autofocus/></div>
        <div class="form-row"><label class="form-label" for="album-description">Una pequeña descripción <span class="optional">(opcional)</span></label><textarea id="album-description" class="field" v-model="description" maxlength="500" rows="3" placeholder="¿De qué va esta historia?"></textarea></div>
      </template>
      <template v-else-if="step===2">
        <div class="security-intro"><div class="security-big-icon"><LockKeyhole :size="28"/></div><h3>Privado desde el principio</h3><p>Solo las personas autorizadas y quienes reciban un enlace de invitación válido podrán acceder.</p></div>
        <label class="toggle-row"><div><strong><UsersRound :size="19"/> Permitir fotos de invitados</strong><p>Quien tenga una invitación válida podrá subir fotos sin crear cuenta.</p></div><input type="checkbox" v-model="allowGuests" role="switch" aria-label="Permitir fotos de invitados"/></label>
        <label class="toggle-row"><div><strong><ShieldCheck :size="19"/> Revisar las fotos antes de mostrarlas</strong><p>Las fotos nuevas esperarán a tu aprobación antes de aparecer en la galería.</p></div><input type="checkbox" v-model="moderation" role="switch" aria-label="Revisar las fotos antes de mostrarlas"/></label>
        <div class="privacy-tip"><Sparkles :size="18"/> Puedes cambiar estos permisos más adelante.</div>
      </template>
      <template v-else>
        <div class="confirm-illustration"><span>{{templates.find(t=>t.name===template)?.symbol}}</span></div>
        <h3 class="confirm-title">{{title}}</h3><p class="text-center muted">{{description||'Un nuevo lugar para los recuerdos compartidos.'}}</p>
        <div class="confirm-list"><div><LockKeyhole :size="18"/> Álbum privado <Check :size="18"/></div><div><UsersRound :size="18"/> {{allowGuests?'Invitados pueden aportar fotos':'Solo miembros registrados suben fotos'}} <Check :size="18"/></div><div><ShieldCheck :size="18"/> {{moderation?'Fotos pendientes de aprobación':'Publicación inmediata'}} <Check :size="18"/></div></div>
      </template>
      <div class="wizard-actions"><button v-if="step>1" type="button" class="btn btn-light" @click="step--"><ArrowLeft :size="18"/> Atrás</button><span v-else class="muted small-info">Sin prisas, es tu historia.</span><button type="submit" class="btn btn-dark" :disabled="busy">{{busy?'Creando...':step===3?'Crear mi álbum':'Continuar'}} <WandSparkles v-if="step===3" :size="18"/><ArrowRight v-else :size="18"/></button></div>
    </form>
  </div></div>
</template>
