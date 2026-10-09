<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ArrowLeft, ArrowUpRight, Eye, EyeOff, LockKeyhole, Heart } from '@lucide/vue'
import { api, type User } from '../lib/api'
import { useSession } from '../stores/session'
const props=defineProps<{mode:'login'|'signup'}>()
const signup=computed(()=>props.mode==='signup')
const router=useRouter()
const session=useSession()
const name=ref(''); const email=ref(''); const password=ref(''); const confirmation=ref('')
const accepted=ref(false); const visible=ref(false); const busy=ref(false); const error=ref('')
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
      <form class="auth-form" @submit.prevent="submit">
        <div v-if="error" class="form-error" role="alert">{{error}}</div>
        <div v-if="signup" class="form-row"><label for="name" class="form-label">Tu nombre</label><input id="name" v-model="name" class="field" autocomplete="name" placeholder="Como quieres que te llamemos" required minlength="2" maxlength="80"/></div>
        <div class="form-row"><label for="email" class="form-label">Correo electrónico</label><input id="email" v-model="email" class="field" type="email" autocomplete="email" placeholder="tu@email.com" required/></div>
        <div class="form-row"><label for="password" class="form-label">Contraseña</label><div class="password-field"><input id="password" v-model="password" class="field" :type="visible?'text':'password'" :autocomplete="signup?'new-password':'current-password'" :minlength="signup?10:1" required placeholder="Tu contraseña"/><button type="button" @click="visible=!visible" class="show-password" :aria-label="visible?'Ocultar':'Mostrar'"><EyeOff v-if="visible" :size="19"/><Eye v-else :size="19"/></button></div><p v-if="signup" class="form-hint">Mínimo 10 caracteres, con letras y números.</p></div>
        <div v-if="signup" class="form-row"><label for="confirm" class="form-label">Repetir contraseña</label><input id="confirm" v-model="confirmation" class="field" type="password" autocomplete="new-password" required :minlength="10" placeholder="Confirma tu contraseña"/></div>
        <label v-if="signup" class="form-check"><input v-model="accepted" type="checkbox" required/><span>Acepto las condiciones de uso y las normas de la comunidad. <small>(Versión de desarrollo pendiente de aprobación legal.)</small></span></label>
        <button class="btn btn-dark auth-submit" type="submit" :disabled="busy">{{busy?'Un momento...':signup?'Crear mi cuenta':'Entrar a mis álbumes'}} <ArrowUpRight v-if="!busy" :size="18"/></button>
        <p class="auth-switch">{{signup?'¿Ya tienes una cuenta?':'¿Todavía no tienes cuenta?'}} <RouterLink :to="signup?'/login':'/signup'">{{signup?'Inicia sesión':'Regístrate gratis'}}</RouterLink></p>
      </form>
    </section>
    <aside class="auth-visual"><div class="auth-visual-center"><div class="auth-art-icon">✿</div><span class="eyebrow">Tus historias, en un solo lugar</span><p class="serif">Los mejores recuerdos nunca son solo nuestros.</p><div class="auth-caption"><LockKeyhole :size="17"/> Privado, compartido y siempre tuyo.</div></div></aside>
  </div></div>
</template>
