<?php

namespace App\Livewire;

use App\Services\DeliveryExpressQuoteService;
use App\Services\PostarQuoteService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class TariffManager extends Component
{
    use WithPagination;

    private const DELIVERY_RATES_PER_PAGE = 20;

    private const EMS_DESTINATIONS = [
        'EMS NAT' => [
            'nacional_beni' => 'Beni',
            'nacional_chuquisaca' => 'Chuquisaca',
            'nacional_cochabamba' => 'Cochabamba',
            'nacional_la_paz' => 'La Paz',
            'nacional_oruro' => 'Oruro',
            'nacional_pando' => 'Pando',
            'nacional_potosi' => 'Potosí',
            'nacional_santa_cruz' => 'Santa Cruz',
            'nacional_tarija' => 'Tarija',
        ],
        'EMS INT' => [
            'Argentina' => 'Argentina',
            'Brasil' => 'Brasil',
            'Canadá' => 'Canadá',
            'Chile' => 'Chile',
            'China' => 'China',
            'Colombia' => 'Colombia',
            'Corea del Sur' => 'Corea del Sur',
            'Costa Rica' => 'Costa Rica',
            'Cuba' => 'Cuba',
            'Ecuador' => 'Ecuador',
            'El Salvador' => 'El Salvador',
            'España' => 'España',
            'Estados Unidos' => 'Estados Unidos',
            'Francia' => 'Francia',
            'Guatemala' => 'Guatemala',
            'Honduras' => 'Honduras',
            'India' => 'India',
            'Italia' => 'Italia',
            'Japón' => 'Japón',
            'México' => 'México',
            'Nicaragua' => 'Nicaragua',
            'Panamá' => 'Panamá',
            'Paraguay' => 'Paraguay',
            'Perú' => 'Perú',
            'Portugal' => 'Portugal',
            'Reino Unido' => 'Reino Unido',
            'República Dominicana' => 'República Dominicana',
            'Uruguay' => 'Uruguay',
            'Venezuela' => 'Venezuela',
        ],
    ];

    public string $activeTab = 'delivery';
    public string $rateSearch = '';
    public string $rateDepartment = '';
    public string $rateMode = '';
    public array $deliveryRates = [];
    public ?string $ratesLoadedAt = null;
    public ?string $ratesError = null;
    public string $category = 'EMS NAT';
    public string $destination = '';
    public string $weight = '';
    public bool $showEmsModal = false;
    public ?string $length = null;
    public ?string $width = null;
    public ?string $height = null;
    public ?array $emsQuote = null;
    public ?string $emsError = null;

    public function updatedRateSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRateDepartment(): void
    {
        $this->resetPage();
    }

    public function updatedRateMode(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->destination = '';
        $this->emsQuote = null;
        $this->emsError = null;
        $this->resetValidation('destination');
    }

    public function closeEmsModal(): void
    {
        $this->showEmsModal = false;
    }

    public function getEmsDestinationsProperty(): array
    {
        return self::EMS_DESTINATIONS[$this->category] ?? [];
    }

    public function loadDeliveryRates(DeliveryExpressQuoteService $service): void
    {
        $this->ratesError = null;
        try {
            $this->deliveryRates = $service->rates();
            $this->ratesLoadedAt = now()->format('d/m/Y H:i:s');
            $this->resetPage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->ratesError = 'No se pudo consultar el tarifario: '.$exception->getMessage();
        }
    }

    public function getFilteredRatesProperty(): LengthAwarePaginator
    {
        $term = mb_strtolower(trim($this->rateSearch));
        $rates = array_values(array_filter(
            $this->deliveryRates,
            function (array $rate) use ($term): bool {
                $destination = (string) data_get($rate, 'destino.nombre_destino', $rate['destino'] ?? '');
                $mode = (string) data_get($rate, 'servicio_extra.nombre', $rate['servicio_extra'] ?? '');
                $searchable = mb_strtolower(json_encode($rate, JSON_UNESCAPED_UNICODE) ?: '');

                return ($this->rateDepartment === '' || mb_strtolower($destination) === mb_strtolower($this->rateDepartment))
                    && ($this->rateMode === '' || mb_strtolower($mode) === mb_strtolower($this->rateMode))
                    && ($term === '' || str_contains($searchable, $term));
            },
        ));
        $page = $this->getPage();

        return new LengthAwarePaginator(
            array_slice($rates, ($page - 1) * self::DELIVERY_RATES_PER_PAGE, self::DELIVERY_RATES_PER_PAGE),
            count($rates),
            self::DELIVERY_RATES_PER_PAGE,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    public function getDeliveryDepartmentsProperty(): array
    {
        return $this->deliveryRateOptions('destino.nombre_destino', 'destino');
    }

    public function getDeliveryModesProperty(): array
    {
        return $this->deliveryRateOptions('servicio_extra.nombre', 'servicio_extra');
    }

    private function deliveryRateOptions(string $nestedKey, string $fallbackKey): array
    {
        $options = [];
        foreach ($this->deliveryRates as $rate) {
            $value = trim((string) data_get($rate, $nestedKey, $rate[$fallbackKey] ?? ''));
            if ($value !== '') {
                $options[$value] = $value;
            }
        }

        natcasesort($options);

        return array_values($options);
    }

    public function quoteEms(PostarQuoteService $postar): void
    {
        $this->activeTab = 'ems';
        $this->showEmsModal = false;
        $this->validate([
            'category' => ['required', 'in:EMS NAT,EMS INT'],
            'destination' => ['required', 'string', 'in:'.implode(',', array_keys($this->emsDestinations))],
            'weight' => ['required', 'numeric', 'gt:0'],
            'length' => ['nullable', 'numeric', 'gt:0'],
            'width' => ['nullable', 'numeric', 'gt:0'],
            'height' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $dimensions = [$this->length, $this->width, $this->height];
        if (count(array_filter($dimensions, fn ($value) => filled($value))) === 1
            || count(array_filter($dimensions, fn ($value) => filled($value))) === 2) {
            $this->addError('dimensions', 'Completa las tres dimensiones o deja todos los campos vacíos.');
            return;
        }

        try {
            $this->emsQuote = $postar->quote(
                $this->category,
                $this->destination,
                (float) $this->weight,
                filled($this->length) ? [
                    'largo' => (float) $this->length,
                    'ancho' => (float) $this->width,
                    'alto' => (float) $this->height,
                ] : null,
            );
            $this->emsError = null;
            $this->showEmsModal = true;
        } catch (\Throwable $exception) {
            report($exception);
            $this->emsQuote = null;
            $this->emsError = $exception->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.tariff-manager');
    }
}
