<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        $messages = [
            ['general','event','saludo','Saludo','¡Hola! 👋 Soy el asistente de Correos de Bolivia. ¿Deseas cotizar un envío o rastrear un paquete? 😊'],
            ['nacional','event','recibido_origen','Recibido en origen','📦 La guía {tracking_number} fue recibida en la sucursal de origen. Continúa su despacho hacia el destino.'],
            ['nacional','event','en_camino','En camino','🚚 La guía {tracking_number} está en camino hacia su destino.'],
            ['nacional','event','recibido_destino','Recibido en destino','📍 La guía {tracking_number} ya llegó a la oficina de destino y continúa el proceso de entrega.'],
            ['nacional','event','cartero','Asignado a cartero','🚚 La guía {tracking_number} fue asignada a un cartero para su entrega.'],
            ['nacional','event','entregado','Entregado','✅ La guía {tracking_number} figura como entregada según el último evento registrado.'],
            ['nacional','event','cancelado','Devuelto o cancelado','⚠️ La guía {tracking_number} figura como devuelta o cancelada. El sistema no registra un nuevo despacho posterior.'],
            ['internacional','event','origen_llegada','Origen o llegada internacional','📦 La guía {tracking_number} continúa en el proceso internacional de recepción, clasificación o despacho.'],
            ['internacional','event','distribucion_nacional','Distribución nacional','🚚 La guía {tracking_number} ingresó al proceso de distribución nacional hacia su destino.'],
            ['internacional','event','aduana','En aduana','🛃 La guía {tracking_number} se encuentra en proceso de aduana.'],
            ['internacional','event','ultima_milla','Última milla','📍 La guía {tracking_number} llegó a la oficina de entrega y continúa en la etapa final de distribución.'],
            ['internacional','event','entregado','Entregado internacional','✅ La guía {tracking_number} figura como entregada según el último evento internacional registrado.'],
            ['internacional','event','importacion_detenida','Devuelto desde aduana','⚠️ La guía {tracking_number} fue devuelta desde la aduana del país de destino hacia su origen. El motivo solo se informa si está registrado.'],
            ['general','quote','EMS NAT','Cotización EMS nacional','Para cotizar EMS nacional necesito origen, destino y peso. El precio se mostrará únicamente cuando exista una tarifa verificada.'],
            ['general','quote','EMS INT','Cotización EMS internacional','Para cotizar EMS internacional necesito país de origen, país de destino y peso. El precio se mostrará únicamente cuando exista una tarifa verificada.'],
            ['general','quote','Delivery Express','Cotización Delivery Express','Delivery Express es nacional, admite hasta 5 kg y requiere una modalidad válida: puerta a puerta, puerta a ventanilla o ventanilla a ventanilla.'],
        ];
        foreach ($messages as [$scope,$type,$key,$name,$template]) DB::table('automatic_responses')->updateOrInsert(['scope'=>$scope,'type'=>$type,'key'=>$key], ['user_id'=>null,'name'=>$name,'template'=>$template,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void { DB::table('automatic_responses')->whereNull('user_id')->delete(); }
};
