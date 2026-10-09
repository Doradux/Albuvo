#!/usr/bin/env python3
"""Prueba local de Albuvo: registro, correo, invitación, JPEG, revisión y privacidad.

Solo para el stack Docker de desarrollo; crea cuentas y álbumes sintéticos.
Requiere requests y Pillow. Ejecutar: python3 scripts/smoke_local.py
"""
import html
import io
import re
import sys
import time
import uuid
from urllib.parse import urlparse

import requests
from PIL import Image

WEB = "http://localhost:5174"
MAIL = "http://localhost:8026"
TIMEOUT = 12


def step(label):
    print(f"[OK] {label}", flush=True)


def call(session, method, path, data=None, guest=None, expected=(200,)):
    url = WEB + "/api/v1" + path
    headers = {"Accept": "application/json"}
    if method != "GET":
        headers["X-CSRF-TOKEN"] = session.csrf
    if guest:
        headers["X-Guest-Token"] = guest
    response = session.request(method, url, json=data, headers=headers, timeout=TIMEOUT)
    if response.status_code == 419 and method != "GET":
        token_response = session.get(WEB + "/api/v1/auth/csrf", timeout=TIMEOUT)
        token_response.raise_for_status()
        session.csrf = token_response.json()["csrf_token"]
        headers["X-CSRF-TOKEN"] = session.csrf
        response = session.request(method, url, json=data, headers=headers, timeout=TIMEOUT)
    if response.status_code not in expected:
        raise AssertionError(f"{method} {path}: HTTP {response.status_code}: {response.text[:500]}")
    return response.json() if response.content and response.status_code != 204 else {}


def new_session():
    session = requests.Session()
    response = session.get(WEB + "/api/v1/auth/csrf", timeout=TIMEOUT)
    response.raise_for_status()
    session.csrf = response.json()["csrf_token"]
    return session


def main():
    marker = uuid.uuid4().hex[:12]
    owner = new_session()
    email = f"albuvo-smoke-{marker}@example.test"
    result = call(owner, "POST", "/auth/register", {
        "name": "Prueba Albuvo", "email": email,
        "password": "LocalTest2026Pass01", "password_confirmation": "LocalTest2026Pass01",
        "accept_terms": True,
    }, expected=(201,))
    assert result["verification_required"]
    step("Registro con sesión y CSRF")

    mail_id = None
    for _ in range(24):
        mailbox = requests.get(MAIL + "/api/v1/messages", timeout=TIMEOUT).json()
        for item in mailbox.get("messages", []):
            if any(r.get("Address", "").lower() == email for r in item.get("To", [])):
                mail_id = item["ID"]
                break
        if mail_id:
            break
        time.sleep(0.5)
    assert mail_id, "Mailpit no ha recibido el correo de verificación"
    message = requests.get(MAIL + "/api/v1/message/" + mail_id, timeout=TIMEOUT).json()
    contents = html.unescape(message.get("HTML", "") + "\n" + message.get("Text", ""))
    match = re.search(r"https?://[^\s\"<>]+/api/v1/verify-email/[^\s\"<>]+", contents)
    assert match, "No encuentro el enlace de verificación en Mailpit"
    verification_url = match.group(0).rstrip(".)")
    assert urlparse(verification_url).hostname == "localhost"
    verification = owner.get(verification_url, allow_redirects=True, timeout=TIMEOUT)
    assert verification.ok, f"Verificación HTTP {verification.status_code}"
    assert call(owner, "GET", "/me")["user"]["email_verified_at"], "Correo no verificado"
    step("Verificación de correo a través de Mailpit")

    album = call(owner, "POST", "/albums", {
        "title": "Smoke local " + marker,
        "description": "Álbum sintético de integración",
        "allow_guest_upload": True, "require_upload_approval": True,
    }, expected=(201,))["album"]
    album_id = album["id"]
    invite = call(owner, "POST", f"/albums/{album_id}/invites", {
        "scope": "upload", "hours": 1, "max_uses": 1,
    }, expected=(201,))
    assert invite["token"] and invite["share_url"].endswith(invite["token"])
    step("Crear álbum privado e invitación de un solo uso")

    outsider = new_session()
    call(outsider, "GET", f"/albums/{album_id}", expected=(404,))
    call(outsider, "GET", f"/albums/{album_id}/media", expected=(404,))
    call(outsider, "GET", f"/albums/{album_id}/moderation", expected=(404,))
    step("Acceso denegado a un usuario ajeno")

    guest = new_session()
    call(guest, "POST", "/album-invites/preview", {"token": invite["token"]})
    accepted = call(guest, "POST", "/album-invites/resolve", {
        "token": invite["token"], "display_name": "Invitado sintético",
        "accept_rules": True,
    })
    guest_token = accepted["guest_token"]
    assert call(guest, "GET", f"/albums/{album_id}", guest=guest_token)["role"] == "guest"
    call(guest, "POST", "/album-invites/resolve", {
        "token": invite["token"], "display_name": "Invitado adicional", "accept_rules": True,
    }, expected=(404,))
    step("Acceso como invitado sin cuenta y límite de invitación")

    buffer = io.BytesIO()
    image = Image.new("RGB", (720, 480), color=(60, 155, 113))
    orientation = image.getexif()
    orientation[274] = 6  # Rotate 90° clockwise without touching the pixel buffer.
    image.save(buffer, format="JPEG", quality=83, exif=orientation)
    binary = buffer.getvalue()
    prepared = call(guest, "POST", f"/albums/{album_id}/uploads", {
        "filename": "foto-test.jpg", "mime": "image/jpeg", "size": len(binary),
        "idempotency_key": str(uuid.uuid4()),
    }, guest=guest_token, expected=(201,))
    put_url = prepared["put_url"]
    assert urlparse(put_url).hostname == "localhost", "Endpoint de almacenamiento no local"
    uploaded = requests.put(put_url, data=binary, headers={"Content-Type": "image/jpeg"}, timeout=TIMEOUT)
    assert uploaded.status_code in (200, 201, 204), f"S3 PUT HTTP {uploaded.status_code}: {uploaded.text[:250]}"
    status = call(guest, "POST", f"/uploads/{prepared['upload_id']}/complete", {},
                  guest=guest_token, expected=(202,))["status"]
    assert status == "processing"
    step("Subida JPEG real por URL firmada al S3Mock local")

    media_status = None
    for _ in range(35):
        state = call(guest, "GET", f"/uploads/{prepared['upload_id']}", guest=guest_token)
        media_status = state["media_status"]
        if media_status in ("pending", "failed", "approved"):
            break
        time.sleep(0.75)
    assert media_status == "pending", f"Worker no procesó imagen: {media_status}"
    moderated = call(owner, "GET", f"/albums/{album_id}/moderation")["media"]
    assert len(moderated) == 1 and moderated[0]["thumbnail_url"]
    assert moderated[0]["width"] == 480 and moderated[0]["height"] == 720, "EXIF orientation ignored"
    preview = requests.get(moderated[0]["thumbnail_url"], timeout=TIMEOUT)
    assert preview.status_code == 200 and preview.content[:3] == b"\xff\xd8\xff"
    assert Image.open(io.BytesIO(preview.content)).size == (280, 420), "JPEG thumbnail not rotated"
    assert not Image.open(io.BytesIO(preview.content)).getexif(), "EXIF still present in thumbnail"
    step("Worker genera JPEG/miniatura, queda pendiente de moderación")

    gallery_before = call(outsider, "GET", f"/albums/{album_id}/media", expected=(404,))
    guest_before = call(guest, "GET", f"/albums/{album_id}/media", guest=guest_token)["media"]
    assert len(guest_before) == 1 and guest_before[0]["status"] == "pending"
    call(owner, "POST", f"/media/{moderated[0]['id']}/approve", {}, expected=(200,))
    gallery_after = call(guest, "GET", f"/albums/{album_id}/media", guest=guest_token)["media"]
    assert len(gallery_after) == 1 and gallery_after[0]["status"] == "approved"
    step("Moderación: imagen aprobada aparece en galería")

    call(owner, "DELETE", f"/albums/{album_id}/invites/{invite['invite']['id']}", expected=(204,))
    call(guest, "GET", f"/albums/{album_id}", guest=guest_token, expected=(404,))
    step("Revocar invitación invalida acceso previo")
    print("SMOKE COMPLETADO: flujo integral de Albuvo OK", flush=True)


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        import traceback
        traceback.print_exc()
        print(f"[ERROR] {exc}", file=sys.stderr, flush=True)
        sys.exit(1)
