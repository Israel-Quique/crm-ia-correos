<?php

namespace App\Livewire;

use App\Models\PostalCampaignTemplate;
use App\Services\WhatsAppCloudApiService;
use Livewire\Component;
use Livewire\WithFileUploads;

class MetaMessageTemplates extends Component
{
    use WithFileUploads;
    public array $metaTemplates = [];
    public ?string $metaError = null;
    public bool $metaLoaded = false;
    public bool $showCreateForm = false;
    public string $name = '';
    public string $language = 'es';
    public string $category = 'UTILITY';
    public string $body = '';
    public ?string $success = null;
    public ?string $submissionError = null;
    public int $createStep = 1;
    public string $templateType = 'PREDEFINED';
    public string $header = '';
    public string $footer = '';
    public string $mediaType = 'NONE';
    public $mediaFile = null;
    public string $search = '';
    public string $statusFilter = '';
    public string $categoryFilter = '';
    public string $localFilter = '';
    public ?string $selectedName = null;

    public function syncMetaTemplates(WhatsAppCloudApiService $whatsapp): void
    {
        $this->metaError = null;
        try {
            $this->metaTemplates = collect($whatsapp->listMessageTemplates())->map(fn (array $template): array => [
                'id' => (string) ($template['id'] ?? ''), 'name' => (string) ($template['name'] ?? ''),
                'language' => (string) ($template['language'] ?? ''), 'status' => (string) ($template['status'] ?? 'UNKNOWN'),
                'category' => (string) ($template['category'] ?? ''),
                'components' => $template['components'] ?? [],
            ])->filter(fn (array $template): bool => $template['name'] !== '')->sortBy('name')->values()->all();
            $this->metaLoaded = true;
        } catch (\Throwable $exception) {
            report($exception); $this->metaTemplates = []; $this->metaLoaded = false;
            $this->metaError = 'No se pudieron consultar las plantillas de Meta: '.$exception->getMessage();
        }
    }

    public function localTemplateExists(string $name): bool
    {
        return PostalCampaignTemplate::query()->where('name', $name)->exists();
    }

    public function selectTemplate(string $name): void
    {
        $this->selectedName = $name;
    }

    public function filteredTemplates(): array
    {
        return collect($this->metaTemplates)->filter(function (array $template): bool {
            $search = strtolower(trim($this->search));
            $matchesSearch = $search === '' || str_contains(strtolower($template['name'].' '.$template['id']), $search);
            $matchesStatus = $this->statusFilter === '' || $template['status'] === $this->statusFilter;
            $matchesCategory = $this->categoryFilter === '' || $template['category'] === $this->categoryFilter;
            $isLocal = $this->localTemplateExists($template['name']);
            $matchesLocal = $this->localFilter === '' || ($this->localFilter === 'yes' ? $isLocal : ! $isLocal);
            return $matchesSearch && $matchesStatus && $matchesCategory && $matchesLocal;
        })->values()->all();
    }

    public function selectedTemplate(): ?array
    {
        return collect($this->metaTemplates)->firstWhere('name', $this->selectedName) ?: ($this->filteredTemplates()[0] ?? null);
    }

    public function translateStatus(string $status): string
    {
        return ['APPROVED' => 'Aprobada', 'PENDING' => 'Pendiente', 'REJECTED' => 'Rechazada', 'PAUSED' => 'Pausada', 'DISABLED' => 'Deshabilitada'][$status] ?? ucfirst(strtolower($status));
    }

    public function translateCategory(string $category): string
    {
        return ['UTILITY' => 'Utilidad', 'MARKETING' => 'Marketing', 'AUTHENTICATION' => 'Autenticación'][$category] ?? ucfirst(strtolower($category));
    }

    public function translateLanguage(string $language): string
    {
        return ['en_US' => 'Inglés (Estados Unidos)', 'en' => 'Inglés', 'es' => 'Español', 'es_MX' => 'Español (México)', 'es_ES' => 'Español (España)', 'pt_BR' => 'Portugués (Brasil)'][$language] ?? $language;
    }

    public function templateBody(?array $template): string
    {
        foreach (($template['components'] ?? []) as $component) {
            if (($component['type'] ?? '') === 'BODY' && ! empty($component['text'])) return (string) $component['text'];
        }
        return 'Esta plantilla no contiene un mensaje de texto disponible.';
    }

    public function templateComponent(?array $template, string $type): ?array
    {
        foreach (($template['components'] ?? []) as $component) {
            if (($component['type'] ?? '') === $type) return $component;
        }
        return null;
    }

    public function templateButtons(?array $template): array
    {
        return $this->templateComponent($template, 'BUTTONS')['buttons'] ?? [];
    }

    public function createTemplate(WhatsAppCloudApiService $whatsapp): void
    {
        $this->submissionError = null;
        $this->name = strtolower(trim($this->name));
        $data = $this->validate([
            'name' => ['required', 'regex:/^[a-z0-9_]+$/', 'max:512'],
            'language' => ['required', 'string', 'max:20'],
            'category' => ['required', 'in:UTILITY,MARKETING,AUTHENTICATION'],
            'body' => ['required', 'string', 'max:1024'],
            'mediaFile' => [$this->mediaType === 'IMAGE' ? 'required' : 'nullable', 'image', 'max:5120'],
        ]);
        try {
            $headerHandle = $this->header;
            if ($this->mediaType === 'IMAGE') {
                if (! $this->mediaFile) throw new \RuntimeException('Selecciona una imagen antes de enviar la plantilla.');
                $headerHandle = $whatsapp->uploadTemplateMedia($this->mediaFile->getRealPath(), $this->mediaFile->getMimeType());
            }
            $whatsapp->createMessageTemplate($data['name'], $data['language'], $data['category'], $data['body'], $headerHandle, $this->footer, $this->mediaType);
            $this->success = 'Solicitud enviada a Meta. Quedará pendiente de revisión.';
            $this->showCreateForm = false;
            $this->reset('name', 'body');
            $this->syncMetaTemplates($whatsapp);
        } catch (\Throwable $exception) {
            report($exception);
            $this->submissionError = 'Meta no aceptó la solicitud: '.$exception->getMessage();
            $this->createStep = 3;
            $this->addError('name', $this->submissionError);
        }
    }

    public function nextCreateStep(): void
    {
        if ($this->createStep === 2) $this->name = strtolower(trim($this->name));
        $this->validate($this->createStep === 1
            ? ['category' => ['required', 'in:UTILITY,MARKETING,AUTHENTICATION']]
            : ['name' => ['required', 'regex:/^[a-z0-9_]+$/', 'max:512'], 'language' => ['required', 'string', 'max:20'], 'body' => ['required', 'string', 'max:1024']]);
        $this->createStep = min(3, $this->createStep + 1);
    }

    public function previousCreateStep(): void
    {
        $this->createStep = max(1, $this->createStep - 1);
    }

    public function openCreateWizard(): void
    {
        $this->resetValidation();
        $this->createStep = 1;
        $this->showCreateForm = true;
    }

    public function render() { return view('livewire.meta-message-templates-fresh'); }
}
