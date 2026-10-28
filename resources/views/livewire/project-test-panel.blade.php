<div class="content">
    <div class="welcome"><div><h1>Pruebas del sistema ✦</h1><p>Verifica el estado del CRM antes de publicar cambios.</p></div></div>
    <div class="card" style="margin-top:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
            <div><h2 style="margin:0">Panel de validación</h2><p style="margin:6px 0 0;color:#64748b">Base de datos, APIs postales, Ollama y registros sincronizados.</p></div>
            <button class="primary-btn" wire:click="runChecks" wire:loading.attr="disabled"><span wire:loading.remove>Ejecutar pruebas</span><span wire:loading>Comprobando...</span></button>
        </div>
        @if($lastRun)<p style="color:#64748b;margin:18px 0 0">Última ejecución: {{ $lastRun }}</p>@endif
    </div>
    @if($checks)
        <div class="cards-grid" style="margin-top:18px">
            @foreach($checks as $key => $check)
                <div class="card" wire:key="test-{{ $key }}" style="border-left:4px solid {{ $check['ok'] ? '#10B981' : '#EF4444' }}">
                    <div style="font-weight:700">{{ $check['ok'] ? '✓' : '!' }} {{ $check['label'] }}</div>
                    <small style="display:block;color:#64748b;margin-top:8px;word-break:break-word">{{ $check['detail'] }}</small>
                </div>
            @endforeach
        </div>
    @else
        <div class="card" style="margin-top:18px;color:#64748b">Presiona “Ejecutar pruebas” para iniciar la validación.</div>
    @endif
</div>
