<?php

namespace Tests\Unit;

use App\Models\PostalEvent;
use App\Models\PostalPackage;
use App\Services\PostalEventPresenter;
use Tests\TestCase;

class PostalEventPresenterTest extends TestCase
{
    public function test_national_event_codes_are_explained_without_exposing_assignment_names(): void
    {
        $presenter = new PostalEventPresenter;
        $event = new PostalEvent([
            'source_event' => '4409',
            'event_name' => 'Asignado a CARTERO por Ana de prueba a Bruno de prueba.',
        ]);

        $this->assertSame(
            'El paquete fue asignado a un cartero para intentar la entrega.',
            $presenter->describeEvent('nacional', $event),
        );
        $this->assertSame(
            'Se intentó entregar el paquete, pero no se pudo completar la entrega.',
            $presenter->describe('nacional', '315', 'Intento fallido'),
        );
        $this->assertSame(
            'El paquete fue asignado a reparto o hubo un intento de entrega.',
            $presenter->describe('nacional', null, 'En reparto'),
        );
        $this->assertSame(
            'Cartero',
            $presenter->category('nacional', '4409', 'Asignado a CARTERO por Ana de prueba.'),
        );
        $this->assertSame(
            'Recibido Destino',
            $presenter->category('nacional', '312', 'Paquete recibido en oficina de entrega'),
        );
    }

    public function test_international_event_codes_have_scope_specific_explanations(): void
    {
        $presenter = new PostalEventPresenter;

        $this->assertSame(
            'El sistema marca el envío como entregado en el país de destino.',
            $presenter->describe('internacional', '37', 'Entregado'),
        );
        $this->assertSame(
            'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            $presenter->describe('nacional', '168', 'Tránsito internacional'),
        );
        $this->assertSame(
            'Aduana',
            $presenter->category('internacional', '31', 'Enviado a Aduana'),
        );
    }

    public function test_event_codes_are_classified_only_in_their_own_postal_scope(): void
    {
        $presenter = new PostalEventPresenter;

        $this->assertSame('En Camino', $presenter->category('nacional', '168', ''));
        $this->assertSame('Sin movimientos o sin clasificación confiable', $presenter->category('internacional', '168', ''));
        $this->assertSame('Entregado', $presenter->category('internacional', '37', ''));
        $this->assertSame('Sin movimientos o sin clasificación confiable', $presenter->category('nacional', '37', ''));
    }

    public function test_official_event_catalogs_use_scope_specific_categories(): void
    {
        $presenter = new PostalEventPresenter;
        $nationalCodes = [
            '295' => 'Recibido Origen',
            '297' => 'Recibido Origen',
            '168' => 'En Camino',
            '312' => 'Recibido Destino',
            '4409' => 'Cartero',
            '316' => 'Entregado',
            '315' => 'Cartero',
            '3345' => 'Cancelado',
            '4402' => 'Cancelado',
            '4404' => 'Cancelado',
            '4405' => 'Cancelado',
            '2239' => 'En Camino',
            '2240' => 'En Camino',
            '2241' => 'En Camino',
            '240' => 'En Camino',
            '318' => 'Recibido Origen',
            '4400' => 'En Camino',
            '4401' => 'En Camino',
        ];
        $internationalCodes = [
            '1' => 'Origen y llegada',
            '8' => 'Origen y llegada',
            '30' => 'Origen y llegada',
            '3' => 'Origen y llegada',
            '130' => 'Origen y llegada',
            '131' => 'Origen y llegada',
            '100' => 'Origen y llegada',
            '101' => 'Origen y llegada',
            '102' => 'Origen y llegada',
            '13' => 'Distribución nacional',
            '14' => 'Distribución nacional',
            '2' => 'Distribución nacional',
            '35' => 'Distribución nacional',
            '132' => 'Distribución nacional',
            '133' => 'Distribución nacional',
            '600' => 'Distribución nacional',
            '601' => 'Distribución nacional',
            '602' => 'Distribución nacional',
            '700' => 'Distribución nacional',
            '701' => 'Distribución nacional',
            '702' => 'Distribución nacional',
            '31' => 'Aduana',
            '34' => 'Aduana',
            '1251' => 'Aduana',
            '32' => 'Última milla',
            '36' => 'Última milla',
            '37' => 'Entregado',
            '38' => 'Importación detenida',
        ];

        foreach ($nationalCodes as $code => $category) {
            $this->assertSame($category, $presenter->category('nacional', $code, ''));
        }
        foreach ($internationalCodes as $code => $category) {
            $this->assertSame($category, $presenter->category('internacional', $code, ''));
        }
        $this->assertSame(
            'Sin movimientos o sin clasificación confiable',
            $presenter->category('internacional', '1252', 'Aduana'),
        );
    }

    public function test_national_code_297_uses_event_text_and_prior_transit_context(): void
    {
        $presenter = new PostalEventPresenter;
        $this->assertSame('En Camino', $presenter->category('nacional', '297', 'Paquete recibido en oficina origen de tránsito'));
        $this->assertSame('Recibido Destino', $presenter->category('nacional', '297', 'Paquete recibido en oficina de destino'));

        $package = new PostalPackage(['scope' => 'nacional']);
        $package->setRelation('events', collect([
            new PostalEvent(['source_event' => '168', 'event_at' => '2025-01-01 10:00:00']),
            new PostalEvent(['source_event' => '297', 'event_name' => 'Actualización de recibo', 'event_at' => '2025-01-02 10:00:00']),
        ]));

        $this->assertSame('Recibido Destino', $presenter->currentCategory($package));
        $this->assertSame('Recibido Destino', $presenter->categoryForEvent($package, $package->events->last()));
    }

    public function test_customs_classification_is_available_only_for_international_events(): void
    {
        $presenter = new PostalEventPresenter;

        $this->assertSame(
            'Sin movimientos o sin clasificación confiable',
            $presenter->category('nacional', null, 'En clasificación especial'),
        );
        $this->assertSame(
            'Sin movimientos o sin clasificación confiable',
            $presenter->category('nacional', null, 'Enviado a Aduana'),
        );
        $this->assertSame(
            'Aduana',
            $presenter->category('internacional', null, 'Enviado a Aduana'),
        );
    }

    public function test_latest_event_is_used_when_current_status_says_no_event(): void
    {
        $package = new PostalPackage([
            'scope' => 'nacional',
            'current_event' => 'Sin evento',
        ]);
        $package->setRelation('events', collect([
            new PostalEvent([
                'source_event' => '315',
                'event_name' => 'Intento fallido',
                'event_at' => '2025-01-01 10:00:00',
            ]),
            new PostalEvent([
                'source_event' => '4409',
                'event_name' => 'Asignado a CARTERO por Ana de prueba.',
                'event_at' => '2025-01-02 10:00:00',
            ]),
        ]));

        $this->assertSame(
            'El paquete fue asignado a un cartero para intentar la entrega.',
            (new PostalEventPresenter)->currentStatus($package),
        );
        $this->assertSame(
            'Cartero',
            (new PostalEventPresenter)->currentCategory($package),
        );
    }

    public function test_event_code_takes_priority_over_a_conflicting_current_status(): void
    {
        $package = new PostalPackage([
            'scope' => 'nacional',
            'current_event' => 'Entregado',
        ]);
        $package->setRelation('latestEvent', new PostalEvent([
            'source_event' => '4409',
            'event_name' => 'Asignado a CARTERO por Ana de prueba.',
            'event_at' => '2025-01-02 10:00:00',
        ]));

        $presenter = new PostalEventPresenter;
        $this->assertSame('Cartero', $presenter->currentCategory($package));
        $this->assertSame(
            'El paquete fue asignado a un cartero para intentar la entrega.',
            $presenter->currentStatus($package),
        );
    }

    public function test_unrecognized_events_are_not_given_an_invented_meaning(): void
    {
        $description = 'Process X9 assigned to employee Ana de prueba';
        $presented = (new PostalEventPresenter)->describe('nacional', 'unknown', $description);

        $this->assertSame(
            'El detalle disponible no permite clasificar este movimiento con confianza.',
            $presented,
        );
        $this->assertStringNotContainsString('Ana de prueba', $presented);
        $this->assertSame(
            'Sin movimientos o sin clasificación confiable',
            (new PostalEventPresenter)->category('nacional', '999', 'Entregado'),
        );
        $this->assertSame(
            'El detalle disponible no permite clasificar este movimiento con confianza.',
            (new PostalEventPresenter)->describe('nacional', '999', 'Entregado'),
        );
        $this->assertSame(
            'Sin movimientos o sin clasificación confiable',
            (new PostalEventPresenter)->category('nacional', null, ''),
        );
    }

    public function test_categories_are_scoped_and_returned_in_the_requested_order(): void
    {
        $this->assertSame([
            'Recibido Origen',
            'En Camino',
            'Recibido Destino',
            'Cartero',
            'Ventanilla',
            'Entregado',
            'Cancelado',
            'Sin movimientos o sin clasificación confiable',
        ], (new PostalEventPresenter)->categories('nacional'));
        $this->assertSame([
            'Origen y llegada',
            'Distribución nacional',
            'Aduana',
            'Última milla',
            'Ventanilla',
            'Entregado',
            'Importación detenida',
            'Sin movimientos o sin clasificación confiable',
        ], (new PostalEventPresenter)->categories('internacional'));
    }
}
