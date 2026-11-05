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
        'destination' => ['label' => 'Llegó a destino', 'event' => 'Recibido Destino', 'color' => 'cyan'],
        'pickup' => ['label' => 'Ventanilla', 'event' => 'Ventanilla para recojo', 'color' => 'orange'],
        'courier' => ['label' => 'Cartero', 'event' => 'Cartero', 'color' => 'purple'],
        'delivered' => ['label' => 'Entregado', 'event' => 'Entregado', 'color' => 'green'],
    ];

    private const INTERNATIONAL_CATEGORIES = [
        'arrival_bolivia' => ['label' => 'Llegada a Bolivia', 'event' => 'Llegada a Bolivia', 'color' => 'blue'],
        'departure_bolivia' => ['label' => 'Salida de Bolivia', 'event' => 'Salida de Bolivia', 'color' => 'cyan'],
        'customs' => ['label' => 'Aduana', 'event' => 'Aduana', 'color' => 'purple'],
        'national_distribution' => ['label' => 'Distribución nacional', 'event' => 'Distribución nacional', 'color' => 'cyan'],
        'last_mile' => ['label' => 'Última milla', 'event' => 'Última milla', 'color' => 'orange'],
        'pickup' => ['label' => 'Ventanilla', 'event' => 'Ventanilla para recojo', 'color' => 'orange'],
        'courier' => ['label' => 'Cartero', 'event' => 'Cartero', 'color' => 'purple'],
        'delivered' => ['label' => 'Entregado', 'event' => 'Entregado', 'color' => 'green'],
        'other' => ['label' => 'Otros estados', 'event' => 'Otros', 'color' => 'gray'],
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
