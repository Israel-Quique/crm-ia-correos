<button type="button" class="primary-btn campaign-create-trigger" onclick="document.getElementById('campaignCreateModal').showModal()">＋ Crear campaña</button>
<dialog id="campaignCreateModal" class="campaign-history-modal campaign-create-modal">
    <form wire:submit="prepareCampaign">
        <div class="modal-head">
            <div>
                <h2>Crear campaña {{ $scope }}</h2>
                <p>Se utilizarán los paquetes que coincidan con los filtros.</p>
            </div>
            <button type="button" class="modal-close" onclick="this.closest('dialog').close()">×</button>
        </div>
        <div class="campaign-modal-body">
            <label>
                Nombre de la campaña
                <input wire:model="campaignName" class="input" placeholder="Campaña postal">
            </label>
            <label>
                Duración total de la campaña (segundos)
                <input wire:model="intervalSeconds" type="number" min="30" class="input">
                <small>Mínimo 30 segundos; se calcula el ritmo por mensaje.</small>
            </label>
            <label class="campaign-test-toggle">
                <input type="checkbox" wire:model.live="testMode">
                Modo prueba
            </label>
            @if($testMode)
                <small class="campaign-mode-help campaign-mode-test">Modo prueba activo: solo se incluirán paquetes con un teléfono registrado en Números de prueba. Cada mensaje irá al teléfono de ese paquete; los demás se excluirán.</small>
            @else
                <small class="campaign-mode-help campaign-mode-live">Modo real activo: se incluirán todos los paquetes que coincidan con los filtros y los mensajes irán a sus teléfonos, aunque no estén registrados en Números de prueba.</small>
            @endif
            <label>
                Mensaje que se registrará
                <textarea wire:model="template" class="input" rows="4"></textarea>
            </label>
            <button class="primary-btn" type="submit">Crear campaña</button>
        </div>
    </form>
</dialog>
