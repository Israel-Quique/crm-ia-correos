<?php

namespace App\Livewire;

use App\Models\PostalCampaignTemplate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PostalCampaignTemplates extends Component
{
    private const NATIONAL_CATEGORIES = [
        'origin' => ['label' => 'Origen', 'event' => 'Recibido Origen', 'color' => 'blue'],
        'transit' => ['label' => 'Camino', 'event' => 'En Camino', 'color' => 'purple'],
        'pickup' => ['label' => 'Ventanilla', 'event' => 'Ventanilla para recojo', 'color' => 'orange'],
        'courier' => ['label' => 'Cartero', 'event' => 'Cartero', 'color' => 'purple'],
    ];

    private const INTERNATIONAL_CATEGORIES = [
        'pickup' => ['label' => 'Ventanilla', 'event' => 'Ventanilla para recojo', 'color' => 'orange'],
        'international' => ['label' => 'Internacional', 'event' => 'Actualización internacional', 'color' => 'blue'],
    ];

    public string $scope = 'nacional';
    public string $selectedCategory = '';
    public bool $isNewMessage = false;
    public string $name = '';
    public string $template = '';
    public ?string $feedback = null;

    public function mount(string $scope = 'nacional'): void
    {
        $this->scope = in_array($scope, ['nacional', 'internacional'], true) ? $scope : 'nacional';
        $this->selectedCategory = array_key_first($this->categories());
        $this->loadSelectedCategory();
    }

    public function categories(): array
    {
        return $this->scope === 'internacional'
            ? self::INTERNATIONAL_CATEGORIES
            : self::NATIONAL_CATEGORIES;
    }

    public function updatedSelectedCategory(): void
    {
        $this->isNewMessage = false;
        $this->loadSelectedCategory();
    }

    public function startNewMessage(): void
    {
        $this->resetValidation();
        $this->isNewMessage = true;
        $this->name = '';
        $this->template = '';
    }

    public function appendTag(string $tag): void
    {
        if (! in_array($tag, ['{recipient}', '{tracking_number}', '{current_event}', '{campaign_name}', '{destination_branch}'], true)) {
            return;
        }

        $this->template .= $tag;
    }

    private function loadSelectedCategory(): void
    {
        $savedTemplate = PostalCampaignTemplate::query()
            ->where('scope', $this->scope)
            ->where('category', $this->selectedCategory)
            ->first();

        if (! $savedTemplate && $this->scope === 'internacional' && $this->selectedCategory === 'international') {
            $savedTemplate = PostalCampaignTemplate::query()
                ->where('scope', 'internacional')
                ->where('category', 'other')
                ->first();
        }

        $this->name = $savedTemplate?->name ?? '';
        $this->template = $savedTemplate?->template ?? '';
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('postal_campaign_templates', 'name')
                    ->where('scope', $this->scope)
                    ->ignore(
                        PostalCampaignTemplate::query()
                            ->where('scope', $this->scope)
                            ->where('category', $this->selectedCategory)
                            ->value('id'),
                    ),
            ],
            'template' => ['required', 'string', 'max:2000'],
            'selectedCategory' => ['required', Rule::in(array_keys($this->categories()))],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        PostalCampaignTemplate::updateOrCreate(
            ['scope' => $this->scope, 'category' => $this->selectedCategory],
            [
                'name' => trim($validated['name']),
                'template' => trim($validated['template']),
                'user_id' => auth()->id(),
            ],
        );

        $this->isNewMessage = false;
        $this->feedback = 'Mensaje guardado para la categoría '.$this->categories()[$this->selectedCategory]['label'].'.';
    }

    public function render()
    {
        return view('livewire.postal-campaign-templates', [
            'categories' => $this->categories(),
        ]);
    }
}
