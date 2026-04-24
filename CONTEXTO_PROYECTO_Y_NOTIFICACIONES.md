# Contexto del Proyecto y Guía de Notificaciones

## 1. Qué es este proyecto

`App Revistas` es una solución compuesta por dos partes:

- `backend/`: API en Laravel que centraliza revistas científicas de la universidad.
- `mobile/`: app móvil nativa hecha con Quasar + Vue + Capacitor.

La app no consume OJS directamente. La arquitectura actual es:

`App móvil -> backend Laravel -> portales públicos de revistas`

Esto permite:

- evitar exponer credenciales en la app
- unificar varias revistas en un solo catálogo
- controlar mejor los cambios si una revista modifica su estructura
- habilitar descargas, preferencias y alertas desde una sola API

## 2. Estado actual del proyecto

### Backend

El backend ya está desplegado y funcionando en:

- `https://api.apprevistas.xpertiaplus.com`

Endpoints principales:

- `/api/health`
- `/api/v1/sources`
- `/api/v1/journals`
- `/api/v1/journals/{journalId}/issues`
- `/api/v1/issues/{issueId}/articles`
- `/api/v1/articles/{articleId}`
- `/api/v1/articles/{articleId}/pdf`
- `/api/v1/issues/{issueId}/pdf`
- `/api/v1/devices/register`
- `/api/v1/devices/preferences`

### Fuentes integradas

Actualmente están integradas estas colecciones:

1. `Familia de Revistas Científicas UNITEPC`
   - portal: `https://investigacion.unitepc.edu.bo/revista/`
2. `G-News UNITEPC`
   - portal: `https://g-news.unitepc.edu.bo/revista/index.php/revista`
3. `Economía, Innovación y Emprendimiento`
   - portal: `https://investigacionfacefa.unitepc.edu.bo/revistas/index.php/eie/index`

### Móvil

La app móvil ya permite:

- listar colecciones
- listar revistas
- listar números
- listar artículos
- abrir PDF de artículo
- abrir PDF de número completo
- guardar descargas locales
- configurar preferencias del dispositivo
- registrar el dispositivo para alertas

## 3. Estado de las notificaciones

La base técnica ya está lista en gran parte.

### Ya implementado

#### En la app móvil

- solicitud de permisos para alertas
- registro de token push
- guardado del token en el backend
- guardado de preferencias por colección, revista y año
- apertura de la sección de revistas al tocar una notificación compatible

#### En el backend

- registro de dispositivos
- persistencia de preferencias
- segmentación por colección, revista y año
- servicio de envío por Firebase Cloud Messaging
- comando para disparar alertas manualmente

Comando disponible:

```bash
php artisan catalog:notify "Nueva publicación disponible" "Ya puedes revisar las novedades"
```

También admite filtros:

```bash
php artisan catalog:notify "Nueva publicación" "Revisa el catálogo" --source=investigacion
php artisan catalog:notify "Nueva edición" "Ya está disponible" --journal=investigacion:revista-unitepc
php artisan catalog:notify "Novedades 2025" "Consulta los artículos nuevos" --year=2025
```

### Lo que todavía falta para que salgan alertas reales

Faltan dos piezas externas de Firebase:

1. `google-services.json` para Android
2. una credencial JSON de cuenta de servicio para el servidor

## 4. Dónde conseguir `google-services.json`

Este archivo se obtiene desde Firebase Console al registrar la app Android.

Ruta general:

1. Entra a [Firebase Console](https://console.firebase.google.com/)
2. Crea un proyecto nuevo o usa uno existente
3. Dentro del proyecto, agrega una app Android
4. Usa como `package name` el de este proyecto móvil:
   - `org.unitepc.revistas`
5. Firebase te ofrecerá descargar el archivo `google-services.json`

Según la documentación oficial de Firebase para Android, el archivo se descarga desde la configuración de la app Android y debe colocarse en el directorio raíz del módulo de la app. Fuente oficial:

- [Agregar Firebase a Android](https://firebase.google.com/docs/android/setup?hl=es-419)

En este proyecto debe colocarse en:

`mobile/src-capacitor/android/app/google-services.json`

### Estado actual

Ese archivo ya está colocado localmente en la ruta correcta y coincide con:

- proyecto Firebase: `revistas-cientificas-unitepc`
- paquete Android: `org.unitepc.revistas`

### Importante

- el nombre debe quedar exactamente `google-services.json`
- no debe llamarse `google-services (1).json`
- no conviene subirlo al repo

## 5. Dónde conseguir la credencial del servidor

La segunda pieza es una cuenta de servicio en JSON para que el backend Laravel pueda autenticarse contra Firebase y enviar alertas.

### Proyecto Firebase confirmado para esta app

Ya quedó confirmado que el proyecto usado es:

- `revistas-cientificas-unitepc`

Y la app Android registrada corresponde a:

- `org.unitepc.revistas`

Se obtiene así:

1. Entra a [Firebase Console](https://console.firebase.google.com/)
2. Abre tu proyecto
3. Ve a `Project settings`
4. Abre la pestaña `Service accounts`
5. Pulsa `Generate new private key`
6. Firebase descargará un archivo `.json`

Firebase documenta este flujo oficialmente aquí:

- [Firebase Admin SDK setup](https://firebase.google.com/docs/admin/setup)

Ese archivo JSON debe subirse al servidor, por ejemplo a una ruta privada como:

`/home/xpertiap/firebase/firebase-service-account.json`

Luego en `backend/.env` configuras:

```env
FCM_PROJECT_ID=revistas-cientificas-unitepc
FCM_CREDENTIALS=/home/xpertiap/firebase/firebase-service-account.json
```

### Dónde ver el `project_id`

Lo verás:

- dentro del mismo archivo JSON descargado
- o en `Firebase Console > Project settings`

## 6. Qué debes hacer cuando tengas esos archivos

### En local para Android

El archivo ya está en:

`mobile/src-capacitor/android/app/google-services.json`

Puedes reconstruir el APK con:

```bash
cd mobile
npm run apk:release
```

### En el servidor

1. Sube el archivo de cuenta de servicio JSON a una ruta privada
2. Abre `backend/.env`
3. Configura:

```env
FCM_PROJECT_ID=revistas-cientificas-unitepc
FCM_CREDENTIALS=/ruta/absoluta/al/firebase-service-account.json
```

4. Aplica cambios:

```bash
cd ~/apps/app_revistas
git pull origin main
cd backend
php artisan optimize:clear
php artisan optimize
```

## 7. Cómo probar las alertas

### Paso 1. Activar alertas en la app

En la app:

- entra a `Preferencias`
- activa alertas
- guarda preferencias

### Paso 2. Verificar backend

Puedes revisar:

```bash
curl https://api.apprevistas.xpertiaplus.com/api/health
```

Debes ver algo como:

- `devices.registered > 0`
- `devices.pushEnabled > 0`

### Paso 3. Enviar una alerta manual

Ejemplo:

```bash
php artisan catalog:notify "Nueva edición disponible" "Ya puedes revisar las novedades de tus revistas"
```

Ejemplo por colección:

```bash
php artisan catalog:notify "Nueva edición disponible" "Hay novedades en la familia de revistas" --source=investigacion
```

## 8. Observaciones importantes

- `google-services.json` no conviene subirlo al repositorio.
- la cuenta de servicio JSON del backend sí es sensible y no debe subirse a GitHub.
- como la credencial privada fue compartida en la conversación, conviene rotarla después de terminar la configuración final.
- si una revista publica nuevos números, el catálogo conviene resincronizarlo con:

```bash
php artisan catalog:sync --source=investigacion
php artisan catalog:sync --source=g-news
php artisan catalog:sync --source=facefa-eie
```

## 9. Próximas fases recomendadas

1. Configurar Firebase completo para alertas reales en producción.
2. Automatizar la resincronización del catálogo.
3. Disparar alertas automáticamente cuando se detecten nuevos números o artículos.
4. Mejorar navegación profunda desde notificaciones a revista o número específico.
