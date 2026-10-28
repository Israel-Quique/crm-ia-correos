<?php

namespace App\Livewire;

use App\Services\OllamaService;
use Livewire\Component;

class AssistantChat extends Component
{
    public string $message = '';
    public string $reply = '';
    public bool $loading = false;
    public array $messages = [];

    public function ask(OllamaService $ollama): void
    {
        $this->validate(['message' => ['required', 'string', 'max:4000']]);
        $question = trim($this->message);
        // Conserva hasta cuatro conversaciones completas (16 mensajes: usuario/asistente).
        $history = array_slice($this->messages, -16);
        $this->messages[] = ['role' => 'user', 'text' => $question];
        $this->loading = true;
        try { $this->reply = $ollama->chat($question, $history); if ($ollama->lastError) $this->dispatch('system-notification', message: $ollama->lastError); }
        catch (\Throwable $e) {
            report($e);
            $this->reply = 'No se pudo conectar con el asistente en este momento. Intenta nuevamente más tarde.';
            $this->dispatch('system-notification', message: 'El asistente no está disponible en este momento.');
        }
        $this->messages[] = ['role' => 'assistant', 'text' => $this->reply];
        $this->loading = false;
        $this->message = '';
    }

    public function render()
    {
        return view('livewire.assistant-chat');
    }
}
