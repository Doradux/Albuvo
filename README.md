# Albuvo

**Tus recuerdos, entre todos.** Plataforma privada de álbumes colaborativos desarrollada con Vue 3 + TypeScript (Vite) y Laravel 12, PostgreSQL, Redis y almacenamiento S3 privado.

## Estado

Primer sprint en desarrollo. **No apto para producción.** El proyecto todavía requiere completar la interfaz, el flujo de carga y moderación, las pruebas de seguridad y el cumplimiento normativo. Los textos legales y comerciales no han sido aprobados.

## Estructura

- `apps/web`: SPA responsive Vue 3, Router y Pinia.
- `services/api`: Laravel (autenticación, álbumes, invitaciones, subida y moderación en desarrollo).
- `infrastructure/docker`: imagen de API y worker.
- `docker-compose.yaml`: stack local (PostgreSQL, Redis, S3Mock local, Mailpit, API, worker, frontend).

## Desarrollo local

```bash
cp services/api/.env.example services/api/.env
# Ajustar credenciales de desarrollo: consultar docker-compose.yaml.
# S3Mock acepta credenciales ficticias locales: AWS_ACCESS_KEY_ID=LOCAL_ONLY; AWS_SECRET_ACCESS_KEY=LOCAL_ONLY
# Para Postgres: DB_PASSWORD=local_albuvo_dev_only
# Para navegador: AWS_PUBLIC_ENDPOINT=http://localhost:9002
cd services/api && composer install && php artisan key:generate && cd ../..
cd apps/web && npm ci && cd ../..
docker compose up --build
```

Web: `http://localhost:5174`; API: `http://localhost:8060/api/v1/health`; Mailpit: `http://localhost:8026`.

Las credenciales anteriores son **únicamente ejemplos para un entorno local aislado**. Nunca desplegar esta configuración a internet, ni reutilizar sus contraseñas. Los archivos `.env` quedan excluidos de Git.

## Seguridad

Las imágenes y miniaturas se almacenan en un bucket S3 de desarrollo, y el acceso a los enlaces se comprueba en Laravel. El emulador S3Mock NO valida las firmas ni aplica políticas de privacidad de producción: solo debe usarse en localhost; producción requiere R2 privado real. La API sigue en implementación y **necesita pruebas de autorización y procesamiento antes de tratar datos reales**. La primera versión solo acepta JPEG; la generación de miniaturas elimina EXIF en las derivadas.
