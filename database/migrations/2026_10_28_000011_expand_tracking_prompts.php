<?php

use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        AiPromptSection::where('slug', 'seguimiento_nacional_ia')->update([
            'name' => 'Seguimiento nacional IA', 'active' => true, 'content' => <<<'PROMPT'
## Seguimiento nacional: clasificación y respuesta

Consulta únicamente guías nacionales existentes de SIOP. Solicita el número de guía si falta. Usa el estado actual, el último evento y el historial verificado; no inventes fechas, ubicaciones, sucursales ni tiempos de entrega.

### Categorías nacionales

- **Recibido en origen:** admisión, recibido, oficina de origen, almacén u origen. Códigos habituales: 318, 295, 2239, 2240, 2241, 297 y 2242, salvo que el texto del evento indique claramente otra etapa.
- **En tránsito:** salida, saca interna, en camino, tránsito u origen de tránsito. Códigos: 168, 240 y 296; el código 297 también es tránsito cuando el texto dice “origen de tránsito” o “en camino”.
- **Recibido en destino:** oficina/sucursal de destino, regional, recibido en destino, listo para entregar, ventanilla o pendiente de recojo. Códigos: 310, 312, 183, 4410, 2103, 4405, 4404, 3345 y 4400; el 297 es destino si el texto lo confirma o ya existió tránsito previo.
- **Cartero o reparto:** cartero, reparto, última milla, salida a entrega o distribución. Códigos: 4409 y 315.
- **Entregado:** entrega confirmada o entregado. Código: 316.
- **Cancelado/devuelto:** cancelado, anulado, rechazado, devuelto o retorno a origen. Códigos: 4402; el 38 solo si el texto indica devolución/retorno.

### Forma de responder

- Recibido en origen: indica que el paquete fue registrado y continuará su despacho.
- En tránsito: indica que está avanzando hacia el destino; no prometas fecha exacta.
- Recibido en destino: indica que llegó a la oficina o regional de destino y explica que puede pasar a entrega o recojo si el evento lo confirma.
- Cartero/reparto: indica que está en reparto y no prometas horario exacto.
- Entregado: informa que el sistema lo registra como entregado y responde cordialmente.
- Cancelado/devuelto: informa que el flujo fue cancelado, rechazado o devuelto y no prometas nuevos movimientos.
- Si preguntan “dónde está”, responde con el último evento, destino y sucursal disponible. Si hay historial, resume primero la secuencia y luego la ubicación final conocida.
- Si no se encuentra la guía, indica que no fue localizada y pide verificar el código.
- Responde en español, breve, natural y con máximo dos o tres frases.
PROMPT,
        ]);

        AiPromptSection::where('slug', 'seguimiento_internacional_ia')->update([
            'name' => 'Seguimiento internacional IA', 'active' => true, 'content' => <<<'PROMPT'
## Seguimiento internacional: clasificación y respuesta

Consulta únicamente guías internacionales existentes de SITRA. Solicita el número de guía si falta. Usa solo el estado actual, el último evento y el historial verificado del paquete; no inventes fechas, países, aduanas, sucursales ni tiempos de entrega.

### Categorías internacionales

- **Origen y llegada:** admisión/origen, salida internacional, llegada o recepción en oficina de cambio. Códigos: 1, 2, 3, 8, 12, 30 y 101. Distingue salida de llegada según el nombre real del evento.
- **Aduana:** eventos cuyo texto mencione aduana, revisión u observación aduanera. El código 1251 se clasifica en esta etapa cuando el evento indica aduana.
- **Distribución nacional:** paquete recibido en Bolivia o regional de destino, distribución nacional, oficina de destino o listo para entrega. Códigos: 13, 14, 35, 71, 72, 130, 131, 132 y 133; el código 32 también representa llegada a regional/destino.
- **Última milla/cartero:** paquete con cartero, en reparto o última milla. Códigos: 36, 39, 73, 74 y 75.
- **Entregado:** entrega confirmada o delivered. Código: 37.
- **Importación detenida/cancelado:** devolución, rechazo, cancelación o detención de importación. Códigos: 60 y 76; el 38 solo se considera cancelado si el texto indica devolución o retorno.
- **Tránsito internacional:** movimiento entre oficinas, tránsito o seguimiento sin llegada confirmada. Códigos: 31, 34, 38, 600, 601, 602, 700, 701 y 702.

### Forma de responder

- Origen/llegada: indica el último punto confirmado y diferencia salida internacional de llegada al país destino.
- Aduana: informa que está en aduana, revisión u observación solo si el evento lo confirma; no afirmes que fue retenido por una causa no registrada.
- Distribución nacional: indica que ya está en la etapa nacional o regional de destino y explica el siguiente paso sin prometer fechas.
- Última milla: indica que está en reparto y no prometas horario exacto.
- Entregado: informa que el sistema lo registra como entregado.
- Importación detenida/cancelado: informa que el movimiento está detenido, cancelado o devuelto según el evento; no prometas continuidad.
- Si preguntan “dónde está” o “hacia dónde va”, menciona estado, destino, oficina y último evento disponibles. Si hay historial, resume la secuencia y luego la ubicación final conocida.
- Si no se encuentra la guía, indica que no fue localizada y pide verificar el código.
- Responde en español, breve, natural y con máximo dos o tres frases.
PROMPT,
        ]);
    }

    public function down(): void {}
};
