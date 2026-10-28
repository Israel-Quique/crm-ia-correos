<?php

namespace Tests\Unit;

use App\Services\DeliveryExpressQuoteService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryExpressQuoteServiceTest extends TestCase
{
    public function test_interpreta_el_formato_real_del_tarifario(): void
    {
        Http::fake([
            '*' => Http::response([[
                'peso1' => '15.00',
                'origen' => ['nombre_origen' => 'LA PAZ'],
                'destino' => ['nombre_destino' => 'SANTA CRUZ'],
                'servicio_extra' => ['nombre' => 'SERVICIO PUERTA A PUERTA'],
            ]], 200),
        ]);

        $quote = app(DeliveryExpressQuoteService::class)->quote(2, 'nacional_santa_cruz');

        $this->assertSame(15.0, $quote['price']);
        $this->assertSame('BOB', $quote['currency']);
    }

    public function test_ignora_peso2_peso3_y_peso_extra_y_usa_peso1(): void
    {
        Http::fake(['*' => Http::response([[
            'peso1' => '20.00', 'peso2' => '99.00', 'peso3' => '120.00', 'peso_extra' => '10.00',
            'destino' => ['nombre_destino' => 'SANTA CRUZ'],
        ]], 200)]);

        $quote = app(DeliveryExpressQuoteService::class)->quote(3, 'nacional_santa_cruz');

        $this->assertSame(20.0, $quote['price']);
    }
}
