<?php

namespace Tests\Unit;

use App\Livewire\PostalCampaignManager;
use App\Models\PostalEvent;
use App\Models\PostalPackage;
use Tests\TestCase;

class PostalCampaignClassificationTest extends TestCase
{
    public function test_it_classifies_courier_and_pickup_statuses_for_national_packages(): void
    {
        $manager = new PostalCampaignManager();
        $manager->scope = 'nacional';

        $this->assertSame(
            'Cartero',
            $manager->classifyPackage($this->packageWithEvent('4409', 'Asignado a cartero')),
        );
        $this->assertSame(
            'Cartero',
            $manager->classifyPackage($this->packageWithEvent('315', 'Intento fallido')),
        );
        $this->assertSame(
            'Ventanilla para recojo',
            $manager->classifyPackage($this->packageWithEvent('312', 'Disponible en oficina')),
        );
        $this->assertSame(
            'Ventanilla para recojo',
            $manager->classifyPackage($this->packageWithEvent('other', 'Paquete listo para recojo en ventanilla')),
        );
    }

    public function test_national_chat_statuses_are_classified_into_origin_transit_and_pickup(): void
    {
        $manager = new PostalCampaignManager();
        $manager->scope = 'nacional';

        $this->assertSame(
            'Recibido Origen',
            $manager->classifyPackage($this->packageWithEvent('other', 'Recibido en origen')),
        );
        $this->assertSame(
            'En Camino',
            $manager->classifyPackage($this->packageWithEvent('other', 'Recibido en origen y despachado hacia Sin destino')),
        );
        $this->assertSame(
            'En Camino',
            $manager->classifyPackage($this->packageWithEvent('other', 'En camino a destino')),
        );
        $this->assertSame(
            'Ventanilla para recojo',
            $manager->classifyPackage($this->packageWithEvent('other', 'Disponible para entrega en oficina')),
        );
    }

    public function test_it_classifies_courier_and_pickup_statuses_for_international_packages(): void
    {
        $manager = new PostalCampaignManager();
        $manager->scope = 'internacional';

        $this->assertSame(
            'Cartero',
            $manager->classifyPackage($this->packageWithEvent('other', 'En reparto por cartero')),
        );
        $this->assertSame(
            'Ventanilla para recojo',
            $manager->classifyPackage($this->packageWithEvent('other', 'Disponible en oficina de entrega')),
        );
        $this->assertSame(
            'Última milla',
            $manager->classifyPackage($this->packageWithEvent('36', 'En distribución')),
        );
    }

    public function test_it_separates_international_arrivals_and_departures_from_event_text(): void
    {
        $manager = new PostalCampaignManager();
        $manager->scope = 'internacional';

        $this->assertSame(
            'Salida de Bolivia',
            $manager->classifyPackage($this->packageWithEvent('1', 'Salida internacional de Bolivia')),
        );
        $this->assertSame(
            'Llegada a Bolivia',
            $manager->classifyPackage($this->packageWithEvent('8', 'Llegada del envío a Bolivia')),
        );
        $this->assertSame(
            'Distribución nacional',
            $manager->classifyPackage($this->packageWithEvent('32', 'Entrada a oficina de destino')),
        );
    }

    private function packageWithEvent(string $code, string $eventName): PostalPackage
    {
        $package = new PostalPackage(['current_event' => $eventName]);
        $package->setRelation('events', collect([
            new PostalEvent([
                'source_event' => $code,
                'event_name' => $eventName,
            ]),
        ]));

        return $package;
    }
}
