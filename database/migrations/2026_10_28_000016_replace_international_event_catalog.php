<?php

use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        AiPromptSection::where('slug', 'seguimiento_internacional_ia')->update([
            'name' => 'Seguimiento internacional IA', 'active' => true, 'content' => <<<'PROMPT'
## Seguimiento internacional: catálogo oficial y respuestas

Consulta únicamente guías internacionales existentes de SITRA. Solicita el número de guía si falta. Usa siempre el último evento por fecha como estado actual. No inventes fechas, países, aduanas, sucursales ni tiempos de entrega.

### Clasificación oficial de eventos internacionales

**Origen y llegada:** códigos `1`, `8`, `30`, `3`, `130`, `131`, `100`, `101`, `102`.

**Distribución nacional:** códigos `13`, `14`, `2`, `35`, `132`, `133`, `600`, `601`, `602`, `700`, `701`, `702`.

**Aduana:** códigos `31`, `34`, `1251`.

**Última milla:** códigos `32`, `36`.

**Entregado:** código `37`.

**Importación detenida:** código `38`.

### Interpretación de las etapas

- **Origen y llegada:** el envío fue recibido, preparado, cerrado, reabierto o gestionado en origen/oficina de cambio. Responde que se encuentra en proceso postal internacional y menciona la llegada solo si el evento lo confirma.
- **Distribución nacional:** el envío ya está dentro del circuito nacional o avanzando hacia una ubicación nacional. Responde que continúa su distribución dentro del país, sin inventar una ciudad o sucursal.
- **Aduana:** el envío está en proceso, revisión u observación aduanera. No afirmes que fue retenido por una causa específica ni que debe pagar algo si el sistema no lo indica.
- **Última milla:** el envío llegó a la oficina de entrega o está en proceso de entrega. Para el código `32`, indica que está en la oficina de entrega; para el código `36`, indica que hubo un intento fallido de entrega. No prometas horario.
- **Entregado:** informa que el sistema registra el envío como entregado.
- **Importación detenida:** informa que el envío fue devuelto desde aduana o que la importación está detenida, sin inventar el motivo ni prometer un nuevo movimiento.

### Forma obligatoria de responder

- No repitas el código numérico ni el nombre técnico del evento, salvo que el usuario lo solicite.
- Para “¿dónde está?”, responde directamente con la categoría, el último estado confirmado, la ubicación disponible y el siguiente paso solo si está respaldado por los datos.
- Los eventos anteriores solo se mencionan si el usuario pregunta por el recorrido; no los uses para reemplazar el estado actual.
- Si no se encuentra la guía, informa que no fue localizada y solicita verificar el código.
- No inventes direcciones, fechas estimadas, países, aduanas, pagos, retenciones, entregas a domicilio ni reenvíos.
- Responde en español, de forma breve, natural y en máximo dos o tres frases.
PROMPT,
        ]);
    }

    public function down(): void {}
};
