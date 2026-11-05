@extends('layouts.crm', ['title' => 'Configurar prompt · ' . ucfirst($aiMode ?? 'cotizaciones')])
@section('content')
<div class="content"><div class="welcome"><div><h1>Asistente IA ✦</h1><p>Configura las instrucciones del agente para {{ str_replace('_', ' ', $aiMode ?? 'cotizaciones') }}.</p></div><a href="{{ route('assistant') }}" class="primary-btn" style="text-decoration:none">▣ Vista Chat</a></div><livewire:prompt-manager :ai-mode="$aiMode ?? 'cotizaciones'" /></div>
@endsection
