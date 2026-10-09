# Albuvo

**Tus recuerdos, entre todos.** Plataforma privada de álbumes colaborativos desarrollada con Vue 3 + TypeScript (Vite) y Laravel 12, PostgreSQL, Redis y almacenamiento S3 privado.

## Estado

Primer sprint en desarrollo. **No apto para producción.** El proyecto todavía requiere completar la interfaz, el flujo de carga y moderación, las pruebas de seguridad y el cumplimiento normativo. Los textos legales y comerciales no han sido aprobados.

## Estructura

- `apps/web`: SPA responsive Vue 3, Router y Pinia.
- `services/api`: Laravel (autenticación, álbumes, invitaciones, subida y moderación en desarrollo).
- `infrastructure/docker`: imagen de API y worker.
- `docker-compose.yaml`: stack local (PostgreSQL, Redis, MinIO privado, Mailpit, API, worker, frontend).

## Desarrollo local

```bash
cp services/api/.env.example services/api/.env
# Ajustar credenciales de desarrollo: consultar docker-compose.yaml.
# Para MinIO: AWS_ACCESS_KEY_ID=albuvo_local; AWS_SECRET_ACCESS_KEY=local_minio_password_123456
# Para Postgres: DB_PASSWORD=local_albuvo_dev_only
# Para navegador: AWS_PUBLIC_ENDPOINT=http://localhost:9002
cd services/api && composer install && php artisan key:generate && cd ../..
cd apps/web && npm ci && cd ../..
docker compose up --build
```

Web: `http://localhost:5174`; API: `http://localhost:8060/api/v1/health`; Mailpit: `http://localhost:8026`; consola MinIO: `http://localhost:9003` (solo local).

Las credenciales anteriores son **únicamente ejemplos para un entorno local aislado**. Nunca desplegar esta configuración a internet, ni reutilizar sus contraseñas. Los archivos `.env` quedan excluidos de Git.

## Seguridad

Las imágenes y miniaturas se almacenan en un bucket privado, y el acceso se comprueba en Laravel. La API sigue en implementación y **necesita pruebas de autorización y procesamiento antes de tratar datos reales**. La primera versión solo acepta JPEG; la generación de miniaturas elimina EXIF en las derivadas.
