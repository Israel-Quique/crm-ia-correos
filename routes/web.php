<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\OllamaController;

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
    Route::view('/chat', 'chat')->name('chat');
    Route::view('/respuestas-automaticas', 'automatic-responses')->name('automatic-responses');
    Route::view('/configuraciones', 'settings')->name('settings');
    Route::view('/pruebas', 'tests')->name('tests');
    Route::view('/campanas-postales', 'postal-campaigns')->name('postal-campaigns');
    Route::view('/campanas-postales/nacional', 'postal-campaigns', ['scope' => 'nacional'])->name('postal-campaigns.national');
    Route::view('/campanas-postales/internacional', 'postal-campaigns', ['scope' => 'internacional'])->name('postal-campaigns.international');
    Route::view('/campanas-postales/internacional/registro', 'postal-campaign-registry')->name('postal-campaigns.international.registry');
    Route::view('/campanas-postales/nacional/registro', 'postal-campaign-registry', ['scope' => 'nacional'])->name('postal-campaigns.national.registry');
});
