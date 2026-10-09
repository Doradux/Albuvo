import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, type User } from '../lib/api'

export const useSession = defineStore('session',()=>{
  const user=ref<User|null>(null)
  const ready=ref(false)
  async function hydrate() {
    try { user.value=(await api<{user:User|null}>('GET','/me')).user }
    catch { user.value=null }
    finally { ready.value=true }
  }
  async function logout() { await api('POST','/auth/logout'); user.value=null }
  return { user,ready,hydrate,logout }
})
