<?php

namespace App\Console\Commands;

use App\Models\PostalEvent;
use App\Models\PostalPackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectPostalRange extends Command
{
    protected $signature = 'postal:inspect-range {--months=12}';
    protected $description = 'Verifica los paquetes postales dentro del rango indicado';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $from = now()->startOfMonth()->subMonths($months - 1)->startOfDay();
        $to = now()->endOfDay();
        $this->table(['Alcance', 'Total', 'Dentro del rango', 'Fecha mínima', 'Fecha máxima'], collect(['nacional', 'internacional'])->map(function ($scope) use ($from, $to) {
            $query = PostalPackage::where('scope', $scope);
            return [$scope, $query->count(), (clone $query)->whereBetween('updated_at_api', [$from, $to])->count(), (string) $query->min('updated_at_api'), (string) $query->max('updated_at_api')];
        })->all());
        $this->line('Rango consultado: '.$from->toDateTimeString().' hasta '.$to->toDateTimeString());
        $this->line('Eventos almacenados: '.PostalEvent::count());
        $events = DB::table('postal_events')->join('postal_packages', 'postal_packages.id', '=', 'postal_events.postal_package_id')->select('postal_packages.scope','postal_packages.source','postal_events.source_event','postal_events.event_name')->distinct()->orderBy('postal_packages.scope')->orderBy('postal_events.source_event')->get();
        $this->table(['Alcance','Fuente','Código original','Evento original'], $events->map(fn($event)=>[$event->scope,$event->source,$event->source_event,$event->event_name])->all());
        return self::SUCCESS;
    }
}
