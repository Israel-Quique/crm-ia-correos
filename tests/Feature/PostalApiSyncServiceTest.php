<?php

namespace Tests\Feature;

use App\Models\PostalPackage;
use App\Services\PostalApiSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostalApiSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_sync_skips_a_range_with_local_data_for_every_day(): void
    {
        foreach (range(22, 30) as $day) {
            PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => "package-{$day}",
                'tracking_number' => "TRACK{$day}",
                'updated_at_api' => "2026-10-{$day} 12:00:00",
            ]);
        }

        Http::fake();

        $result = app(PostalApiSyncService::class)->sync(
            'nacional',
            1,
            '2026-10-22',
            '2026-10-30',
        );

        $this->assertTrue($result['skipped']);
        $this->assertSame(0, $result['received']);
        Http::assertNothingSent();
    }

    public function test_manual_sync_can_be_forced_when_local_data_exists(): void
    {
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'package-1',
            'tracking_number' => 'TRACK1',
            'updated_at_api' => '2026-10-22 12:00:00',
        ]);
        Http::fake(['*' => Http::response(['data' => []])]);

        $result = app(PostalApiSyncService::class)->sync(
            'nacional',
            1,
            '2026-10-22',
            '2026-10-22',
            true,
        );

        $this->assertFalse($result['skipped'] ?? false);
        Http::assertSentCount(1);
    }

    public function test_sync_stops_when_api_reports_the_last_page(): void
    {
        Http::fake(['*' => Http::response([
            'data' => array_fill(0, 200, ['tracking_number' => '']),
            'meta' => ['current_page' => 1, 'last_page' => 1],
        ])]);

        $result = app(PostalApiSyncService::class)->sync(
            'nacional',
            1,
            '2026-10-22',
            '2026-10-22',
            true,
        );

        $this->assertSame(200, $result['received']);
        Http::assertSentCount(1);
    }
}
