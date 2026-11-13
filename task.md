# Conversa OS CRM — Plan maestro de implementación

## 1. Objetivo

Ampliar el CRM postal de la Agencia Boliviana de Correos (AGBC) para integrar datos de los sistemas SIOP y SITRA, administrar paquetes y eventos postales, crear campañas de mensajería por WhatsApp y probar conversaciones con IA local sin consumir conversaciones de Meta.

El desarrollo debe conservar el layout, la navegación, los temas visuales y la autenticación existentes.

## 2. Estado actual del proyecto

- Aplicación Laravel 12, PHP 8.2+, Blade, Vite y Livewire 4.
- Layout autenticado compartido en `resources/views/layouts/crm.blade.php`; las rutas principales del CRM usan middleware `auth`.
- Módulos disponibles: dashboard (`/dashboard`), chat de WhatsApp (`/chat`), chat automatizado de prueba (`/chat-automatizado`), tarifas (`/tarifas`), rastreo nacional/internacional, campañas postales, plantillas, respuestas automáticas, asistente y configuraciones.
- Cotizaciones de EMS y Delivery Express consultan servicios de tarifas; el rastreo usa los registros postales locales sincronizados.
- `OllamaService` alimenta el asistente y los flujos automáticos conversacionales; respuestas automáticas también conserva plantillas estáticas configurables.
- Los datos postales se normalizan en `postal_packages` y `postal_events`; `api_records` se mantiene para la integración genérica.
- El chat registra mensajes entrantes y salientes, admite medios, modos por conversación, números de prueba y respuestas automáticas.
- El procesamiento de la cola y la sincronización están definidos en `routes/console.php`; esto no confirma que el scheduler, credenciales externas o datos de producción estén disponibles en cada ambiente.
- Las migraciones reflejan cambios locales del proyecto. Verificar el estado con `php artisan migrate:status` antes de afirmar que están aplicadas en una base concreta.
- No guardar credenciales ni contraseñas de demostración en este documento. Usar `.env` y los mecanismos seguros de configuración del ambiente.

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

Implementación actual: `PostalApiSyncService` y `PostalTrackingManager` sincronizan/listan por alcance. Los puntos siguientes describen los requisitos del adaptador y no sustituyen la verificación de cada ambiente.

### 4.1 Fuentes

Variables que el código consulta actualmente (los valores sensibles viven solo en `.env`):

```env
PAQUETES_EVENTOS_API_URL=
PAQUETES_EVENTOS_API_TOKEN=
TRACKING_API_URL=
TRACKING_SQLSERVER_TOKEN=
POSTAL_API_TIMEOUT=90
POSTAL_API_VERIFY_SSL=
EMS_CALCULATOR_API_URL=
EMS_CALCULATOR_VERIFY_SSL=
DELIVERY_EXPRESS_TARIFF_API_URL=
DELIVERY_EXPRESS_TARIFF_API_TOKEN=
CDS_API_URL=
CDS_API_TOKEN=
```

No copiar valores ni tokens productivos a este documento. La configuración actual contiene valores predeterminados para algunos endpoints; verificar autenticación, certificados y formato de respuesta en cada ambiente. El adaptador debe soportar las formas de respuesta probadas por sus pruebas.

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

Implementación actual: componente Livewire `PostalCampaignManager`, `PostalCampaignRegistry` y `PostalCampaignTemplates`, con pantallas separadas por alcance:

```text
/campanas-postales/nacional/registro
/campanas-postales/internacional/registro
/mensajes-predeterminados/nacional
/mensajes-predeterminados/internacional
```

### 5.1 Filtros

- El alcance nacional consulta SIOP y el internacional consulta SITRA; cada vista conserva su alcance.
- Los filtros de paquetes incluyen rango de fecha, evento, cantidad máxima, teléfono y departamentos según la pantalla.
- Catálogo de eventos usado por los filtros:
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

El resumen se calcula con el conjunto filtrado actual y puede no incluir una métrica si la interfaz no la expone.

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

- La campaña se prepara con los filtros y mensajes por paquete, y el envío se despacha a `SendPostalCampaignMessages`.
- La cola se procesa desde el scheduler. Revisar el Job y la configuración del proveedor para verificar el intervalo, reintentos y garantías de no duplicación antes de ampliar las funciones.
- El modo prueba y el envío real dependen de la configuración de números de prueba y WhatsApp Cloud API.
- No se debe declarar pausa, reanudación o cancelación como terminadas si no están implementadas y cubiertas por pruebas.

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

## 6. Módulo C — Chat automatizado y conversaciones WhatsApp

La aplicación tiene dos flujos distintos. `/chat-automatizado` es una simulación conversacional de cotizaciones y rastreo; `/chat` es el chat CRM conectado al flujo de WhatsApp. No son un Sandbox persistente separado.

```text
/chat-automatizado
/chat
```

### 6.1 Objetivo

Permitir probar respuestas conversacionales sin enviar mensajes de prueba desde el simulador a Meta; mantener el chat real sujeto a la configuración del proveedor y a las reglas de números de prueba.

### 6.2 Funciones

- `/chat-automatizado` permite conversar y cotizar EMS/Delivery Express, además de consultar guías presentes en los registros locales.
- `/chat` muestra conversaciones de WhatsApp, permite responder y enviar adjuntos, y ofrece modos global, manual, IA y automático por contacto.
- El webhook procesa mensajes entrantes; el modo automático conserva contexto para completar flujos de cotización y rastreo.
- El historial conversacional se guarda en `whatsapp_messages`; no existe actualmente un módulo separado con tablas `whatsapp_sandbox_conversations` y `whatsapp_sandbox_messages`.

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

### `postal_packages` — implementada

- La tabla y modelo están implementados. Los campos y restricciones exactos se definen en las migraciones vigentes.
- Campos principales: `id`.
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

### `postal_events` — implementada

- La tabla y modelo están implementados; revisar la migración si se requiere modificar la forma de deduplicación de eventos.
- `id`.
- `postal_package_id`.
- `source_event`.
- `event_name`.
- `event_at`.
- `raw_payload` JSONB.
- `created_at`, `updated_at`.
- Unique sugerido: paquete + evento + fecha.

### `postal_campaigns` — implementada

- La tabla y modelo están implementados; las columnas exactas pueden diferir de esta propuesta inicial.
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

### `postal_campaign_messages` — implementada

- La tabla está implementada y mantiene la relación campaña-paquete. Ver migración para la lista exacta de columnas y estados.
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

### `whatsapp_sandbox_conversations` — propuesta futura

- No implementada.
- `id`.
- `postal_package_id` nullable.
- `session_key`.
- `mode`: sandbox.
- `created_by`.
- Timestamps.

### `whatsapp_sandbox_messages` — propuesta futura

- No implementada.
- `id`.
- `conversation_id`.
- `role`: agent, customer, assistant.
- `content`.
- `metadata` JSONB.
- `created_at`, `updated_at`.

## 8. Rutas actuales y rutas futuras

Rutas autenticadas implementadas (ver `routes/web.php`):

```text
GET  /dashboard
GET  /chat
GET  /chat-automatizado
GET  /tarifas
GET  /rastreo/nacional
GET  /rastreo/internacional
GET  /campanas-postales/nacional/registro
GET  /campanas-postales/internacional/registro
GET  /mensajes-predeterminados/nacional
GET  /mensajes-predeterminados/internacional
GET  /respuestas-automaticas
GET  /configuraciones
POST /api/assistant/chat
POST /configuraciones/sincronizar-ahora
```

El webhook de WhatsApp está expuesto para la integración del proveedor y no forma parte del grupo autenticado del CRM. Confirmar su verificación de firma/token en el controlador y no protegerlo con login de usuario.

Las rutas de Sandbox independiente, control API de campaña y acciones de pausa/reanudación/cancelación son propuestas futuras; no documentarlas como endpoints existentes.

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

## 11. Próximas verificaciones y trabajo pendiente

1. Verificar configuración y conectividad de SIOP, SITRA, CDS, tarifarios EMS/Delivery Express y Ollama por ambiente.
2. Ejecutar `php artisan migrate:status` y aplicar migraciones pendientes con aprobación del responsable del ambiente.
3. Confirmar en ejecución la cola, scheduler y bloqueo de los comandos programados.
4. Completar pruebas de autorización y operaciones sensibles (sincronización y envío), si forman parte del alcance de producción.
5. Revisar la experiencia de seguimiento de campaña; documentar pausa, reanudación y cancelación solo si se implementan.
6. Decidir si hace falta un Sandbox persistente separado; el chat automatizado y el historial de WhatsApp existentes no lo sustituyen exactamente.
7. Validar en un entorno de prueba los flujos completos de cotización EMS/Delivery Express y rastreo con respuestas reales del proveedor, sin inventar tarifas o estados.
8. Ejecutar las pruebas focalizadas afectadas y, antes de liberar, la suite completa, `php artisan view:cache` y `npm run build`.

## 12. Criterios de aceptación

Estos criterios son metas de producto; un criterio no queda verificado solo porque aparezca en este archivo.

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

## 13. Funcionalidad implementada actualmente

### Sincronización y paquetes

- `PostalApiSyncService` sincroniza por alcance: nacional/SIOP e internacional/SITRA; admite rangos de fecha, paginación, deduplicación y actualización de eventos.
- `PostalTrackingManager` proporciona filtros, paginación, detalle de guía y sincronización manual desde la interfaz.
- El scheduler define `crm:sync-api` cada cinco minutos y sincronizaciones postales/CDS cada treinta minutos, sujetas al ajuste de sincronización.
- El repositorio incluye campos para teléfonos de origen y destino, evento actual, payload y fecha de sincronización.
- La configuración y la disponibilidad real de cada API deben verificarse por ambiente; el código no demuestra que todos los entornos tengan credenciales o conexión.

### Campañas y filtros

- `PostalCampaignManager` prepara campañas y sus mensajes por paquete, muestra historial y despacha `SendPostalCampaignMessages` a la cola.
- Hay vistas/registro separados para campañas nacionales e internacionales, y plantillas postales por alcance y categoría.
- Las pruebas de operaciones cubren filtros/paginación de rastreo (14 por página), cambios de alcance, sincronización simulada y filtros/paginación de tarifas.
- Las capacidades de pausa, reanudación y cancelación no deben suponerse; confirmar el código y pruebas antes de presentarlas como disponibles.

### Chat, automatización e IA

- `Chat` administra conversaciones, envíos manuales, adjuntos, perfil de contacto y modo por conversación (`global`, `manual`, `ai`, `automatic`).
- `WhatsAppAutoReplyService` coordina respuestas estáticas y flujos postales con historial; `AutomaticResponseMatcher` selecciona respuestas por frases activadoras.
- Los flujos automáticos de cotización y rastreo derivan al agente conversacional para obtener tarifas/estados verificados y mantener contexto.
- `/chat-automatizado` ejecuta una conversación simulada de cotización y rastreo sin ser un Sandbox persistente separado.
- Las respuestas automáticas incluyen administración de plantillas, filtros, paginación y activadores para cotizaciones y rastreo.
- `OllamaService` integra el modelo local y las secciones de instrucciones almacenadas en `ai_prompt_sections`.

### Dashboard, configuración y auditoría

- Los modales del dashboard muestran desgloses para mensajes no leídos, actividad de las últimas 24 horas, estados de mensajes enviados, campañas activas y paquetes por origen.
- `/configuraciones` ofrece preferencias del sistema y controles de sincronización.
- Existe una tabla/modelo de auditoría para acciones del CRM.
- El chat incluye el nombre visible “Chat global” para la lista de conversaciones personales.

### Mensajes y validaciones

- Los webhooks entrantes de WhatsApp y la actualización de estado se procesan con sus controladores y almacenamiento en `whatsapp_messages`.
- Las pruebas focalizadas recientemente ejecutadas: `AutomaticResponsesTest` (8 pruebas), `DashboardMetricsTest` (2 pruebas), los 3 flujos automáticos de cotización/rastreo y la prueba de renderizado del chat.
- Una ejecución de `WhatsAppWebhookTest` reportó 30 pruebas aprobadas y 2 fallos de aserciones de interfaz preexistentes/no relacionadas (texto de preferencias del sistema y perfil lateral del chat); deben revalidarse antes de afirmar que la suite completa está verde.
- `php artisan view:cache` y `git diff --check` pasaron en las modificaciones recientes descritas en el historial de trabajo; ejecutar nuevamente antes de una entrega si hubo cambios posteriores.

## 14. Pendientes y límites conocidos

- Verificar en cada ambiente la conexión, credenciales, formato y disponibilidad de SIOP, SITRA, CDS, APIs tarifarias, Ollama y WhatsApp Cloud API.
- Confirmar qué migraciones están aplicadas en la base objetivo; la presencia de archivos de migración en el repositorio no implica que producción esté actualizada.
- Verificar en ejecución los workers/colas y el scheduler; su definición en el código no demuestra que los procesos estén corriendo.
- Confirmar y probar permisos para las operaciones sensibles de sincronización y envío.
- No existe un Sandbox persistente separado con las tablas propuestas; implementarlo solo si es requisito.
- Confirmar el alcance de pausa, reanudación, cancelación, reintentos y prevención de duplicados de campañas antes de documentar esas capacidades como garantizadas.
- Revalidar los dos fallos reportados por `WhatsAppWebhookTest` (aserciones de texto en preferencias del sistema y perfil lateral), y ejecutar la suite completa antes de liberar.
- Validar las estadísticas del dashboard contra los datos del ambiente de destino; las pruebas cubren cálculos con datos de prueba, no los datos de producción.

### 14.1 Plan para soportar picos de mensajes entrantes de WhatsApp

**Situación actual en el código:** `WhatsAppWebhookController` valida la firma y persiste mensajes con el identificador del proveedor como clave idempotente. La descarga de medios ya se delega a `ProcessWhatsAppInboundMedia`, en una cola separada con tres intentos y backoff; una falla al persistir o encolar responde `503` para permitir el reintento del proveedor. Las respuestas automáticas se encolan en `whatsapp-incoming`, conservando el retraso de un minuto que permite agrupar mensajes de una misma conversación. `routes/console.php` ahora procesa `whatsapp-incoming`, `whatsapp-media` y `default`, pero lo hace en ejecuciones de hasta 50 segundos iniciadas cada minuto. Esto reduce trabajo síncrono en el webhook, pero todavía no garantiza workers continuos ni capacidad ante un pico; las respuestas automáticas mantienen una política de un intento porque repetir un envío externo cuando el resultado es incierto puede duplicar mensajes.

**Cambios de código aplicados:** descarga de medios fuera de la petición; colas prioritarias separadas para respuestas entrantes y medios; manejo explícito de error cuando no se logra persistir/encolar; reintentos limitados con backoff para descarga de medios; y actualización del comando programado para consumir las nuevas colas además de `default`.

**Plan recomendado, por etapas:**

1. **Medir y fijar el objetivo:** acordar con operaciones el pico esperado de mensajes por minuto, la latencia máxima aceptable del webhook y el tiempo máximo para iniciar una respuesta automática. Registrar una línea base y usar esos objetivos para decidir la capacidad; no asumir que un número fijo de workers es suficiente.
2. **Acortar el webhook con seguridad:** conservar la verificación de firma, registrar el mensaje entrante de forma durable y encolar su procesamiento antes de responder `200`. Mover descarga de medios y procesamiento lento fuera de la petición. Si no se pudo persistir o encolar, responder con error para que WhatsApp pueda reintentar; verificar idempotencia por ID del proveedor tanto en almacenamiento como en respuestas.
3. **Mantener workers disponibles:** pendiente de despliegue. En producción, ejecutar workers persistentes bajo un supervisor/servicio administrado y reiniciarlos al desplegar. Definir concurrencia, memoria, timeout y política de reinicio con base en los tiempos medidos. El scheduler actualizado es solo una alternativa temporizada y no reemplaza workers persistentes.
4. **Aislar y proteger el procesamiento:** ya se separaron las colas de respuestas entrantes y medios y se priorizan antes de `default`. La descarga de medios tiene reintentos limitados y backoff; falta monitorear los jobs agotados en `failed_jobs`. No reintentar automáticamente envíos de respuesta cuando el resultado del proveedor sea ambiguo, hasta implementar una garantía de idempotencia de salida. Mantener y probar el orden por conversación.
5. **Controlar dependencias lentas:** fijar timeouts y límites de concurrencia para Ollama y APIs externas. Si una dependencia no responde, usar un fallback estático seguro cuando corresponda y conservar el error para diagnóstico, sin dejar bloqueado el webhook ni perder el mensaje.
6. **Observar y probar antes de producción:** pendiente. Monitorear profundidad y antigüedad de cola, duración y fallos de jobs, tiempo del webhook, workers activos y disponibilidad de dependencias. Hacer una prueba de carga gradual que simule el pico acordado y una ráfaga superior, comprobando que no se pierdan ni dupliquen mensajes, que la cola vuelva a vaciarse y que se cumplan los objetivos acordados. Definir alertas y responsables de atención.

**Criterio para darlo por listo:** documentar el pico objetivo, resultados de la prueba, configuración de workers y alertas. No afirmar capacidad ni disponibilidad garantizada hasta verificarlo en el ambiente objetivo. Confirmar con negocio si el retraso actual de un minuto antes de procesar respuestas automáticas es intencional; no reducirlo sin validar límites, orden y experiencia de respuesta.

## 15. Estado de validación y mantenimiento del documento

- Fecha de actualización: 11/11/2026.
- Este documento describe el estado visible en el repositorio. No confirma el estado de una base de datos desplegada, credenciales, conectividad externa ni volumen de datos en producción.
- El árbol de trabajo contiene cambios locales y archivos nuevos/eliminados sin consolidar. No se debe asumir que todo el código local ya está desplegado.
- Para actualizar este estado, comparar `routes/web.php`, `routes/console.php`, `app/Livewire`, servicios, migraciones y pruebas; no copiar afirmaciones antiguas sobre migraciones ejecutadas o cargas de datos sin volver a verificarlas.
- Comandos recomendados para una verificación del ambiente:

```bash
php artisan migrate:status
php artisan route:list
php artisan test
php artisan view:cache
npm run build
```

- Ejecutar la suite completa y build antes de liberar; registrar en este archivo cualquier fallo, límite del entorno o validación que no se pudo realizar.
