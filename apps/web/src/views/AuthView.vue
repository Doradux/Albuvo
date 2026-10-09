<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowLeft, ArrowUpRight, Eye, EyeOff, LockKeyhole, Heart } from '@lucide/vue'
import { api, type User } from '../lib/api'
import { useSession } from '../stores/session'
const props=defineProps<{mode:'login'|'signup'}>()
const signup=computed(()=>props.mode==='signup')
const router=useRouter(); const route=useRoute()
const session=useSession()
const name=ref(''); const email=ref(''); const password=ref(''); const confirmation=ref('')
const accepted=ref(false); const visible=ref(false); const busy=ref(false); const error=ref('')
const googleErrors:Record<string,string>={
  not_configured:'El acceso con Google está pendiente de configurar por el administrador.',
  accept_terms:'Acepta las condiciones antes de registrarte con Google.',
  login_required:'Primero inicia sesión en Albuvo para vincular Google.',
  session_expired:'La sesión de Google ha caducado. Inténtalo otra vez.',
  cancelled:'Has cancelado el inicio de sesión con Google.',
  oauth_failed:'Google no pudo verificar el acceso. Vuelve a intentarlo.',
  unverified_email:'Google no ha confirmado tu correo electrónico.',
  signup_required:'Aún no tienes Google vinculado. Regístrate con Google para crear una cuenta.',
  existing_account:'Ya tienes cuenta con ese correo. Entra con tu contraseña y después vincula Google desde Mis álbumes.',
  signup_failed:'No se pudo completar el registro con Google.',
  different_email:'El correo de Google debe ser el mismo que el de tu cuenta Albuvo.',
  identity_in_use:'Esta cuenta de Google ya está vinculada a otra cuenta de Albuvo.'
}
onMounted(()=>{
  const key=String(route.query.google_error||'')
  if(key) error.value=googleErrors[key]||'No se pudo completar el acceso con Google.'
})
async function googleLogin(){
  busy.value=true;error.value=''
  try {
    if(signup.value) await api('POST','/auth/google/prepare',{accept_terms:accepted.value})
    window.location.assign('/api/v1/auth/google/redirect?intent='+(signup.value?'signup':'login'))
  }catch(e){error.value=(e as Error).message;busy.value=false}
}
async function submit() {
  busy.value=true; error.value=''
  try {
    const result=await api<{user:User}>('POST',signup.value?'/auth/register':'/auth/login',
      signup.value?{name:name.value,email:email.value,password:password.value,password_confirmation:confirmation.value,accept_terms:accepted.value}
                  :{email:email.value,password:password.value})
    session.user=result.user
    await router.push('/app')
  } catch(e) {error.value=e instanceof Error?e.message:'No se pudo completar el acceso.'}
  finally {busy.value=false}
}
</script>
<template>
  <div class="auth-page"><div class="container auth-layout">
    <section class="auth-panel"><RouterLink to="/" class="back-link"><ArrowLeft :size="17"/> Volver al inicio</RouterLink>
      <span class="pill auth-pill"><Heart :size="14"/> Qué alegría verte por aquí</span>
      <h1>{{signup?'Empieza a guardar lo que importa.':'Qué bien tenerte de vuelta.'}}</h1>
      <p class="muted">{{signup?'Un lugar especial para cada recuerdo que merezca quedarse.':'Tus recuerdos favoritos te están esperando.'}}</p>
      <div v-if="error" class="form-error" role="alert">{{error}}</div>
      <label v-if="signup" class="form-check google-terms"><input v-model="accepted" type="checkbox" required/><span>Acepto las condiciones de uso y las normas de la comunidad. <small>(Versión de desarrollo pendiente de aprobación legal.)</small></span></label>
      <button type="button" class="google-auth-button" :disabled="busy" @click="googleLogin">
        <svg class="google-mark" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.25 5.48-4.77 7.18l7.73 6C44.41 38.03 46.98 31.88 46.98 24.55z"/><path fill="#FBBC05" d="M10.53 28.59A14.41 14.41 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.95-6.2A23.9 23.9 0 0 0 0 24c0 3.87.93 7.52 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.9-5.8l-7.73-6c-2.15 1.45-4.92 2.3-8.17 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.97 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
        Continuar con Google
      </button>
      <div class="auth-divider"><span>o con correo electrónico</span></div>
      <form class="auth-form" @submit.prevent="submit">
        <div v-if="signup" class="form-row"><label for="name" class="form-label">Tu nombre</label><input id="name" v-model="name" class="field" autocomplete="name" placeholder="Como quieres que te llamemos" required minlength="2" maxlength="80"/></div>
        <div class="form-row"><label for="email" class="form-label">Correo electrónico</label><input id="email" v-model="email" class="field" type="email" autocomplete="email" placeholder="tu@email.com" required/></div>
        <div class="form-row"><label for="password" class="form-label">Contraseña</label><div class="password-field"><input id="password" v-model="password" class="field" :type="visible?'text':'password'" :autocomplete="signup?'new-password':'current-password'" :minlength="signup?10:1" required placeholder="Tu contraseña"/><button type="button" @click="visible=!visible" class="show-password" :aria-label="visible?'Ocultar':'Mostrar'"><EyeOff v-if="visible" :size="19"/><Eye v-else :size="19"/></button></div><p v-if="signup" class="form-hint">Mínimo 10 caracteres, con letras y números.</p></div>
        <div v-if="signup" class="form-row"><label for="confirm" class="form-label">Repetir contraseña</label><input id="confirm" v-model="confirmation" class="field" type="password" autocomplete="new-password" required :minlength="10" placeholder="Confirma tu contraseña"/></div>
        <button class="btn btn-dark auth-submit" type="submit" :disabled="busy">{{busy?'Un momento...':signup?'Crear mi cuenta':'Entrar a mis álbumes'}} <ArrowUpRight v-if="!busy" :size="18"/></button>
        <p class="auth-switch">{{signup?'¿Ya tienes una cuenta?':'¿Todavía no tienes cuenta?'}} <RouterLink :to="signup?'/login':'/signup'">{{signup?'Inicia sesión':'Regístrate gratis'}}</RouterLink></p>
      </form>
    </section>
    <aside class="auth-visual"><div class="auth-visual-center"><div class="auth-art-icon">✿</div><span class="eyebrow">Tus historias, en un solo lugar</span><p class="serif">Los mejores recuerdos nunca son solo nuestros.</p><div class="auth-caption"><LockKeyhole :size="17"/> Privado, compartido y siempre tuyo.</div></div></aside>
  </div></div>
</template>
