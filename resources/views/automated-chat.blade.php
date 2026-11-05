@extends('layouts.crm', ['title' => 'Chat automatizado · Conversa OS'])
@section('content')
<main class="content automated-chat-page"><div class="welcome"><div><h1>Chat automatizado</h1><p>Prueba las respuestas predeterminadas que se usan cuando la IA no está disponible.</p></div><span class="ai-online">● Modo automático</span></div>@livewire('automated-chat')</main>
@endsection
