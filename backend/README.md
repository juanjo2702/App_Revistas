# App Revistas Backend

Backend Laravel 12 para el catalogo de revistas de UNITEPC. Este servicio expone una API publica de solo lectura para revistas OJS y mantiene un catalogo local sincronizado para la app movil.

## Requisitos

- PHP 8.2+
- Composer 2+
- MySQL 8+ o MariaDB compatible

## Variables principales

El archivo `.env.example` ya viene preparado para el primer despliegue en cPanel con:

- `APP_URL=https://api.apprevistas.xpertiaplus.com`
- `DB_CONNECTION=mysql`
- `OJS_SOURCE_1_ENABLED=false`
- `OJS_SOURCE_2_DRIVER=public_ojs_34`
- `G-News` como unica fuente activa

## Despliegue en cPanel desde Git

### 1. Preparar dominio y base de datos

- Crea o corrige el subdominio para que apunte a `app_revistas/backend/public`
- Crea la base `xpertiap_apprevistas`
- Crea el usuario `xpertiap_apprevistas` y dale todos los privilegios

### 2. Clonar el repo e instalar dependencias

```bash
cd ~
git clone https://github.com/juanjo2702/App_Revistas.git app_revistas
cd ~/app_revistas/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

### 3. Configurar `.env`

Edita `.env` y ajusta al menos:

```env
DB_PASSWORD=TU_PASSWORD_REAL
```

Si el dominio cambia mas adelante, actualiza tambien:

```env
APP_URL=https://api.apprevistas.xpertiaplus.com
```

### 4. Inicializar Laravel

```bash
php artisan key:generate
chmod -R 775 storage bootstrap/cache
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan optimize
php artisan catalog:sync --source=g-news
```

## Verificacion

Prueba estos endpoints:

```bash
curl -I https://api.apprevistas.xpertiaplus.com/api/health
curl https://api.apprevistas.xpertiaplus.com/api/health
curl https://api.apprevistas.xpertiaplus.com/api/v1/sources
curl https://api.apprevistas.xpertiaplus.com/api/v1/journals
```

El backend puede responder vacio en la raiz `/`; la validacion correcta de esta version se hace sobre `/api/*`.

## Actualizaciones futuras

```bash
cd ~/app_revistas
git pull origin main
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan catalog:sync --source=g-news
```
