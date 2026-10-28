<?php
namespace App\Services;
use App\Models\ApiRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiRecordSyncService
{
    public function sync(string $source, ?string $url = null): array
    {
        $url ??= config('services.crm_api.url');
        if (! $url) throw new RuntimeException('Configura CRM_API_URL antes de sincronizar.');
        $request = Http::timeout((int) config('services.crm_api.timeout', 60))->acceptJson();
        if ($token = config('services.crm_api.token')) $request = $request->withToken($token);
        $json = $request->get($url, ['limit' => 1000, 'per_page' => 1000, 'order' => 'updated_at:desc'])->throw()->json();
        $items = data_get($json, 'data', $json);
        if (! is_array($items)) throw new RuntimeException('La API no devolvió una lista de registros.');
        $created = $updated = 0;
        foreach (array_slice($items, 0, 1000) as $item) {
            if (! is_array($item)) continue;
            $externalId = (string) ($item['id'] ?? $item['uuid'] ?? $item['external_id'] ?? '');
            if ($externalId === '') continue;
            $record = ApiRecord::firstOrNew(['source' => $source, 'external_id' => $externalId]);
            $wasNew = ! $record->exists;
            $record->fill(['status' => $item['status'] ?? $item['state'] ?? null, 'payload' => $item, 'api_updated_at' => isset($item['updated_at']) ? Carbon::parse($item['updated_at']) : null, 'last_synced_at' => now()]);
            $record->save();
            $wasNew ? $created++ : $updated++;
        }
        return ['received' => count($items), 'created' => $created, 'updated' => $updated];
    }
}
