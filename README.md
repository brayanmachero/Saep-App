# SAEP Platform

Plataforma interna de SAEP: Prevención de Riesgos (SST), RRHH y contratación,
Bodega e inventario, Comercial (cotizador y tarifas), Kanban y Protección de Datos
(Ley 21.719).

- **Stack:** Laravel 12 · PHP 8.2+ · MySQL 8 · Blade + Tailwind + Vite
- **Producción:** `https://saep.bmachero.com`, publicada con Ploi desde la rama **`ploi-main`**
- **Operación, despliegue, variables y tareas programadas:** [docs/DEVOPS.md](docs/DEVOPS.md)

## Inicio rápido

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
composer dev
```

## Flujo de trabajo

1. Crear la rama desde `ploi-main`.
2. Ejecutar `php artisan test` antes de integrar.
3. Hacer merge en `ploi-main` y push: Ploi despliega automáticamente (Quick deploy).
4. Registrar el trabajo en Planner según [AGENTS.md](AGENTS.md).
