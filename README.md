# Albuvo

**Tus recuerdos, entre todos.** Plataforma privada de álbumes colaborativos desarrollada con Vue 3 + TypeScript (Vite) y Laravel 12, PostgreSQL, Redis y almacenamiento S3 privado.

## Estado

Primer flujo de colaboración validado localmente: registro y verificación de correo, álbum privado, invitación para colaborar sin cuenta, subida JPEG, miniatura, aprobación y galería. **No apto para producción.** Siguen pendientes endurecimiento de seguridad, recuperación de cuenta, textos legales aprobados, almacenamiento privado real y pruebas en navegadores/dispositivos.

## Estructura

- `apps/web`: SPA responsive Vue 3, Router y Pinia; selección de hasta 20 fotos JPEG con seguimiento de procesado.
- `services/api`: Laravel (autenticación, álbumes, invitaciones, subida, moderación, ajustes de álbum, miembros y limpieza de subidas caducadas).
- `infrastructure/docker`: imagen compartida de API, worker y scheduler.
- `docker-compose.yaml`: stack local (PostgreSQL, Redis, S3Mock, Mailpit, API, worker, scheduler y frontend).

## Desarrollo local

```bash
cp services/api/.env.example services/api/.env
# Ajustar credenciales: consultar docker-compose.yaml.
# S3Mock usa credenciales ficticias: AWS_ACCESS_KEY_ID=LOCAL_ONLY; AWS_SECRET_ACCESS_KEY=LOCAL_ONLY.
# PostgreSQL local: DB_PASSWORD=local_albuvo_dev_only.
# Para navegador: AWS_PUBLIC_ENDPOINT=http://localhost:9002.
cd services/api && composer install && php artisan key:generate && cd ../..
cd apps/web && npm ci && cd ../..
docker compose up -d --build
```

Web: `http://localhost:5174`; API: `http://localhost:8060/api/v1/health`; Mailpit: `http://localhost:8026`.

Las credenciales anteriores son **únicamente ejemplos para un entorno local aislado**. Nunca desplegar esta configuración en internet ni reutilizar sus contraseñas. Los archivos `.env` quedan excluidos de Git.

## Pruebas

```bash
cd services/api && php artisan test && cd ../..
cd apps/web && npm run build && cd ../..
python3 -m pip install --user requests Pillow  # si faltan dependencias
python3 scripts/smoke_local.py
```

La prueba de integración crea usuarios, álbum e imagen **sintéticos** en el stack local. Verifica el correo mediante Mailpit, realiza subida al S3Mock, consulta al worker, aprueba la imagen y revoca el acceso. No la ejecutes contra servicios externos.

El servicio `scheduler` ejecuta cada hora `php artisan albuvo:cleanup-uploads`, liberando la cuota reservada por subidas abandonadas. Todos los contenedores de desarrollo tienen `restart: unless-stopped`. Para detenerlos: `docker compose down` (sin `-v` para conservar datos).

## Seguridad

Los objetos se almacenan en un bucket S3 de desarrollo; el acceso a su URL firmada depende de la autorización de la API. **S3Mock NO valida las firmas ni garantiza la privacidad**: solo debe ejecutarse en localhost. En producción es obligatorio sustituirlo por R2 o S3 con bucket privado, políticas y firmas efectivas, TLS, configuración del dominio público, controles de capacidad y auditoría de seguridad. La generación de miniaturas corrige la orientación EXIF del JPEG antes de re-encodificarlo, y elimina metadatos EXIF en las imágenes derivadas.
