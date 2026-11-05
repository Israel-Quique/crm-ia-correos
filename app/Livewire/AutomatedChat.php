<?php

namespace App\Livewire;

use App\Models\AutomaticResponse;
use App\Models\PostalPackage;
use App\Services\DeliveryExpressQuoteService;
use App\Services\EmsCalculator;
use App\Services\PostarQuoteService;
use App\Services\AutomaticResponseMatcher;
use Livewire\Component;

class AutomatedChat extends Component
{
    public string $message = '';
    public array $messages = [];

    public function send(EmsCalculator $ems, DeliveryExpressQuoteService $delivery, PostarQuoteService $postar): void
    {
        $this->validate(['message' => 'required|string|max:1000']);
        $input = trim($this->message);
        $this->messages[] = ['role' => 'user', 'text' => $input];
        $this->messages[] = ['role' => 'assistant', 'text' => $this->conversationReply($input, $ems, $delivery, $postar)];
        $this->message = '';
    }

    private function conversationReply(string $input, EmsCalculator $ems, DeliveryExpressQuoteService $delivery, PostarQuoteService $postar): string
    {
        if ($tracking = $this->trackingRequest($input)) {
            return $this->trackingReply($tracking);
        }
        $text = $this->normalizeText(implode(' ', array_column($this->messages, 'text')));
        $userText = $this->normalizeText(implode(' ', array_map(fn ($item) => ($item['role'] ?? '') === 'user' ? ($item['text'] ?? '') : '', $this->messages)));
        $route = $this->route($text, $postar);
        $service = $this->service($text);
        if (preg_match('/\b(?:hola|buenas|saludos)\b/iu', $input) && !preg_match('/\b(?:enviar|llevar|mandar|cotizar|cu[aá]nto cuesta|rastrea|paquete|pauqete)\b/iu', $input)) return '¡Hola! 👋 Soy el asistente de Correos de Bolivia. ¿Deseas cotizar un envío o rastrear un paquete? 😊';
        if (!$route && preg_match('/\b(?:enviar|llevar|mandar|cotizar|coztaria|cu[aá]nto cuesta)\b/iu', $input)) return '¿Desde qué ciudad deseas enviar el paquete y a qué ciudad?';
        if (!$route && $service) return 'Para cotizar necesito la ciudad de origen y la ciudad de destino. ¿Desde qué ciudad se enviará y a qué ciudad?';
        if ($route && !$service) return "Para cotizar tu envío desde {$route['origin_name']} hasta {$route['destination_name']}, ¿prefieres EMS o Delivery Express?";
        if ($service === 'delivery_express') {
            $mode = $this->mode($userText);
            if (!$mode && preg_match('/\b(?:explica|explicar|explicarme|explicaci[oó]n|qu[eé]\s+significa|cu[aá]les\s+son|no\s+entiendo|no\s+comprendo|qu[eé]\s+son)\b.*\bmodalidad(?:es)?\b|\bmodalidad(?:es)?\b.*\b(?:explica|explicar|explicarme|explicaci[oó]n|significa|entiendo|comprendo)\b/iu', $input)) {
                return "Te explico las modalidades de forma sencilla. En este caso, ‘ventanilla’ significa una oficina de Correos:\n\n• Puerta a puerta: Correos recoge el paquete en tu domicilio y lo entrega en el domicilio de la persona que lo recibe.\n\n• Puerta a ventanilla: Correos recoge el paquete en tu domicilio y la persona que lo recibe debe retirarlo en una oficina de Correos.\n\n• Ventanilla a ventanilla: tú llevas el paquete a una oficina de Correos y la persona que lo recibe lo retira en otra oficina.\n\n¿Cuál de estas opciones te resulta más conveniente?";
            }
            if (!$mode) return '¿Qué modalidad deseas: puerta a puerta, puerta a ventanilla o ventanilla a ventanilla?';
            $weight = $this->weight($userText);
            if ($weight === null) return '¿Cuánto pesa el paquete? Delivery Express admite hasta 5 kg.';
            if ($weight > 5) return 'Delivery Express admite como máximo 5 kg. Para un peso mayor, puedo cotizarlo por EMS Nacional.';
            try { $q = $delivery->quote($weight, $route['destination'], $mode); return sprintf('La tarifa verificada de Delivery Express para %.2f kg desde %s hasta %s es de Bs. %.2f.', $weight, $route['origin_name'], $route['destination_name'], $q['price']); }
            catch (\Throwable) { return 'No pude confirmar la tarifa oficial de Delivery Express en este momento. Intenta nuevamente más tarde.'; }
        }
        if ($service === 'ems') {
            $weight = $this->weight($userText);
            if ($weight === null) return "Perfecto. Para EMS desde {$route['origin_name']} hasta {$route['destination_name']}, ¿cuánto pesa el paquete?";
            try { $q = $ems->calculate('EMS NAT', $route['destination'], $weight); return sprintf('La tarifa verificada de EMS para %.2f kg desde %s hasta %s es de Bs. %.2f.', $weight, $route['origin_name'], $route['destination_name'], $q['tariff']); }
            catch (\Throwable) { return 'No pude confirmar la tarifa oficial de EMS en este momento. Intenta nuevamente más tarde.'; }
        }
        return $this->resolveResponse($input)?->template ?? 'Indícame si deseas cotizar un envío o rastrear un paquete.';
    }

    private function route(string $text, PostarQuoteService $postar): ?array
    {
        $text = $this->normalizeText($text);
        $cities = '(?:la paz|el alto|santa cruz|cochabamba|santa cruz|sucre|trinidad|cobija|oruro|potos[ií]|tarija)';
        if (!preg_match('/(?:desde|de)\s+(' . $cities . ')\s+(?:hasta|a)\s+(' . $cities . ')/iu', $text, $m)) return null;
        $origin = trim($m[1]); $destination = trim($m[2]);
        $originCode = $postar->destinationCode($origin); $destinationCode = $postar->destinationCode($destination);
        if (!$originCode || !$destinationCode) return null;
        return ['origin_name' => ucfirst($origin), 'destination_name' => ucfirst($destination), 'destination' => $destinationCode];
    }
    private function service(string $text): ?string { preg_match_all('/\b(?:por\s+)?(delivery(?:\s*express)?|ems)\b/iu', $text, $m); if (!$m[1]) return null; return str_contains(mb_strtolower(end($m[1])), 'delivery') ? 'delivery_express' : 'ems'; }
    private function weight(string $text): ?float { if (!preg_match_all('/(\d+(?:[.,]\d+)?)\s*(kg|kilo|kilos|kilogramo|kilogramos|ilos|ilo|g|gramos?)\b/iu', $text, $matches, PREG_SET_ORDER)) return null; $m=$matches[array_key_last($matches)]; $n=(float)str_replace(',','.',$m[1]); return preg_match('/^(g|gramos?)$/iu',$m[2]) ? $n/1000 : $n; }
    private function mode(string $text): ?string { return match(true) { preg_match('/puerta\s+a\s+puerta/iu',$text)===1=>'puerta_a_puerta', preg_match('/puerta\s+a\s+ventanilla/iu',$text)===1=>'puerta_a_ventanilla', preg_match('/ventanilla\s+a\s+ventanilla/iu',$text)===1=>'ventanilla_a_ventanilla', default=>null }; }
    private function trackingRequest(string $text): ?string
    {
        if (!preg_match('/\b(?:d[oó]nde|donde|estado|rastrea|seguimiento|ubicaci[oó]n)\b.*\b(?:paquete|env[ií]o|gu[ií]a)\b|\b(?:paquete|env[ií]o|gu[ií]a)\b.*\b(?:d[oó]nde|donde|estado|rastrea|seguimiento|ubicaci[oó]n)\b/iu', $text)) return null;
        if (!preg_match('/\b([A-Z]{1,3}\d{6,}[A-Z]{0,3})\b/i', $text, $match)) return null;
        return strtoupper($match[1]);
    }
    private function trackingReply(string $tracking): string
    {
        $package = PostalPackage::with('events')->whereRaw('upper(tracking_number) = ?', [$tracking])->first();
        if (!$package) return "No encontré la guía {$tracking} en los registros disponibles. Verifica que el código esté escrito correctamente.";
        $event = $package->events->sortByDesc('event_at')->first();
        $status = $package->current_event ?: $event?->event_name ?: 'en proceso de actualización';
        if ($package->scope === 'internacional' && (string) $event?->source_event === '37') {
            $status = 'entregado en el país de destino';
        }
        $date = $event?->event_at ?: $package->updated_at_api;
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
        $dateText = $date ? "\n🕒 Última actualización: {$date->format('d/m/Y H:i')}." : '';
        $destination = $package->destination_branch ? "\n📍 Destino: {$package->destination_branch}." : '';
        return "¡Hola! 👋 {$greeting}. Soy Jaimito de Correos de Bolivia ✉️.\n\n📦 Tu paquete {$tracking} se encuentra actualmente como: {$status}.{$destination}{$dateText}\n💬 Gracias por preferirnos. Puedes escribirnos a este mismo número para consultar por tu paquete 😊.";
    }
    private function resolveResponse(string $message): ?AutomaticResponse { return app(AutomaticResponseMatcher::class)->match($message); }
    private function normalizeText(string $text): string { return str_ireplace(['coztaria', 'crcuz', 'santa crcuz', 'pauqete'], ['costaria', 'cruz', 'santa cruz', 'paquete'], $text); }
    public function render() { return view('livewire.automated-chat'); }
}
