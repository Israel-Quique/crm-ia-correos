<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\OllamaController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\WhatsAppMediaController;
use Illuminate\Support\Facades\DB;

Route::get('/', fn () => redirect()->route('login'));
Route::view('/login', 'auth.login')->name('login');
Route::post('/login', function () {
    $credentials = request()->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (! Auth::attempt($credentials, request()->boolean('remember'))) {
        return back()->withErrors(['email' => 'Las credenciales no son válidas.'])->withInput();
    }

    request()->session()->regenerate();
    return redirect()->route('dashboard');
})->name('login.store');
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);
// Ruta compatible con la configuración de Meta y con el CRM anterior.
Route::get('/api/integrations/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/api/integrations/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive']);
Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('login');
    })->name('logout');
    Route::post('/api/assistant/chat', [OllamaController::class, 'chat'])->name('assistant.chat');
    Route::view('/assistant', 'assistant')->name('assistant');
    Route::view('/chat-automatizado', 'automated-chat')->name('automated-chat');
    Route::view('/assistant/seguimiento-nacional', 'prompt-manager', ['aiMode' => 'seguimiento_nacional'])->name('assistant.tracking.national');
    Route::view('/assistant/seguimiento-internacional', 'prompt-manager', ['aiMode' => 'seguimiento_internacional'])->name('assistant.tracking.international');
    Route::view('/assistant/cotizaciones', 'prompt-manager', ['aiMode' => 'cotizaciones'])->name('assistant.quotes');
    Route::view('/chat', 'chat')->name('chat');
    Route::view('/historial-mensajes', 'whatsapp-message-history')->name('whatsapp.message-history');
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
        }

        return response()->json(['saved' => true]);
    })->name('settings.preferences');
    Route::post('/configuraciones/sincronizacion', function () { DB::table('app_settings')->updateOrInsert(['key'=>'postal_sync_enabled'], ['value'=>request()->boolean('enabled') ? '1' : '0', 'updated_at'=>now()]); return back()->with('syncSettingSaved', 'Estado de sincronización actualizado.'); })->name('settings.sync');
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
