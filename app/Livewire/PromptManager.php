<?php

namespace App\Livewire;

use App\Models\AiPromptSection;
use Livewire\Component;

class PromptManager extends Component
{
    public string $mode = 'cotizaciones';
    public string $content = '';
    public bool $active = true;
    public string $savedMessage = '';

    public function mount(string $aiMode = 'cotizaciones'): void
    {
        $this->mode = in_array($aiMode, ['cotizaciones', 'seguimiento_nacional', 'seguimiento_internacional'], true) ? $aiMode : 'cotizaciones';
        $this->loadPrompt();
    }

    public function loadPrompt(): void
    {
        $slug = $this->slug();
        $prompt = AiPromptSection::where('slug', $slug)->first();
        $this->content = $prompt?->content ?? '';
        if (str_contains($this->content, '# Instrucciones del agente postal')) $this->content = '';
        $this->active = $prompt?->active ?? true;
        $this->savedMessage = '';
    }

    public function save(): void
    {
        $this->validate(['content' => ['required', 'string', 'max:30000']]);
        AiPromptSection::updateOrCreate(
            ['slug' => $this->slug()],
            ['name' => $this->title(), 'content' => trim($this->content), 'active' => $this->active],
        );
        $this->savedMessage = 'Prompt guardado correctamente.';
    }

    public function usePreset(): void
    {
        $this->loadPrompt();
        $this->savedMessage = 'Configuración cargada desde la base de datos.';
    }

    private function slug(): string
    {
        return match ($this->mode) {
            'seguimiento_nacional' => 'seguimiento_nacional_ia',
            'seguimiento_internacional' => 'seguimiento_internacional_ia',
            default => 'cotizaciones_ia',
        };
    }

    private function title(): string
    {
        return match ($this->mode) {
            'seguimiento_nacional' => 'Seguimiento nacional IA',
            'seguimiento_internacional' => 'Seguimiento internacional IA',
            default => 'Cotizaciones IA',
        };
    }

    public function render()
    {
        return view('livewire.prompt-manager', ['title' => $this->title()]);
    }
}
