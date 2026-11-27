<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\OllamaController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\WhatsAppMediaController;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\AuthController;

Route::get('/', fn () => redirect()->route('login'));
Route::view('/login', 'auth.login')->name('login');
Route::post('/login', function () {
    $credentials = request()->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);
    $credentials['email'] = strtolower(trim($credentials['email']));

    if (! Auth::attempt($credentials, false)) {
        return back()->withErrors(['email' => 'Las credenciales no son válidas.'])->withInput();
    }

    request()->session()->regenerate();
    return redirect()->route('chat');
})->name('login.store');
// Flujo de autenticación principal: mantiene una única sesión y no recuerda credenciales.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);
// Ruta compatible con la configuración de Meta y con el CRM anterior.
Route::get('/api/integrations/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/api/integrations/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive']);
Route::middleware('local.admin')->group(function () {
    Route::get('/auditoria', function () {
        return view('audit-log-real', [
            'events' => \App\Models\AuditLog::with('user')->latest()->paginate(13),
            'legacyEvents' => collect([
                ['action' => 'Configuración modificada', 'detail' => 'Preferencias del sistema', 'actor' => 'Sistema', 'at' => now()],
                ['action' => 'IA activada o desactivada', 'detail' => 'Estado del agente local', 'actor' => 'Sistema', 'at' => now()],
                ['action' => 'Respuesta editada', 'detail' => 'Respuestas automáticas', 'actor' => 'Sistema', 'at' => now()],
                ['action' => 'Campaña creada', 'detail' => 'Registro de campañas postales', 'actor' => 'Sistema', 'at' => now()],
                ['action' => 'Mensaje enviado manualmente', 'detail' => 'Desde el módulo de chat', 'actor' => 'Sistema', 'at' => now()],
                ['action' => 'Campaña reintentada', 'detail' => 'Reenvío de mensajes fallidos', 'actor' => 'Sistema', 'at' => now()],
            ]),
        ]);
    })->name('audit-log');
    Route::get('/dashboard', function () {
        $period = request()->query('period', 'today');
        $since = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->startOfDay(),
        };
        $monthStart = now()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();
        $messages = \App\Models\WhatsAppMessage::query();
        $unreadMessages = (clone $messages)
            ->where('direction', 'inbound')
            ->whereNull('read_at');
        $activeMessages = (clone $messages)->where('created_at', '>=', $since);
        $sentMessages = (clone $messages)
            ->where('direction', 'outbound')
            ->whereIn('status', ['sent', 'delivered', 'read', 'replied'])
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $nextMonthStart);
        $runningCampaignQuery = \App\Models\PostalCampaign::whereIn('status', ['running', 'active', 'en curso']);
        try { $ollamaOnline = \Illuminate\Support\Facades\Http::timeout(1)->get(rtrim(config('services.ollama.url', 'http://127.0.0.1:11434'), '/') . '/api/tags')->successful(); } catch (\Throwable) { $ollamaOnline = false; }
        $packages = \App\Models\PostalPackage::query()->where(function ($query) use ($since) {
            $query->where('updated_at_api', '>=', $since)
                ->orWhere('last_synced_at', '>=', $since)
                ->orWhere('created_at', '>=', $since);
        });
        $packageStatus = fn (string $needle) => (clone $packages)->where('current_event', 'like', '%'.$needle.'%')->count();
        $deliveredPackages = $packageStatus('Entregado');
        $customsPackages = $packageStatus('Aduana');
        $returnedPackages = $packageStatus('Devuelto');
        $inTransitPackages = max(0, (clone $packages)->count() - $deliveredPackages - $customsPackages - $returnedPackages);
        $destinationName = static function ($package): ?string {
            $payload = is_array($package->raw_payload) ? $package->raw_payload : [];
            $name = $package->destination_branch
                ?: data_get($payload, 'destino.departamento')
                ?: data_get($payload, 'destino.nombre_destino')
                ?: data_get($payload, 'destino.nombre')
                ?: data_get($payload, 'destino.ciudad')
                ?: (is_string(data_get($payload, 'destino')) ? data_get($payload, 'destino') : null);
            return $name ? trim((string) $name) : null;
        };
        $topNationalDestinations = (clone $packages)->where('scope', 'nacional')->get(['destination_branch', 'raw_payload'])->map(fn ($package) => $destinationName($package))->filter()->countBy()->sortDesc()->take(3)->map(fn ($total, $destination) => (object) ['destination_branch' => $destination, 'total' => $total])->values();
        $topInternationalDestinations = (clone $packages)->where('scope', 'internacional')->get(['destination_branch', 'raw_payload'])->map(fn ($package) => $destinationName($package))->filter()->countBy()->sortDesc()->take(3)->map(fn ($total, $destination) => (object) ['destination_branch' => $destination, 'total' => $total])->values();
        return view('dashboard', [
            'unanswered' => (clone $unreadMessages)->count(),
            'unreadMessageDetails' => (clone $unreadMessages)->latest('created_at')->limit(5)->get(['contact_name', 'phone', 'message', 'created_at']),
            'activeConversations' => (clone $activeMessages)->distinct('phone')->count('phone'),
            'activeInboundCount' => (clone $activeMessages)->where('direction', 'inbound')->count(),
            'activeOutboundCount' => (clone $activeMessages)->where('direction', 'outbound')->count(),
            'activeContactDetails' => (clone $activeMessages)
                ->select('phone')
                ->selectRaw('MAX(created_at) as last_activity')
                ->groupBy('phone')
                ->orderByDesc('last_activity')
                ->limit(5)
                ->get(),
            'sentThisMonth' => (clone $sentMessages)->count(),
            'sentStatusBreakdown' => (clone $sentMessages)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'runningCampaigns' => (clone $runningCampaignQuery)->count(),
            'runningCampaignDetails' => (clone $runningCampaignQuery)->latest('updated_at')->limit(5)->get(['name', 'status', 'total', 'sent', 'failed', 'pending', 'campaign_type']),
            'availablePackages' => \App\Models\PostalPackage::count(),
            'packageSourceBreakdown' => \App\Models\PostalPackage::query()
                ->selectRaw('source, COUNT(*) as total')
                ->groupBy('source')
                ->orderBy('source')
                ->get(),
            'lastPackageSync' => \App\Models\PostalPackage::max('last_synced_at'),
            'apiErrors' => \App\Models\ApiRecord::whereIn('status', ['error', 'failed', 'failure'])->count(),
            'ollamaOnline' => $ollamaOnline,
            'activities' => (clone $messages)->where('created_at', '>=', $since)->latest('created_at')->limit(5)->get(),
            'period' => $period,
            'deliveredPackages' => $deliveredPackages,
            'inTransitPackages' => $inTransitPackages,
            'customsPackages' => $customsPackages,
            'returnedPackages' => $returnedPackages,
            'topNationalDestinations' => $topNationalDestinations,
            'topInternationalDestinations' => $topInternationalDestinations,
        ]);
    })->name('dashboard');
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('login');
    })->name('logout');
    Route::get('/usuarios-roles', [UserRoleController::class, 'index'])->name('users-roles');
    Route::get('/usuarios', [UserRoleController::class, 'users'])->name('users.index');
    Route::get('/roles', [UserRoleController::class, 'roles'])->name('roles.index');
    Route::post('/usuarios', [UserRoleController::class, 'storeUser'])->name('users.store');
    Route::put('/usuarios/{user}', [UserRoleController::class, 'updateUser'])->name('users.update');
    Route::delete('/usuarios/{user}', [UserRoleController::class, 'destroyUser'])->name('users.destroy');
    Route::post('/roles', [UserRoleController::class, 'storeRole'])->name('roles.store');
    Route::put('/roles/{role}', [UserRoleController::class, 'updateRole'])->name('roles.update');
    Route::delete('/roles/{role}', [UserRoleController::class, 'destroyRole'])->name('roles.destroy');
    Route::post('/api/assistant/chat', [OllamaController::class, 'chat'])->name('assistant.chat');
    Route::view('/assistant', 'assistant')->name('assistant');
    Route::redirect('/chat-automatizado', '/automatizacion');
    Route::view('/tarifas', 'tariffs')->name('tariffs');
    Route::view('/rastreo/nacional', 'postal-tracking', ['scope' => 'nacional'])->name('tracking.national');
    Route::view('/rastreo/internacional', 'postal-tracking', ['scope' => 'internacional'])->name('tracking.international');
    Route::view('/assistant/seguimiento-nacional', 'prompt-manager', ['aiMode' => 'seguimiento_nacional'])->name('assistant.tracking.national');
    Route::view('/assistant/seguimiento-internacional', 'prompt-manager', ['aiMode' => 'seguimiento_internacional'])->name('assistant.tracking.international');
    Route::view('/assistant/cotizaciones', 'prompt-manager', ['aiMode' => 'cotizaciones'])->name('assistant.quotes');
    Route::view('/chat', 'chat')->name('chat');
    Route::get('/chat/media/{message}', [WhatsAppMediaController::class, 'show'])->name('whatsapp.media');
    Route::view('/automatizacion', 'automation')->name('automation');
    Route::view('/respuestas-automaticas', 'automatic-responses')->name('automatic-responses');
    Route::view('/configuraciones', 'settings')->name('settings');
    Route::view('/numeros-prueba', 'test-numbers')->name('whatsapp.test-numbers');
    Route::post('/configuraciones/preferencias', function () {
        $preferences = request()->validate([
            'test_mode' => ['required', 'boolean'],
            'ai_enabled' => ['required', 'boolean'],
            'automatic_responses_enabled' => ['required', 'boolean'],
            'confirm_actions' => ['required', 'boolean'],
        ]);

        foreach ($preferences as $key => $value) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value ? '1' : '0', 'updated_at' => now()],
            );
            \App\Models\AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $key === 'ai_enabled' ? ($value ? 'IA activada' : 'IA desactivada') : 'Configuración modificada',
                'category' => $key === 'ai_enabled' ? 'ia' : 'configuracion',
                'description' => $key === 'ai_enabled' ? 'El agente de inteligencia artificial fue '.($value ? 'activado' : 'desactivado').' desde Configuraciones.' : 'Se modificó la preferencia '.$key.'.',
                'metadata' => ['setting' => $key, 'value' => (bool) $value],
                'ip_address' => request()->ip(),
            ]);
        }

        return response()->json(['saved' => true]);
    })->name('settings.preferences');
    Route::post('/configuraciones/sincronizacion', function () { DB::table('app_settings')->updateOrInsert(['key'=>'postal_sync_enabled'], ['value'=>request()->boolean('enabled') ? '1' : '0', 'updated_at'=>now()]); return back()->with('syncSettingSaved', 'Estado de sincronización actualizado.'); })->name('settings.sync');
    Route::post('/configuraciones/sincronizar-ahora', function (\App\Services\PostalApiSyncService $sync) { $data = request()->validate(['scope' => ['required', 'in:nacional,internacional'], 'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'force' => ['sometimes', 'boolean']]); try { $result = $sync->sync($data['scope'], 1, $data['from'], $data['to'], $data['force'] ?? false); if ($result['skipped'] ?? false) return back()->with('syncNowMessage', 'Ya hay datos locales para todos los días del rango; no se consultó nuevamente la API. Activa “Forzar actualización” si necesitas volver a consultar.'); return back()->with('syncNowMessage', 'Sincronización '.($data['scope'] === 'nacional' ? 'nacional' : 'internacional').' completada: '.($result['received'] ?? 0).' paquetes recibidos.'); } catch (\Throwable $e) { report($e); return back()->with('syncNowError', 'No se pudo sincronizar: '.$e->getMessage()); } })->name('settings.sync-now');
    Route::view('/pruebas', 'tests')->name('tests');
    Route::view('/campanas-postales/internacional/registro', 'postal-campaign-registry')->name('postal-campaigns.international.registry');
    Route::view('/campanas-postales/nacional/registro', 'postal-campaign-registry', ['scope' => 'nacional'])->name('postal-campaigns.national.registry');
    Route::redirect('/campanas-postales', '/campanas-postales/nacional/registro')->name('postal-campaigns');
    Route::redirect('/campanas-postales/nacional', '/campanas-postales/nacional/registro')->name('postal-campaigns.national');
    Route::redirect('/campanas-postales/internacional', '/campanas-postales/internacional/registro')->name('postal-campaigns.international');
    Route::view('/mensajes-predeterminados/nacional', 'postal-campaign-templates', ['scope' => 'nacional'])->name('postal-campaign-templates.national');
    Route::view('/mensajes-predeterminados/internacional', 'postal-campaign-templates', ['scope' => 'internacional'])->name('postal-campaign-templates.international');
    Route::redirect('/mensajes-predeterminados', '/mensajes-predeterminados/nacional')->name('postal-campaign-templates');
});
