<?php

namespace Tests\Feature;

use App\Models\PostalPackage;
use App\Services\CdsDeclarationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CdsDeclarationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_sender_recipient_and_phone_from_cds(): void
    {
        PostalPackage::create([
            'source' => 'sitra', 'scope' => 'internacional',
            'external_id' => 'test-ee006280595ar',
            'tracking_number' => 'EE006280595AR', 'phone' => '+59170000000',
        ]);

        Http::fake([
            '*' => Http::response(['data' => [[
                'codigo' => 'EE006280595AR',
                'declaracion_cds' => ['objetos' => [['declaraciones' => [[
                    'destinatario_declarado' => ['nombre' => 'Joaquín Escobar Cabezas', 'telefono' => '+59171234567'],
                    'campos' => ['SNm' => 'Rimmele Mabel Ester', 'RNm' => 'Joaquín Escobar Cabezas'],
                ]]]]],
            ]]], 200),
        ]);

        $result = app(CdsDeclarationSyncService::class)->sync();
        $package = PostalPackage::where('tracking_number', 'EE006280595AR')->firstOrFail();

        $this->assertSame(1, $result['updated']);
        $this->assertSame('Rimmele Mabel Ester', $package->cds_sender);
        $this->assertSame('Joaquín Escobar Cabezas', $package->cds_recipient);
        $this->assertSame('+59171234567', $package->cds_phone);
        $this->assertNotNull($package->cds_synced_at);
    }

    public function test_scoped_sync_updates_the_matching_international_package_only(): void
    {
        $tracking = 'RR123456789XX';
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'national-duplicate-tracking',
            'tracking_number' => $tracking,
            'phone' => '+59170000001',
        ]);
        $international = PostalPackage::create([
            'source' => 'sitra',
            'scope' => 'internacional',
            'external_id' => 'international-duplicate-tracking',
            'tracking_number' => $tracking,
            'phone' => '+59170000002',
        ]);
        Http::fake([
            '*' => Http::response(['data' => [[
                'codigo' => $tracking,
                'declaracion_cds' => ['objetos' => [['declaraciones' => [[
                    'destinatario_declarado' => ['nombre' => 'Destinatario internacional'],
                ]]]]],
            ]]], 200),
        ]);

        app(CdsDeclarationSyncService::class)->sync([$tracking], 'internacional');

        $international->refresh();
        $this->assertSame('Destinatario internacional', $international->cds_recipient);
        $this->assertDatabaseHas('postal_packages', [
            'source' => 'siop',
            'tracking_number' => $tracking,
            'cds_recipient' => null,
        ]);
    }
}
