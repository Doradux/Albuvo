<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowLeft, Plus, QrCode, Copy, LockKeyhole, UploadCloud, Check, X, ShieldCheck, Images, Clock3, UsersRound, ImagePlus, Link as LinkIcon, RefreshCcw, Eye, ArrowUpRight } from '@lucide/vue'
import QRCode from 'qrcode'
import { api, bytes, getGuest, uploadToSignedUrl, type Album, type Photo } from '../lib/api'
import { useSession } from '../stores/session'
const route=useRoute();const session=useSession()
const id=String(route.params.id)
const album=ref<Album|null>(null); const role=ref('');const canUpload=ref(false);const canModerate=ref(false)
const photos=ref<Photo[]>([]);const pending=ref<Photo[]>([]);const tab=ref<'gallery'|'moderation'|'sharing'>('gallery')
const loading=ref(true);const busy=ref(false);const error=ref('');const notice=ref('');const shareUrl=ref('')
const qr=ref('');const uploadPercent=ref<number|null>(null);const selected=ref<Photo|null>(null)
const invites=ref<{id:string;scope:string;revoked_at:string|null;expires_at:string;used_count:number}[]>([])
const memberEmail=ref('');const memberRole=ref('viewer')
const isOwner=computed(()=>['owner','admin'].includes(role.value))
async function refresh() {
  const guest=getGuest(id)
  const detail=await api<{album:Album;role:string;can_upload:boolean;can_moderate:boolean}>('GET','/albums/'+id,undefined,guest)
  album.value=detail.album;role.value=detail.role;canUpload.value=detail.can_upload;canModerate.value=detail.can_moderate
  photos.value=(await api<{media:Photo[]}>('GET','/albums/'+id+'/media',undefined,guest)).media
  if(canModerate.value) pending.value=(await api<{media:Photo[]}>('GET','/albums/'+id+'/moderation',undefined,guest)).media
}
async function load() {
  await session.hydrate()
  try {await refresh();if(route.query.new==='1')tab.value='sharing'}
  catch(e){error.value=(e as Error).message}finally{loading.value=false}
}
onMounted(load)
async function createInvite() {
  busy.value=true;error.value=''
  try {
    const result=await api<{share_url:string}>('POST','/albums/'+id+'/invites',{scope:'upload',hours:168})
    shareUrl.value=result.share_url
    qr.value=await QRCode.toDataURL(result.share_url,{margin:2,width:210,color:{dark:'#263b36',light:'#ffffff'}})
    notice.value='Invitación creada. Este enlace se muestra una sola vez: guárdalo ahora.'
    await loadInvites()
  }catch(e){error.value=(e as Error).message}finally{busy.value=false}
}
async function loadInvites() {
  if(!isOwner.value)return
  invites.value=(await api<{invites:typeof invites.value}>('GET','/albums/'+id+'/invites')).invites
}
async function revoke(inviteId:string) {
  if(!confirm('¿Revocar esta invitación? Dejará de permitir nuevas subidas.'))return
  try {await api('DELETE','/albums/'+id+'/invites/'+inviteId);shareUrl.value='';qr.value='';await loadInvites();notice.value='Invitación revocada.'}
  catch(e){error.value=(e as Error).message}
}
async function copyLink(){if(!shareUrl.value)return;try{await navigator.clipboard.writeText(shareUrl.value);notice.value='Enlace copiado.'}catch{notice.value='Selecciona el enlace para copiarlo.'}}
async function addMember(){
  try {await api('POST','/albums/'+id+'/members',{email:memberEmail.value,role:memberRole.value});memberEmail.value='';notice.value='Acceso concedido.'}
  catch(e){error.value=(e as Error).message}
}
async function onFile(event:Event) {
  const input=event.target as HTMLInputElement
  const file=input.files?.[0];if(!file)return
  error.value='';notice.value=''
  if(file.type!=='image/jpeg'||file.size>12582912){error.value='Por ahora solo se admiten JPEG de hasta 12 MB.';input.value='';return}
  busy.value=true;uploadPercent.value=0
  try {
    const guest=getGuest(id)
    const {upload_id,put_url}=await api<{upload_id:string;put_url:string}>('POST','/albums/'+id+'/uploads',{
      filename:file.name,mime:'image/jpeg',size:file.size,idempotency_key:crypto.randomUUID()},guest)
    await uploadToSignedUrl(put_url,file,p=>uploadPercent.value=p)
    await api('POST','/uploads/'+upload_id+'/complete',{},guest)
    notice.value='Foto recibida. Se está procesando y pasará por moderación si está activada.'
    await refresh()
  }catch(e){error.value=(e as Error).message}
  finally {busy.value=false;uploadPercent.value=null;input.value=''}
}
async function decide(photo:Photo,action:'approve'|'reject'){
  busy.value=true;error.value=''
  try{
    await api('POST','/media/'+photo.id+'/'+action,action==='reject'?{reason:'Rechazado por el moderador'}:{})
    notice.value=action==='approve'?'Foto aprobada y publicada.':'Foto rechazada.'
    await refresh()
  }catch(e){error.value=(e as Error).message}finally{busy.value=false}
}
const published=computed(()=>photos.value.filter(p=>p.status==='approved'))
const myUploads=computed(()=>photos.value.filter(p=>p.status!=='approved'))
</script>
<template>
  <div class="album-page page-pad"><div class="container">
    <RouterLink to="/app" v-if="session.user" class="back-link"><ArrowLeft :size="17"/> Mis álbumes</RouterLink>
    <div v-if="loading" class="loading-placeholder">Abriendo tu álbum...</div>
    <div v-else-if="!album" class="empty-state"><LockKeyhole :size="42"/><h3>Este álbum es privado</h3><p>{{error||'Necesitas una invitación válida o una cuenta autorizada para entrar.'}}</p><RouterLink to="/app" class="btn btn-dark">Ir a mis álbumes</RouterLink></div>
    <template v-else>
      <div class="album-hero"><div class="album-hero-art"><span>✿</span></div><div class="album-hero-copy"><span class="pill"><LockKeyhole :size="14"/> Álbum privado</span><h1>{{album.title}}</h1><p>{{album.description||'Cada foto cuenta una historia. Esta es la vuestra.'}}</p><div class="album-meta"><span><Images :size="17"/> {{published.length}} fotos</span><span><ShieldCheck :size="17"/> {{canModerate?'Puedes moderar':role==='guest'?'Invitado':'Acceso autorizado'}}</span><span>{{bytes(album.used_bytes)}} / {{bytes(album.quota_bytes)}}</span></div></div></div>
      <div v-if="notice" class="form-success">{{notice}}</div><div v-if="error" class="form-error" role="alert">{{error}}</div>
      <div class="album-toolbar"><div class="tabs"><button :class="{selected:tab==='gallery'}" @click="tab='gallery'"><Images :size="18"/> Galería</button><button v-if="canModerate" :class="{selected:tab==='moderation'}" @click="tab='moderation'"><ShieldCheck :size="18"/> Pendientes <span v-if="pending.length" class="tab-count">{{pending.length}}</span></button><button v-if="isOwner" :class="{selected:tab==='sharing'}" @click="tab='sharing';loadInvites()"><UsersRound :size="18"/> Compartir</button></div><label v-if="canUpload" class="btn btn-dark upload-label"><UploadCloud :size="18"/> {{busy?'Subiendo...':'Subir foto'}}<input type="file" accept="image/jpeg,.jpg,.jpeg" :disabled="busy" @change="onFile" hidden/></label></div>
      <div v-if="uploadPercent!==null" class="upload-progress"><div class="upload-progress-top"><span>Subiendo foto...</span><strong>{{uploadPercent}} %</strong></div><div class="progress-track"><div :style="{width:uploadPercent+'%'}"></div></div></div>
      <section v-if="tab==='gallery'"><div class="album-section-head"><h2>Vuestros recuerdos</h2><p>Una historia contada desde todos los puntos de vista.</p></div>
        <div v-if="published.length" class="photo-grid"><button v-for="photo in published" :key="photo.id" class="photo-card" @click="selected=photo"><img :src="photo.thumbnail_url||photo.preview_url||''" alt="Fotografía del álbum" loading="lazy"/><span class="photo-hover"><Eye :size="23"/></span></button></div>
        <div v-else class="empty-state"><div class="empty-art"><ImagePlus :size="54" :stroke-width="1.3"/></div><h3>Un álbum esperando historias</h3><p>La primera foto siempre tiene algo especial.</p><label v-if="canUpload" class="btn btn-dark upload-label"><Plus :size="18"/> Subir primera foto<input type="file" accept="image/jpeg" :disabled="busy" @change="onFile" hidden/></label></div>
        <div v-if="myUploads.length" class="my-uploads"><h3>Mis fotos en proceso</h3><div v-for="p in myUploads" :key="p.id" class="pending-row"><Clock3 :size="18"/><span>Foto del {{new Date(p.created_at).toLocaleDateString('es-ES')}}</span><span class="status-pill">{{p.status==='pending'?'Pendiente de aprobación':p.status==='failed'?'Error de procesamiento':'Procesando'}}</span></div></div>
      </section>
      <section v-else-if="tab==='moderation'"><div class="album-section-head"><h2>Fotos pendientes</h2><p>Las fotos de invitados se quedan privadas hasta que las apruebes.</p></div>
        <div v-if="!pending.length" class="empty-state"><ShieldCheck :size="48"/><h3>Todo al día</h3><p>No hay fotos esperando tu aprobación.</p></div>
        <div v-else class="moderation-grid"><article v-for="photo in pending" :key="photo.id" class="moderation-card"><img v-if="photo.thumbnail_url" :src="photo.thumbnail_url" alt="Foto pendiente de revisión"/><div class="moderation-card-body"><span><Clock3 :size="14"/> {{new Date(photo.created_at).toLocaleDateString('es-ES')}}</span><div class="moderation-actions"><button class="btn btn-danger" :disabled="busy" @click="decide(photo,'reject')"><X :size="16"/> Rechazar</button><button class="btn btn-dark" :disabled="busy" @click="decide(photo,'approve')"><Check :size="16"/> Aprobar</button></div></div></article></div>
      </section>
      <section v-else-if="tab==='sharing'" class="sharing-layout">
        <div class="share-panel surface"><div class="panel-icon"><QrCode :size="29"/></div><h2>Invita a vivirlo juntos</h2><p>Comparte un enlace o QR para que otros puedan aportar fotos sin registrarse.</p><button class="btn btn-dark" :disabled="busy" @click="createInvite"><Plus :size="17"/> Generar invitación</button>
          <div v-if="shareUrl" class="share-result"><img v-if="qr" :src="qr" alt="Código QR de invitación" class="qr-image"/><div class="share-input"><input class="field" readonly :value="shareUrl" aria-label="Enlace privado de invitación"/><button class="icon-button" title="Copiar enlace" @click="copyLink"><Copy :size="19"/></button></div><small>Guarda este enlace ahora. Por seguridad no podrá recuperarse después.</small></div>
        </div>
        <div class="share-side"><div class="surface side-panel"><h3><LinkIcon :size="18"/> Invitaciones activas</h3><p class="muted">Revoca accesos cuando lo necesites.</p><div v-if="!invites.length" class="muted">Todavía no has creado invitaciones.</div><div v-for="invite in invites" :key="invite.id" class="invite-row"><div><strong>Enlace para {{invite.scope==='upload'?'subir fotos':'ver'}}</strong><span>{{invite.revoked_at?'Revocado':'Válido hasta '+new Date(invite.expires_at).toLocaleDateString('es-ES')}}</span></div><button v-if="!invite.revoked_at" class="btn btn-danger btn-small" @click="revoke(invite.id)">Revocar</button></div></div>
          <form class="surface side-panel" @submit.prevent="addMember"><h3><UsersRound :size="18"/> Añadir miembro</h3><p class="muted">Para una persona que ya tenga cuenta en Albuvo.</p><input v-model="memberEmail" class="field" required type="email" placeholder="persona@correo.com"/><select v-model="memberRole" class="field"><option value="viewer">Puede ver</option><option value="contributor">Puede subir fotos</option><option value="moderator">Puede moderar</option></select><button class="btn btn-dark btn-small" type="submit">Conceder acceso <ArrowUpRight :size="16"/></button></form></div>
      </section>
      <button class="refresh-button" @click="refresh" title="Actualizar galería"><RefreshCcw :size="16"/> Actualizar</button>
    </template>
    <div v-if="selected" class="lightbox" role="dialog" aria-modal="true" aria-label="Visor de fotografía" @click.self="selected=null"><button class="lightbox-close" @click="selected=null" aria-label="Cerrar"><X :size="26"/></button><img :src="selected.preview_url||''" alt="Fotografía ampliada"/></div>
  </div></div>
</template>
