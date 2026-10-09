export type User = { id: string; name: string; email: string; email_verified_at: string | null; google_connected?: boolean }
export type Album = { id:string; title:string; description:string|null; slug:string; status:string; visibility:string; used_bytes:number; quota_bytes:number; reserved_bytes?:number; media_count?:number; created_at:string; allow_guest_upload?:boolean; require_upload_approval?:boolean }
export type Photo = { id:string; status:string; created_at:string; width:number|null; height:number|null; preview_url:string|null; thumbnail_url:string|null; reason:string|null }

let csrf: string | null = null

async function refreshCsrf(): Promise<string> {
  const res = await fetch('/api/v1/auth/csrf', { credentials: 'include', headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error('No se pudo iniciar la sesión segura.')
  csrf = (await res.json()).csrf_token as string
  return csrf
}

export async function api<T>(method: string, url: string, body?: unknown, guest?: string): Promise<T> {
  // Laravel rotates its session CSRF token on registration/login.
  // On 419, refresh and retry once; the rejected request was not executed.
  for (let attempt = 0; attempt < 2; attempt++) {
    if (method !== 'GET' && csrf === null) await refreshCsrf()
    const headers: Record<string, string> = { Accept: 'application/json' }
    if (body !== undefined) headers['Content-Type'] = 'application/json'
    if (csrf && method !== 'GET') headers['X-CSRF-TOKEN'] = csrf
    if (guest) headers['X-Guest-Token'] = guest
    const res = await fetch('/api/v1' + url, {
      method, credentials: 'include', headers,
      body: body === undefined ? undefined : JSON.stringify(body)
    })
    if (res.status === 419 && attempt === 0) {
      csrf = null
      continue
    }
    if (res.status === 204) return {} as T
    const json = await res.json().catch(() => ({}))
    if (!res.ok) {
      if (res.status === 419) csrf = null
      const validation = json.errors && Object.values(json.errors).flat()[0]
      throw new Error(String(validation || json.message || 'No se pudo completar la operación.'))
    }
    return json as T
  }
  throw new Error('No se pudo renovar la sesión segura.')
}
export function bytes(size: number): string {
  if (!size) return '0 MB'
  if (size > 1073741824) return (size/1073741824).toFixed(1)+' GB'
  return (size/1048576).toFixed(1)+' MB'
}
export function guestStorageKey(albumId:string): string { return 'albuvo_guest_'+albumId }
export function saveGuest(albumId:string,token:string) { sessionStorage.setItem(guestStorageKey(albumId),token) }
export function getGuest(albumId:string) { return sessionStorage.getItem(guestStorageKey(albumId)) || undefined }
export function uploadToSignedUrl(url:string, file:File, progress:(percent:number)=>void):Promise<void> {
  return new Promise((resolve,reject)=>{
    const xhr = new XMLHttpRequest()
    xhr.open('PUT',url)
    xhr.setRequestHeader('Content-Type','image/jpeg')
    xhr.upload.onprogress=(e)=>{ if(e.lengthComputable) progress(Math.round(e.loaded*100/e.total)) }
    xhr.onload=()=> xhr.status>=200 && xhr.status<300 ? resolve() : reject(new Error('El almacenamiento no aceptó la imagen.'))
    xhr.onerror=()=>reject(new Error('Error de conexión durante la subida.'))
    xhr.send(file)
  })
}
