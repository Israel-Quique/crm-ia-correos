<?php

namespace App\Livewire;

use App\Models\AutomaticResponse;
use Livewire\Component;
use Livewire\WithPagination;

class AutomaticResponses extends Component
{
    use WithPagination;

    private const EVENT_KEYS = [
        'general' => ['saludo'],
        'nacional' => [
            'recibido_origen',
            'en_camino',
            'recibido_destino',
            'ventanilla',
            'cartero',
            'entregado',
            'cancelado',
        ],
        'internacional' => [
            'origen_llegada',
            'distribucion_nacional',
            'aduana',
            'ultima_milla',
            'cartero',
            'ventanilla',
            'entregado',
            'importacion_detenida',
        ],
    ];

    private const QUOTE_KEYS = ['cotizacion_postal', 'EMS NAT', 'EMS INT', 'Delivery Express'];
    private const TRACKING_KEYS = ['rastreo_postal', 'rastreo_nacional', 'rastreo_internacional'];

    public int $perPage = 8;

    public string $scopeFilter = 'all';
    public string $typeFilter = 'all';
    public string $keyFilter = 'all';
    public string $statusFilter = 'all';

    public string $scope = 'general';
    public string $type = 'event';
    public string $key = '';
    public string $name = '';
    public string $template = '';
    public string $triggerPhrases = '';
    public bool $active = true;
    public ?int $editingResponseId = null;

    protected array $rules = [
        'scope' => 'required|in:general,nacional,internacional',
        'type' => 'required|in:event,quote,tracking',
        'key' => 'required|string|max:80',
        'name' => 'required|string|max:120',
        'template' => 'required|string|max:1000',
        'triggerPhrases' => 'nullable|string|max:1000',
    ];

    public function startCreate(): void
    {
        $this->resetEditor();
        $this->resetValidation();
        $this->dispatch('automatic-response-open');
    }

    public function edit(int $id): void
    {
        $response = AutomaticResponse::findOrFail($id);

        $this->editingResponseId = $response->id;
        $this->scope = $response->scope;
        $this->type = $response->type;
        $this->key = $response->key;
        $this->name = $response->name;
        $this->template = $response->template;
        $this->triggerPhrases = implode(', ', $response->trigger_phrases ?? []);
        $this->active = $response->active;

        $this->resetValidation();
        $this->dispatch('automatic-response-open');
    }

    public function keySuggestions(): array
    {
        $keys = array_merge(self::QUOTE_KEYS, self::TRACKING_KEYS);

        foreach (self::EVENT_KEYS as $scopeKeys) {
            $keys = array_merge($keys, $scopeKeys);
        }

        return array_values(array_unique($keys));
    }

    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'scope' => $validated['scope'],
            'type' => $validated['type'],
            'key' => trim($validated['key']),
            'name' => trim($validated['name']),
            'template' => trim($validated['template']),
            'trigger_phrases' => $this->parseTriggers($validated['triggerPhrases'] ?? ''),
            'active' => $this->active,
        ];

        if ($this->editingResponseId !== null) {
            $response = AutomaticResponse::findOrFail($this->editingResponseId);
            $response->update($attributes);

            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'Respuesta editada',
                'category' => 'respuestas',
                'description' => 'Se actualizó la respuesta: '.$response->name,
                'ip_address' => request()->ip(),
            ]);

            session()->flash('automaticResponseSaved', 'Respuesta automática actualizada.');
        } else {
            AutomaticResponse::create($attributes + ['user_id' => auth()->id()]);
            session()->flash('automaticResponseSaved', 'Respuesta automática guardada.');
        }

        $this->resetEditor();
        $this->resetPage();
        $this->dispatch('automatic-response-saved');
    }

    private function resetEditor(): void
    {
        $this->editingResponseId = null;
        $this->scope = 'general';
        $this->type = 'event';
        $this->reset(['key', 'name', 'template', 'triggerPhrases']);
        $this->active = true;
    }

    private function parseTriggers(string $value): array
    {
        return array_values(array_filter(
            array_map(fn ($item) => trim($item), preg_split('/[,\n]+/', $value)),
            fn ($item) => $item !== '',
        ));
    }

    public function toggle(int $id): void
    {
        $response = AutomaticResponse::findOrFail($id);
        $response->update(['active' => ! $response->active]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Respuesta editada',
            'category' => 'respuestas',
            'description' => 'Se '.($response->active ? 'activó' : 'desactivó').' la respuesta: '.$response->name,
            'ip_address' => request()->ip(),
        ]);

        $this->resetPage();
    }

    public function delete(int $id): void
    {
        AutomaticResponse::whereKey($id)->delete();
        $this->resetPage();
    }

    public function updatedScopeFilter(): void
    {
        $this->resetPage();
        $this->resetKeyFilterIfUnavailable();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
        $this->resetKeyFilterIfUnavailable();
    }

    public function updatedKeyFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->scopeFilter = 'all';
        $this->typeFilter = 'all';
        $this->keyFilter = 'all';
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    private function resetKeyFilterIfUnavailable(): void
    {
        if ($this->keyFilter !== 'all' && ! $this->availableKeys()->contains($this->keyFilter)) {
            $this->keyFilter = 'all';
        }
    }

    private function availableKeys()
    {
        $query = AutomaticResponse::query()->select('key')->distinct();

        if ($this->scopeFilter !== 'all') {
            $query->where('scope', $this->scopeFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        return $query->orderBy('key')->pluck('key');
    }

    public function render()
    {
        $query = AutomaticResponse::latest()->orderByDesc('id');

        if ($this->scopeFilter !== 'all') {
            $query->where('scope', $this->scopeFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        if ($this->keyFilter !== 'all') {
            $query->where('key', $this->keyFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('active', false);
        }

        return view('livewire.automatic-responses', [
            'responses' => $query->paginate($this->perPage),
            'keys' => $this->availableKeys(),
            'keySuggestions' => $this->keySuggestions(),
        ]);
    }
}
