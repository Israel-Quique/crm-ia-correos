# Conversa OS CRM — Plan maestro de implementación

## 1. Objetivo

Ampliar el CRM postal de la Agencia Boliviana de Correos (AGBC) para integrar datos de los sistemas SIOP y SITRA, administrar paquetes y eventos postales, crear campañas de mensajería por WhatsApp y probar conversaciones con IA local sin consumir conversaciones de Meta.

El desarrollo debe conservar el layout, la navegación, los temas visuales y la autenticación existentes.

## 2. Estado actual del proyecto

- Laravel 11/12 con Blade, Vite y Livewire instalado (`livewire/livewire v4.4.8`; validar compatibilidad si se requiere fijar Livewire 3).
- PostgreSQL configurado para la base `crm`.
- Login funcional con sesiones, middleware `auth` y usuario demo:
  - Email: `sofia@crm.test`
  - Contraseña: `NexusCRM2026!`
- Layout compartido en `resources/views/layouts/crm.blade.php`.
- Dashboard en `/dashboard`.
- Chat CRM en `/chat`.
- Asistente Livewire en `/assistant`.
- Configuraciones en `/configuraciones`.
- Ollama local integrado mediante `OllamaService` y `POST /api/assistant/chat`.
- Modelos locales disponibles: `llama3.2:3b`, `qwen2.5:7b`, `qwen2.5:0.5b` y `deepseek-r1:8b`.
- Sincronizador genérico existente: `php artisan crm:sync-api`.
- Tabla genérica existente: `api_records`, con deduplicación por `source + external_id`.
- Scheduler actual configurado para sincronizar cada cinco minutos.

## 3. Reglas que no deben romperse

- Todas las vistas autenticadas deben utilizar el layout padre `layouts.crm`.
- Sidebar y top bar deben mantenerse visibles en Dashboard, Chat, Campañas, Sandbox, Configuraciones y futuras vistas.
- La navegación debe conservar módulos plegables y desplegables.
- El modo día/noche debe conservarse entre rutas usando `localStorage`.
- La paleta visual debe conservar el estilo Conversa OS.
- No enviar mensajes reales a Meta desde el Sandbox.
- Toda acción real de envío debe respetar el modo prueba y una confirmación explícita.
- No guardar duplicados provenientes de SIOP o SITRA.

## 4. Módulo A — Integración SIOP y SITRA

### 4.1 Fuentes

Crear dos conexiones lógicas configurables:

```env
SIOP_API_URL=
SIOP_API_TOKEN=
SIOP_API_TIMEOUT=60
SITRA_API_URL=
SITRA_API_TOKEN=
SITRA_API_TIMEOUT=60
```

No asumir el formato final hasta recibir la documentación real de cada API. El adaptador debe soportar respuestas con lista directa o bajo `data`.

### 4.2 Datos mínimos de paquete

Cada registro postal debe almacenar:

- `source`: `siop` o `sitra`.
- `external_id`.
- `tracking_number`.
- `sender`.
- `recipient`.
- `phone`.
- `current_event`.
- `destination_branch`.
- `updated_at_api`.
- `raw_payload` JSONB.
- `last_synced_at`.

### 4.3 Deduplicación y actualización

- La clave única mínima será `source + external_id`.
- Si no existe el registro, insertar.
- Si existe, actualizar los campos recibidos.
- El evento/estado debe actualizarse siempre que la API entregue una versión nueva.
- No sobrescribir datos locales con `null` si la API no envía ese campo.
- Mantener el JSON original para auditoría.
- Registrar errores de formato, autenticación, timeout y respuestas HTTP no exitosas.

### 4.4 Extracción

- Solicitar los últimos 1000 registros.
- Ordenar por fecha de actualización descendente.
- Aplicar paginación si la API no permite `limit=1000`.
- Crear comandos separados o una opción por fuente:

```bash
php artisan postal:sync siop
php artisan postal:sync sitra
php artisan postal:sync --all
```

- Programar la sincronización con `withoutOverlapping()`.

## 5. Módulo B — Campañas Postales

Crear el componente Livewire `PostalCampaignManager` y la ruta:

```text
/campanas-postales
```

### 5.1 Filtros

- Fuente: SIOP, SITRA o ambas.
- Evento postal, selección múltiple:
  - Recibido en oficina de origen.
  - En camino a sucursal destino.
  - Recibido en sucursal destino.
  - Cartero en ruta de reparto.
  - Entregado.
  - Cancelado.
- Fecha desde.
- Fecha hasta.
- Orden descendente: paquete más nuevo primero.
- Cantidad máxima del lote.
- Búsqueda por número de guía, destinatario o teléfono.

### 5.2 Resumen del filtro

Mostrar antes de enviar:

- Cantidad total encontrada.
- Cantidad seleccionada.
- Distribución por evento.
- Rango de fechas.
- Fuente de origen.
- Cantidad de teléfonos válidos.
- Cantidad excluida por duplicidad o falta de teléfono.

### 5.3 Campaña

Guardar:

- Nombre.
- Usuario creador.
- Evento seleccionado.
- Filtros JSON.
- Plantilla de mensaje.
- Intervalo entre mensajes.
- Modo prueba.
- Estado: borrador, preparada, ejecutando, pausada, cancelada, finalizada, con errores.
- Totales de enviados, pendientes, errores y omitidos.
- Fechas de inicio y finalización.

### 5.4 Envío y rate limiting

- Procesar mediante Jobs y cola.
- No ejecutar un lote completo dentro de una petición HTTP.
- Respetar el intervalo configurado entre mensajes.
- Usar `WithoutOverlapping` y bloqueo por campaña.
- Permitir pausar, reanudar y cancelar.
- El modo prueba no debe llamar a Meta.
- El envío real debe usar una abstracción `WhatsAppMessageSender`.

### 5.5 Progreso Livewire + Alpine.js

Mostrar:

- `Enviando X de Y paquetes`.
- Porcentaje.
- Enviados.
- Pendientes.
- Errores.
- Omitidos.
- Último número de guía procesado.
- Último error.
- Botones Pausar, Reanudar y Cancelar.

## 6. Módulo C — WhatsApp Sandbox con IA local

Crear la ruta:

```text
/whatsapp-sandbox
```

Crear el componente Livewire `WhatsAppSandbox`.

### 6.1 Objetivo

Simular el flujo completo agente-cliente sin consumir la cuota gratuita de Meta ni enviar mensajes reales.

### 6.2 Funciones

- Seleccionar un paquete postal existente.
- Mostrar tracking, destinatario, teléfono, evento y sucursal.
- Generar plantilla automática:

```text
Hola [Nombre], tu paquete [Tracking] está en estado [Evento] y tiene como destino [Sucursal].
```

- Simular el mensaje enviado por el agente.
- Permitir escribir como cliente.
- Enviar el mensaje simulado a Ollama.
- Generar una respuesta sugerida usando el contexto del paquete.
- Permitir aceptar, editar o descartar la respuesta.
- Mostrar claramente que todo es Sandbox y no consume Meta.
- Guardar la conversación local como historial de prueba.

### 6.3 Contexto de IA

El prompt de Ollama debe incluir únicamente los datos necesarios:

- Número de guía.
- Estado actual.
- Destinatario.
- Sucursal destino.
- Último evento.
- Mensaje del cliente.

No enviar secretos, tokens ni datos innecesarios al modelo.

## 7. Diseño de base de datos propuesto

### `postal_packages`

- `id`.
- `source`.
- `external_id`.
- `tracking_number`.
- `sender`.
- `recipient`.
- `phone`.
- `current_event`.
- `destination_branch`.
- `updated_at_api`.
- `raw_payload` JSONB.
- `last_synced_at`.
- `created_at`, `updated_at`.
- Unique: `source + external_id`.
- Index: `tracking_number`, `phone`, `current_event`, `updated_at_api`.

### `postal_events`

- `id`.
- `postal_package_id`.
- `source_event`.
- `event_name`.
- `event_at`.
- `raw_payload` JSONB.
- `created_at`, `updated_at`.
- Unique sugerido: paquete + evento + fecha.

### `postal_campaigns`

- `id`.
- `user_id`.
- `name`.
- `filters` JSONB.
- `events` JSONB.
- `template` TEXT.
- `interval_seconds`.
- `test_mode`.
- `status`.
- Contadores de progreso.
- Fechas de ejecución.

### `postal_campaign_messages`

- `id`.
- `postal_campaign_id`.
- `postal_package_id`.
- `phone`.
- `tracking_number`.
- `message`.
- `provider`.
- `provider_message_id`.
- `status`: pending, processing, sent, failed, skipped.
- `error_message`.
- `sent_at`.
- Unique sugerido: campaña + paquete.

### `whatsapp_sandbox_conversations`

- `id`.
- `postal_package_id` nullable.
- `session_key`.
- `mode`: sandbox.
- `created_by`.
- Timestamps.

### `whatsapp_sandbox_messages`

- `id`.
- `conversation_id`.
- `role`: agent, customer, assistant.
- `content`.
- `metadata` JSONB.
- `created_at`, `updated_at`.

## 8. Rutas previstas

```text
GET  /campanas-postales
GET  /whatsapp-sandbox
POST /api/postal/sync
POST /api/postal/campaigns/{campaign}/pause
POST /api/postal/campaigns/{campaign}/resume
POST /api/postal/campaigns/{campaign}/cancel
POST /api/assistant/chat
```

Todas las rutas del CRM deben requerir autenticación.

## 9. Estilo visual obligatorio

### Layout

- Todas las vistas deben extender `resources/views/layouts/crm.blade.php`.
- Sidebar persistente.
- Top bar persistente.
- Contenido hijo mediante `@yield('content')`.
- No duplicar sidebar o top bar dentro de módulos.

### Paleta Conversa OS

- Amarillo sidebar/acento: `#EBB52C`.
- Amarillo primario: `#FFCB05`.
- Hover: `#DDA221`.
- Fondo oscuro principal: `#0F171E`.
- Fondo oscuro profundo: `#080C10`.
- Fondo día: `#F7F9FC`.
- Tarjetas día: `#FFFFFF` / `#F3F4F6`.
- Tarjetas noche: `#0C1528`.
- Bordes: `#D1D5DB` en día y `#233451` en noche.
- Texto oscuro: `#111820`.
- Texto secundario: `#64748B`.
- Estado naranja: `#F59E0B`.
- Estado azul: `#3B82F6`.
- Estado gris: `#6B7280`.
- Estado verde: `#10B981`.
- Estado rojo: `#EF4444`.

### Componentes

- Tarjetas con bordes suaves y radios entre 10 y 16px.
- Botones principales en amarillo/naranja.
- Estados con badges pequeños y legibles.
- Inputs blancos en modo día y azul oscuro en modo noche.
- Sidebar con grupos plegables.
- Feedback visual de carga y errores.
- Diseño responsive para escritorio, tablet y móvil.

## 10. Seguridad

- Tokens de APIs solo en `.env`.
- Nunca guardar tokens en vistas, JavaScript o JSON público.
- Validar y limitar todos los filtros.
- Sanitizar mensajes mostrados en el Sandbox.
- Proteger formularios con CSRF.
- Aplicar autorización por rol para campañas y sincronizaciones.
- Registrar auditoría de sincronizaciones y envíos.
- No permitir llamadas a Meta desde modo prueba.
- Añadir timeout, reintentos limitados y manejo de errores HTTP.

## 11. Orden recomendado de implementación

1. Confirmar documentación y autenticación de SIOP y SITRA.
2. Crear migraciones de `postal_packages` y `postal_events`.
3. Crear modelos y adaptadores SIOP/SITRA.
4. Reemplazar o complementar `api_records` con registros postales normalizados.
5. Crear comando y Job de sincronización de 1000 registros.
6. Crear `PostalCampaignManager` y filtros.
7. Crear tablas y Jobs de campañas.
8. Crear barra de progreso, pausa, reanudación y cancelación.
9. Crear migraciones y componente `WhatsAppSandbox`.
10. Integrar el contexto postal con Ollama.
11. Añadir pruebas de deduplicación, actualización de estados y modo prueba.
12. Ejecutar validación Blade, migraciones, colas, scheduler y Vite.

## 12. Criterios de aceptación

- SIOP y SITRA pueden sincronizar los últimos 1000 registros.
- Los registros repetidos no generan filas duplicadas.
- Un cambio de estado actualiza el paquete existente.
- El orden por fecha muestra primero el paquete más nuevo.
- Los filtros por evento, cantidad y fechas funcionan juntos.
- Una campaña muestra el total antes de iniciar.
- El rate limiting se respeta entre mensajes.
- Pausar, reanudar y cancelar funcionan sin duplicar envíos.
- El modo prueba nunca llama a Meta.
- El Sandbox permite conversar como cliente y agente.
- Ollama genera respuestas usando el contexto del paquete.
- Todas las nuevas vistas conservan sidebar, top bar y tema día/noche.
- La aplicación sigue compilando con `npm run build`.
- Las migraciones se ejecutan sin errores en PostgreSQL.

## 13. Cambios realizados durante la iteración actual

### Sincronización y paquetes

- El scheduler de Laravel ejecuta la sincronización cada cinco minutos mediante `everyFiveMinutes()` y `withoutOverlapping()`.
- Las campañas filtran paquetes con guía válida y número de teléfono registrado.
- La normalización de SITRA reconoce `telefono`, `celular`, `mobile`, `phone`, `telefono_celular` y contactos anidados.
- Se agregó `origin_phone` para el teléfono del remitente; `phone` conserva el teléfono del destinatario.
- Se creó y ejecutó la migración `2026_10_27_000006_add_origin_phone_to_postal_packages.php`.
- La tabla de paquetes muestra guía, teléfonos de origen y destino, destinatario, evento y fecha.

### Campañas y filtros

- El selector de eventos se convirtió en un combo compacto.
- Se agregaron `Aplicar filtros` y `Limpiar filtros`.
- Los filtros Desde, Hasta, Cantidad máxima y Eventos del flujo se organizan en fila y son responsivos.
- El resumen superior solo muestra los seis eventos definidos para cada alcance.
- El combo conserva contraste en modo claro y modo oscuro.

### Configuraciones y registro

- La ruta `/configuraciones` quedó disponible desde el menú lateral.
- Configuraciones incluye preferencias, estado de servicios y registro de actualizaciones.
- El registro muestra los cinco últimos paquetes actualizados con fuente, guía, teléfonos, evento y fecha.
- La pantalla fue rediseñada con tarjetas oscuras, interruptores e indicadores de PostgreSQL, Ollama y sincronización API.

### Mensajes y validaciones

- Se preparó `crm:incoming-message` con notificación de escritorio, imagen del contacto y tono de dos notas.
- La notificación queda lista para conectarse al webhook real de WhatsApp.
- Se validó PHP y se ejecutaron correctamente `npm run build` y `php artisan view:cache`.

## 14. Bloqueadores actuales

Para completar la integración real todavía se necesita:

- URL y documentación de SIOP.
- URL y documentación de SITRA.
- Método de autenticación de cada API.
- Ejemplo JSON real de paquete.
- Catálogo oficial de eventos postales.
- Credenciales y configuración de Meta WhatsApp Cloud API para producción.
- Decisión de qué roles pueden sincronizar, crear campañas y enviar mensajes.
- SIOP conectado a `https://dev.correos.gob.bo:18100/api/paquetes-eventos`.
- SITRA conectado a `https://sitra.correos.gob.bo/api/tracking/paquetes`.
- `PostalApiSyncService` usa 5 páginas de 200 registros para obtener hasta 1000 paquetes.
- Ejecutados correctamente: `php artisan postal:sync nacional` y `php artisan postal:sync internacional`.
- La carga real creó 1000 registros de SIOP para Nacional y 1000 registros de SITRA para Internacional.
- Nacional queda limitado a SIOP e Internacional queda limitado a SITRA.
- Los eventos se guardan en `postal_events` y el evento más reciente actualiza `postal_packages.current_event`.
- Campañas separadas: `/campanas-postales/nacional` y `/campanas-postales/internacional`.
