<?php

namespace Tests\Unit;

use App\Services\DeliveryExpressQuoteService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryExpressQuoteServiceTest extends TestCase
{
    public function test_exposes_the_active_api_tariff_list_for_management(): void
    {
        Http::fake(['*' => Http::response([
            ['peso1' => '15.00', 'destino' => ['nombre_destino' => 'SANTA CRUZ']],
            ['peso1' => '20.00', 'destino' => ['nombre_destino' => 'LA PAZ']],
        ], 200)]);

        $rates = app(DeliveryExpressQuoteService::class)->rates();

        $this->assertCount(2, $rates);
        $this->assertSame('15.00', $rates[0]['peso1']);
    }

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

        $this->assertSame(17.5, $quote['price']);
        $this->assertSame('BOB', $quote['currency']);
    }

    public function test_matches_the_rate_for_the_requested_origin_and_destination(): void
    {
        Http::fake(['*' => Http::response([
            [
                'peso1' => '15.00',
                'min_weight_kg' => 0,
                'max_weight_kg' => 5,
                'origen' => ['nombre_origen' => 'LA PAZ'],
                'destino' => ['nombre_destino' => 'TARIJA'],
                'servicio_extra' => ['nombre' => 'SERVICIO PUERTA A PUERTA'],
            ],
            [
                'peso1' => '25.00',
                'min_weight_kg' => 0,
                'max_weight_kg' => 5,
                'origen' => ['nombre_origen' => 'SANTA CRUZ'],
                'destino' => ['nombre_destino' => 'TARIJA'],
                'servicio_extra' => ['nombre' => 'SERVICIO PUERTA A PUERTA'],
            ],
        ], 200)]);

        $quote = app(DeliveryExpressQuoteService::class)->quote(
            2,
            'nacional_tarija',
            'puerta_a_puerta',
            'nacional_la_paz',
        );

        $this->assertSame(17.5, $quote['price']);
    }

    public function test_ignora_peso2_peso3_y_peso_extra_y_usa_peso1(): void
    {
        Http::fake(['*' => Http::response([[
            'peso1' => '20.00', 'peso2' => '99.00', 'peso3' => '120.00', 'peso_extra' => '10.00',
            'destino' => ['nombre_destino' => 'SANTA CRUZ'],
        ]], 200)]);

        $quote = app(DeliveryExpressQuoteService::class)->quote(3, 'nacional_santa_cruz');

        $this->assertSame(22.5, $quote['price']);
        $this->assertSame(0, $quote['extra_kilos']);

        $quote = app(DeliveryExpressQuoteService::class)->quote(4, 'nacional_santa_cruz');

        $this->assertSame(32.5, $quote['price']);
        $this->assertSame(1, $quote['extra_kilos']);
    }
}
