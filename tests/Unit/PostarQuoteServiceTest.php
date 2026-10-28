<?php

namespace Tests\Unit;

use App\Services\PostarQuoteService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostarQuoteServiceTest extends TestCase
{
    public function test_cotiza_ems_nacional_y_envia_el_formato_correcto_a_postar(): void
    {
        Http::fake([
            'https://postar.correos.gob.bo/api/calcular' => Http::response([
                'success' => true,
                'tarifa' => 37.50,
                'categoria' => 'EMS NAT',
                'destino' => 'nacional_santa_cruz',
            ], 200),
        ]);

        $service = app(PostarQuoteService::class);
        $quote = $service->quote('EMS NAT', $service->destinationCode('Santa Cruz'), 2);

        $this->assertSame('EMS NAT', $quote['category']);
        $this->assertSame('nacional_santa_cruz', $quote['destination']);
        $this->assertSame(2.0, $quote['weight']);
        $this->assertSame(37.50, $quote['tariff']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://postar.correos.gob.bo/api/calcular'
                && $request->method() === 'POST'
                && $request['categoria'] === 'EMS NAT'
                && $request['destino'] === 'nacional_santa_cruz'
                && (float) $request['peso'] === 2.0;
        });
    }

    public function test_reconoce_el_ultimo_destino_de_una_ruta(): void
    {
        $service = app(PostarQuoteService::class);

        $this->assertSame(
            'nacional_santa_cruz',
            $service->destinationCodeFromText('enviar desde La Paz hasta Santa Cruz mediante EMS'),
        );
    }

    public function test_toma_santa_cruz_como_destino_y_no_la_paz_como_origen(): void
    {
        $service = app(PostarQuoteService::class);

        $this->assertSame(
            'nacional_santa_cruz',
            $service->destinationCodeFromText('enviar un paquete desde La Paz a Santa Cruz, peso 7 kilos'),
        );
    }
}
