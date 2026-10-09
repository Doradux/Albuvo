<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowLeft, Plus, QrCode, Copy, LockKeyhole, UploadCloud, Check, X, ShieldCheck, Images, Clock3, UsersRound, ImagePlus, Link as LinkIcon, RefreshCcw, Eye, ArrowUpRight, Settings2, Save, Trash2 } from '@lucide/vue'
import QRCode from 'qrcode'
import { api, bytes, getGuest, uploadToSignedUrl, type Album, type Photo } from '../lib/api'
import { useSession } from '../stores/session'
const route=useRoute();const session=useSession()
const id=String(route.params.id)
const album=ref<Album|null>(null); const role=ref('');const canUpload=ref(false);const canModerate=ref(false)
const photos=ref<Photo[]>([]);const pending=ref<Photo[]>([]);const tab=ref<'gallery'|'moderation'|'sharing'|'settings'>('gallery')
const loading=ref(true);const busy=ref(false);const error=ref('');const notice=ref('');const shareUrl=ref('')
const qr=ref('');const uploadPercent=ref<number|null>(null);const uploadStage=ref('');const selected=ref<Photo|null>(null)
const invites=ref<{id:string;scope:string;revoked_at:string|null;expires_at:string;used_count:number}[]>([])
const memberEmail=ref('');const memberRole=ref('viewer')
const members=ref<{id:string;name:string;email:string;role:string}[]>([])
const editTitle=ref('');const editDescription=ref('');const editAllowGuests=ref(true);const editModeration=ref(true)
const isOwner=computed(()=>['owner','admin'].includes(role.value))
async function refresh() {
  const guest=getGuest(id)
  const detail=await api<{album:Album;role:string;can_upload:boolean;can_moderate:boolean}>('GET','/albums/'+id,undefined,guest)
  album.value=detail.album;role.value=detail.role;canUpload.value=detail.can_upload;canModerate.value=detail.can_moderate
  photos.value=(await api<{media:Photo[]}>('GET','/albums/'+id+'/media',undefined,guest)).media
  if(canModerate.value) pending.value=(await api<{media:Photo[]}>('GET','/albums/'+id+'/moderation',undefined,guest)).media
}
async function load() {
  try {
    await session.hydrate();await refresh()
    if(album.value) {
      editTitle.value=album.value.title
      editDescription.value=album.value.description||''
      editAllowGuests.value=Boolean(album.value.allow_guest_upload)
      editModeration.value=Boolean(album.value.require_upload_approval)
    }
    if(route.query.new==='1') {tab.value='sharing';await Promise.all([loadInvites(),loadMembers()])}
  }
  catch(e){error.value=(e as Error).message}finally{loading.value=false}
}
let refreshTimer:ReturnType<typeof setInterval>|undefined
onMounted(()=>{
  void load()
  // Moderator's pending queue updates while the moderation tab is open.
  refreshTimer=setInterval(()=>{
    if(tab.value==='moderation' && !busy.value && !document.hidden) {
      void refresh().catch(()=>{ /* Retain the last successfully loaded gallery. */ })
    }
  },12000)
})
onUnmounted(()=>{if(refreshTimer)clearInterval(refreshTimer)})
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
async function loadMembers(){
  if(!isOwner.value)return
  members.value=(await api<{members:typeof members.value}>('GET','/albums/'+id+'/members')).members
}
async function removeMember(memberId:string){
  if(!confirm('¿Quitar a esta persona del álbum? Perderá el acceso.'))return
  try {
    await api('DELETE','/albums/'+id+'/members/'+memberId)
    await loadMembers()
    notice.value='Acceso retirado.'
  }catch(e){error.value=(e as Error).message}
}
async function saveSettings(){
  busy.value=true;error.value=''
  try {
    const result=await api<{album:Album}>('PATCH','/albums/'+id,{
      title:editTitle.value.trim(),description:editDescription.value.trim(),
      allow_guest_upload:editAllowGuests.value,require_upload_approval:editModeration.value
    })
    album.value=result.album
    notice.value='Ajustes guardados.'
  }catch(e){error.value=(e as Error).message}finally{busy.value=false}
}
async function addMember(){
  try {await api('POST','/albums/'+id+'/members',{email:memberEmail.value,role:memberRole.value});memberEmail.value='';notice.value='Acceso concedido.';await loadMembers()}
  catch(e){error.value=(e as Error).message}
}
async function waitForPhoto(uploadId:string,guest?:string):Promise<string> {
  for(let attempt=0;attempt<40;attempt++) {
    await new Promise(resolve=>setTimeout(resolve,750))
    const state=await api<{status:string;media_status:string}>('GET','/uploads/'+uploadId,undefined,guest)
    if(state.media_status==='failed') throw new Error('No se pudo procesar el JPEG. Intenta con otra imagen.')
    if(state.media_status==='pending'||state.media_status==='approved') return state.media_status
  }
  return 'processing'
}
async function onFile(event:Event) {
  const input=event.target as HTMLInputElement
  const files=Array.from(input.files||[])
  if(!files.length||busy.value)return
  error.value='';notice.value=''
  if(files.length>20){error.value='Selecciona hasta 20 fotos por tanda.';input.value='';return}
  if(files.some(f=>f.type!=='image/jpeg'||f.size<100||f.size>12582912)) {
    error.value='Por ahora solo se admiten JPEG de entre 100 bytes y 12 MB.'
    input.value='';return
  }
  busy.value=true;uploadPercent.value=0
  const guest=getGuest(id)
  let received=0
  const problems:string[]=[]
  let background=0
  try {
    for(const [index,file] of files.entries()) {
      uploadPercent.value=0
      uploadStage.value='Foto '+(index+1)+' de '+files.length+': subiendo...'
      try {
        const {upload_id,put_url}=await api<{upload_id:string;put_url:string}>('POST','/albums/'+id+'/uploads',{
          filename:file.name,mime:'image/jpeg',size:file.size,idempotency_key:crypto.randomUUID()
        },guest)
        await uploadToSignedUrl(put_url,file,p=>uploadPercent.value=p)
        uploadStage.value='Foto '+(index+1)+' de '+files.length+': procesando...'
        await api('POST','/uploads/'+upload_id+'/complete',{},guest)
        const status=await waitForPhoto(upload_id,guest)
        if(status==='processing') background++
        received++
      }catch(e){problems.push(file.name+': '+(e as Error).message)}
    }
    await refresh()
    if(received) notice.value=received+' foto'+(received===1?' recibida':'s recibidas')+
      '. '+(background?'Algunas siguen procesándose. ':'')+
      (album.value?.require_upload_approval?'Quedarán visibles tras la aprobación.':'Ya están en la galería.')
    if(problems.length) error.value=problems.join(' | ')
  }finally {
    busy.value=false;uploadPercent.value=null;uploadStage.value='';input.value=''
  }
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
      <div class="album-toolbar"><div class="tabs"><button :class="{selected:tab==='gallery'}" @click="tab='gallery'"><Images :size="18"/> Galería</button><button v-if="canModerate" :class="{selected:tab==='moderation'}" @click="tab='moderation'"><ShieldCheck :size="18"/> Pendientes <span v-if="pending.length" class="tab-count">{{pending.length}}</span></button><button v-if="isOwner" :class="{selected:tab==='sharing'}" @click="tab='sharing';void loadInvites();void loadMembers()"><UsersRound :size="18"/> Compartir</button><button v-if="role==='owner'" :class="{selected:tab==='settings'}" @click="tab='settings'"><Settings2 :size="18"/> Ajustes</button></div><label v-if="canUpload" class="btn btn-dark upload-label"><UploadCloud :size="18"/> {{busy?'Subiendo...':'Subir fotos'}}<input type="file" accept="image/jpeg,.jpg,.jpeg" multiple :disabled="busy" @change="onFile" hidden/></label></div>
      <div v-if="uploadPercent!==null" class="upload-progress"><div class="upload-progress-top"><span>{{uploadStage||'Subiendo fotos...'}}</span><strong>{{uploadPercent}} %</strong></div><div class="progress-track"><div :style="{width:uploadPercent+'%'}"></div></div></div>
      <section v-if="tab==='gallery'"><div class="album-section-head"><h2>Vuestros recuerdos</h2><p>Una historia contada desde todos los puntos de vista.</p></div>
        <div v-if="published.length" class="photo-grid"><button v-for="photo in published" :key="photo.id" class="photo-card" @click="selected=photo"><img :src="photo.thumbnail_url||photo.preview_url||''" alt="Fotografía del álbum" loading="lazy"/><span class="photo-hover"><Eye :size="23"/></span></button></div>
        <div v-else class="empty-state"><div class="empty-art"><ImagePlus :size="54" :stroke-width="1.3"/></div><h3>Un álbum esperando historias</h3><p>La primera foto siempre tiene algo especial.</p><label v-if="canUpload" class="btn btn-dark upload-label"><Plus :size="18"/> Subir primeras fotos<input type="file" accept="image/jpeg" multiple :disabled="busy" @change="onFile" hidden/></label></div>
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
          <div class="surface side-panel"><h3><UsersRound :size="18"/> Miembros registrados</h3><p class="muted">Solo los miembros autorizados pueden ver este álbum.</p><div v-if="!members.length" class="muted">Cargando miembros...</div><div v-for="member in members" :key="member.id" class="invite-row"><div><strong>{{member.name}}</strong><span>{{member.email}} · {{member.role==='owner'?'Propietario':member.role==='admin'?'Administrador':member.role==='viewer'?'Solo lectura':member.role==='contributor'?'Colaborador':'Moderador'}}</span></div><button v-if="member.role!=='owner' && (role==='owner'||member.role!=='admin')" type="button" class="btn btn-danger btn-small" @click="removeMember(member.id)"><Trash2 :size="15"/> Quitar</button></div></div>
          <form class="surface side-panel" @submit.prevent="addMember"><h3><UsersRound :size="18"/> Añadir miembro</h3><p class="muted">Para una persona que ya tenga cuenta en Albuvo.</p><input v-model="memberEmail" class="field" required type="email" placeholder="persona@correo.com"/><select v-model="memberRole" class="field"><option value="viewer">Puede ver</option><option value="contributor">Puede subir fotos</option><option value="moderator">Puede moderar</option></select><button class="btn btn-dark btn-small" type="submit">Conceder acceso <ArrowUpRight :size="16"/></button></form></div>
      </section>
      <section v-else-if="tab==='settings'" class="sharing-layout"><form class="surface share-panel" @submit.prevent="saveSettings"><div class="panel-icon"><Settings2 :size="27"/></div><h2>Ajustes del álbum</h2><p>Personaliza tu espacio privado. Solo el propietario puede cambiar estas opciones.</p><div class="form-row"><label for="edit-title" class="form-label">Nombre del álbum</label><input id="edit-title" v-model="editTitle" class="field" required maxlength="100"/></div><div class="form-row"><label for="edit-description" class="form-label">Descripción</label><textarea id="edit-description" v-model="editDescription" class="field" maxlength="500" rows="3"/></div><label class="toggle-row"><div><strong>Permitir subidas de invitados</strong><p>Solo con invitación de colaboración activa.</p></div><input v-model="editAllowGuests" type="checkbox" role="switch"/></label><label class="toggle-row"><div><strong>Aprobar fotos antes de publicarlas</strong><p>Las nuevas imágenes deberán pasar por tu revisión.</p></div><input v-model="editModeration" type="checkbox" role="switch"/></label><button class="btn btn-dark" type="submit" :disabled="busy"><Save :size="17"/> {{busy?'Guardando...':'Guardar ajustes'}}</button></form><div class="surface side-panel"><h3><LockKeyhole :size="18"/> Privado desde el principio</h3><p class="muted">Tu álbum no se publica en búsquedas. Si ya has entregado invitaciones, puedes revocarlas desde Compartir.</p><p class="muted">El nombre y la descripción pueden cambiarse sin invalidar los enlaces.</p></div></section>
      <button class="refresh-button" @click="refresh" title="Actualizar galería"><RefreshCcw :size="16"/> Actualizar</button>
    </template>
    <div v-if="selected" class="lightbox" role="dialog" aria-modal="true" aria-label="Visor de fotografía" @click.self="selected=null"><button class="lightbox-close" @click="selected=null" aria-label="Cerrar"><X :size="26"/></button><img :src="selected.preview_url||''" alt="Fotografía ampliada"/></div>
  </div></div>
</template>
