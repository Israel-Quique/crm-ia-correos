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

    public function test_sync_follows_all_api_reported_pages_past_page_one_hundred(): void
    {
        config(['services.postal.page_delay_ms' => 0]);
        Http::fake(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
            $page = (int) ($query['page'] ?? 0);

            return Http::response([
                'data' => [[
                    'id' => "package-{$page}",
                    'tracking_number' => $page === 101 ? 'C0001A89911BO' : "TRACK{$page}",
                ]],
                'meta' => ['current_page' => $page, 'last_page' => 101],
            ]);
        });

        $result = app(PostalApiSyncService::class)->sync(
            'nacional',
            1,
            '2026-10-22',
            '2026-10-22',
            true,
        );

        $this->assertSame(101, $result['pages']);
        $this->assertSame(101, $result['reported_last_page']);
        $this->assertSame(101, $result['received']);
        $this->assertDatabaseHas('postal_packages', [
            'source' => 'siop',
            'tracking_number' => 'C0001A89911BO',
        ]);
        Http::assertSentCount(101);
    }

    public function test_sync_fails_if_api_repeats_a_page(): void
    {
        config(['services.postal.page_delay_ms' => 0]);
        Http::fake(['*' => Http::response([
            'data' => [['id' => 'package-1', 'tracking_number' => 'TRACK1']],
            'meta' => ['current_page' => 1, 'last_page' => 3],
        ])]);

        try {
            app(PostalApiSyncService::class)->sync(
                'nacional',
                1,
                '2026-10-22',
                '2026-10-22',
                true,
            );
            $this->fail('A repeated page should stop the synchronization.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('repitio los datos de la pagina 2', $exception->getMessage());
        }

        Http::assertSentCount(2);
    }
}
