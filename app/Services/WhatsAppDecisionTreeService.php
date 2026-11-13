<?php

namespace App\Services;

use App\Models\AutomaticResponse;
use App\Models\PostalPackage;
use App\Models\WhatsAppDecisionTreeSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppDecisionTreeService
{
    private const SESSION_MINUTES = 30;

    private const INTERNATIONAL_DESTINATIONS = [
        'Argentina', 'Brasil', 'Canadá', 'Chile', 'China', 'Colombia', 'Corea del Sur',
        'Costa Rica', 'Cuba', 'Ecuador', 'El Salvador', 'España', 'Estados Unidos',
        'Francia', 'Guatemala', 'Honduras', 'India', 'Italia', 'Japón', 'México',
        'Nicaragua', 'Panamá', 'Paraguay', 'Perú', 'Portugal', 'Reino Unido',
        'República Dominicana', 'Uruguay', 'Venezuela',
    ];

    private const NATIONAL_DESTINATIONS = [
        'nacional_beni' => 'Beni',
        'nacional_chuquisaca' => 'Chuquisaca',
        'nacional_cochabamba' => 'Cochabamba',
        'nacional_la_paz' => 'La Paz',
        'nacional_oruro' => 'Oruro',
        'nacional_pando' => 'Pando',
        'nacional_potosi' => 'Potosí',
        'nacional_santa_cruz' => 'Santa Cruz',
        'nacional_tarija' => 'Tarija',
    ];

    public function __construct(
        private readonly PostarQuoteService $postar,
        private readonly DeliveryExpressQuoteService $deliveryExpress,
    ) {
    }

    public function replyTo(string $phone, string $message, ?AutomaticResponse $matchedResponse = null): ?string
    {
        $phone = $this->normalizePhone($phone);
        $message = trim($message);

        if ($phone === '' || $message === '') {
            return null;
        }

        $session = WhatsAppDecisionTreeSession::query()->where('phone', $phone)->first();
        if ($session?->expires_at?->isPast()) {
            $session->delete();
            $session = null;
        }

        if ($session && $this->isCancellation($message)) {
            $session->delete();

            return 'Cancelé el flujo. Escribe «cotizar» o «rastrear» cuando quieras iniciar otra consulta.';
        }

        if ($session && $this->isMenuRequest($message)) {
            return $this->startMenu($phone);
        }

        if ($session && $session->flow_key === 'menu') {
            return $this->continueMenu($session, $message);
        }

        $intent = $this->intent($message, $matchedResponse);

        if ($session && $intent === $session->flow_key) {
            return $this->continueFlow($session, $message);
        }

        if ($session && $intent !== null && $intent !== $session->flow_key) {
            $session->delete();
            $session = null;
        }

        if ($session) {
            return $this->continueFlow($session, $message);
        }

        if ($this->isMenuRequest($message) && $this->anyFlowEnabled()) {
            return $this->startMenu($phone);
        }

        if ($intent === null || ! $this->flowEnabled($intent)) {
            return null;
        }

        return $intent === 'quote'
            ? $this->startQuote($phone, $message, $matchedResponse)
            : $this->startTracking($phone, $message, $matchedResponse);
    }

    private function intent(string $message, ?AutomaticResponse $response): ?string
    {
        if (in_array($response?->type, ['quote', 'tracking'], true)) {
            return $response->type;
        }

        $normalized = $this->normalizeText($message);

        $hasShippingVerb = preg_match('/\b(mand\w*|envi\w*|despach\w*)\b/u', $normalized) === 1;
        $hasShipmentNoun = preg_match('/\b(paquet\w*|envio|carta|sobre)\b/u', $normalized) === 1;
        if (preg_match('/\b(cotiz\w*|precio|tarifa|cuanto cuesta)\b/u', $normalized) === 1
            || ($hasShippingVerb && $hasShipmentNoun)) {
            return 'quote';
        }

        if (preg_match('/\b(rastre\w*|seguimiento|ubicacion|donde esta|estado de mi paquete|estado del paquete)\b/u', $normalized) === 1) {
            return 'tracking';
        }

        return null;
    }

    private function startMenu(string $phone): string
    {
        $this->saveSession($phone, 'menu', 'main_menu', []);

        return $this->menuMessage();
    }

    private function menuMessage(): string
    {
        $options = [];
        if ($this->flowEnabled('quote')) {
            $options[] = '1. Cotizar un envío';
        }
        if ($this->flowEnabled('tracking')) {
            $options[] = '2. Rastrear un paquete';
        }

        return "¡Hola! ¿Qué necesitas?\n".implode("\n", $options)."\nResponde con el número de la opción.";
    }

    private function continueMenu(WhatsAppDecisionTreeSession $session, string $message): string
    {
        $intent = $this->intent($message, null);
        if ($intent === 'quote' && $this->flowEnabled('quote')) {
            $session->delete();

            return $this->startQuote($session->phone, $message, null);
        }
        if ($intent === 'tracking' && $this->flowEnabled('tracking')) {
            $session->delete();

            return $this->startTracking($session->phone, $message, null);
        }

        $choice = trim($message);
        if ($choice === '1' && $this->flowEnabled('quote')) {
            $session->delete();

            return $this->startQuote($session->phone, '', null);
        }
        if ($choice === '2' && $this->flowEnabled('tracking')) {
            $session->delete();

            return $this->startTracking($session->phone, '', null);
        }

        return $this->menuMessage()."\nNo reconocí esa opción.";
    }

    private function startQuote(string $phone, string $message, ?AutomaticResponse $response): string
    {
        $context = [
            'scope' => $this->quoteScope($message, $response),
            'service' => $this->quoteService($message, $response),
        ];
        if ($context['scope'] !== null && $context['service'] === null) {
            $context['service'] = 'ems';
        }
        if ($context['scope'] === 'internacional') {
            $context['origin_name'] = 'Bolivia';
        }
        $quoteResponse = $this->quoteResponse($context, $response);

        if ($context['scope'] === null) {
            $route = $this->nationalRouteFromText($message);
            if ($route !== null) {
                $context['pending_national_route'] = $route;
            }
            $this->saveSession($phone, 'quote', 'quote_scope', $context);

            return $this->withTemplate($quoteResponse?->template, $this->quoteChoicePrompt());
        }

        if ($context['scope'] === 'internacional' && $context['service'] === 'delivery_express') {
            $context['service'] = null;
        }

        if ($context['service'] === null) {
            $this->saveSession($phone, 'quote', 'quote_service', $context);

            $question = $context['scope'] === 'nacional'
                ? '¿Qué servicio prefieres para el envío nacional: EMS o Delivery Express?'
                : 'Para envíos internacionales está disponible EMS INT. ¿Deseas cotizar por EMS?';

            return $this->withTemplate($quoteResponse?->template, $question);
        }

        return $this->withTemplate(
            $quoteResponse?->template,
            $this->askNextQuoteField($phone, $context, $message),
        );
    }

    private function continueFlow(WhatsAppDecisionTreeSession $session, string $message): string
    {
        return match ($session->flow_key) {
            'quote' => $this->continueQuote($session, $message),
            'tracking' => $this->continueTracking($session, $message),
            default => $this->menuMessage(),
        };
    }

    private function continueQuote(WhatsAppDecisionTreeSession $session, string $message): string
    {
        $context = $session->context ?? [];

        switch ($session->step) {
            case 'quote_scope':
                $selection = $this->parseQuoteChoice($message);
                if ($selection === null) {
                    $route = $this->nationalRouteFromText($message);
                    if ($route !== null) {
                        $context = array_merge($context, $route, [
                            'scope' => 'nacional',
                            'origin_name' => $this->nationalDestinationName($route['origin']),
                            'destination_name' => $this->nationalDestinationName($route['destination']),
                        ]);
                        $this->saveSession($session->phone, 'quote', 'quote_service', $context);

                        return "Entendido: el paquete irá desde {$context['origin_name']} hasta {$context['destination_name']}.\n"
                            .'¿Qué servicio prefieres: EMS Nacional o Delivery Express?';
                    }

                    if ($this->isQuoteChoiceQuestion($message)) {
                        $this->saveSession($session->phone, 'quote', 'quote_scope', $context);

                        return $this->quoteChoiceExplanation();
                    }

                    return $this->quoteChoicePrompt();
                }
                $context = array_merge($context, $selection);
                $route = $this->nationalRouteFromText($message);
                if ($context['scope'] === 'nacional' && $route !== null) {
                    $context = array_merge($context, $route, [
                        'origin_name' => $this->nationalDestinationName($route['origin']),
                        'destination_name' => $this->nationalDestinationName($route['destination']),
                    ]);
                }
                if ($context['scope'] === 'internacional') {
                    unset($context['pending_national_route']);
                    $context['origin_name'] = 'Bolivia';
                } elseif (isset($context['pending_national_route'])) {
                    $context = array_merge($context, $context['pending_national_route']);
                    unset($context['pending_national_route']);
                }
                $quoteResponse = $this->quoteResponse($context, null);

                return $this->withTemplate(
                    $quoteResponse?->template,
                    $this->askNextQuoteField($session->phone, $context, ''),
                );

            case 'quote_service':
                $service = $this->parseService($message, $context['scope'] ?? null);
                if ($service === null) {
                    return ($context['scope'] ?? null) === 'nacional'
                        ? 'Responde EMS o Delivery Express.'
                        : 'Para envíos internacionales responde EMS para continuar con EMS INT.';
                }
                $context['service'] = $service;
                $quoteResponse = $this->quoteResponse($context, null);

                return $this->withTemplate(
                    $quoteResponse?->template,
                    $this->askNextQuoteField($session->phone, $context, $message),
                );

            case 'quote_origin':
                $codes = $this->postar->destinationCodesFromText($message);
                if ($codes === []) {
                    return 'No reconocí esa ciudad. Indica una ciudad de origen de Bolivia, por ejemplo La Paz o Santa Cruz.';
                }
                $context['origin'] = $codes[0];
                if (count($codes) > 1) {
                    $context['destination'] = $codes[array_key_last($codes)];

                    return $this->askQuoteWeight($session->phone, $context);
                }
                $this->saveSession($session->phone, 'quote', 'quote_destination', $context);

                return '¿A qué ciudad o país se enviará?';

            case 'quote_destination':
                $destination = ($context['scope'] ?? null) === 'internacional'
                    ? $this->internationalDestinationFromText($message)
                    : $this->nationalDestinationFromText($message);
                if ($destination === null) {
                    return ($context['scope'] ?? null) === 'internacional'
                        ? 'No reconocí el país. Indica el país de destino, por ejemplo Argentina, Chile o España.'
                        : 'No reconocí esa ciudad. Indica una ciudad de destino de Bolivia.';
                }
                $context['destination'] = $destination['code'];
                $context['destination_name'] = $destination['name'];

                return $this->askQuoteWeight($session->phone, $context);

            case 'quote_weight':
                $weight = $this->parseWeight($message);
                if ($weight === null) {
                    return 'Indica el peso en kg, por ejemplo 2 kg.';
                }
                $context['weight'] = $weight;
                if (($context['service'] ?? null) === 'delivery_express' && $weight > 5) {
                    $this->saveSession($session->phone, 'quote', 'quote_oversize', $context);

                    return 'Delivery Express admite hasta 5 kg. ¿Quieres continuar con EMS? Responde sí o no.';
                }

                return $this->afterQuoteWeight($session->phone, $context);

            case 'quote_oversize':
                if (! $this->isAffirmative($message)) {
                    $session->delete();

                    return 'No continué la cotización. Escribe «cotizar» si deseas iniciar otra consulta.';
                }
                $context['service'] = 'ems';

                return $this->askQuoteDimensions($session->phone, $context);

            case 'quote_mode':
                $mode = $this->deliveryModeFromText($message);
                if ($mode === null) {
                    if ($this->isDeliveryModeQuestion($message)) {
                        $this->saveSession($session->phone, 'quote', 'quote_mode', $context);

                        return $this->deliveryModeExplanation();
                    }

                    return $this->deliveryModePrompt();
                }
                $context['mode'] = $mode;

                return $this->calculateQuote($session, $context);

            case 'quote_dimensions':
                $dimensions = $this->parseDimensions($message);
                if ($dimensions === false) {
                    return "Puedes responder «no» para omitir las dimensiones o enviarlas como largo x ancho x alto en cm, por ejemplo 20 x 15 x 10.";
                }
                $context['dimensions'] = $dimensions;

                return $this->calculateQuote($session, $context);
        }

        $session->delete();

        return 'La cotización se reinició. Escribe «cotizar» para comenzar otra vez.';
    }

    private function askNextQuoteField(string $phone, array $context, string $message): string
    {
        if (($context['scope'] ?? null) === 'nacional') {
            $codes = $this->postar->destinationCodesFromText($message);
            if (count($codes) > 1) {
                $context['origin'] = $codes[0];
                $context['destination'] = $codes[array_key_last($codes)];
                return $this->askQuoteWeight($phone, $context);
            }
            if (($context['origin'] ?? null) === null) {
                $this->saveSession($phone, 'quote', 'quote_origin', $context);

                return '¿Desde qué ciudad de Bolivia se enviará el paquete?';
            }
        } elseif (($context['destination'] ?? null) === null) {
            $destination = $this->internationalDestinationFromText($message);
            if ($destination !== null) {
                $context['destination'] = $destination['code'];
                $context['destination_name'] = $destination['name'];
            } else {
                $this->saveSession($phone, 'quote', 'quote_destination', $context);

                return 'Tomaré Bolivia como país de origen. ¿A qué país se enviará el paquete?';
            }
        }

        if (($context['destination'] ?? null) === null) {
            $this->saveSession($phone, 'quote', 'quote_destination', $context);

            return '¿A qué ciudad de Bolivia se enviará el paquete?';
        }

        $weight = $this->parseWeight($message);
        if ($weight !== null) {
            $context['weight'] = $weight;
            return $this->afterQuoteWeight($phone, $context);
        }

        return $this->askQuoteWeight($phone, $context);
    }

    private function askQuoteWeight(string $phone, array $context): string
    {
        $this->saveSession($phone, 'quote', 'quote_weight', $context);

        return '¿Cuánto pesa el paquete? Indica el peso en kg.';
    }

    private function afterQuoteWeight(string $phone, array $context): string
    {
        if (($context['service'] ?? null) === 'delivery_express') {
            $this->saveSession($phone, 'quote', 'quote_mode', $context);

            return "¿Qué modalidad prefieres?\n1. Puerta a puerta\n2. Puerta a ventanilla\n3. Ventanilla a ventanilla.";
        }

        return $this->askQuoteDimensions($phone, $context);
    }

    private function askQuoteDimensions(string $phone, array $context): string
    {
        $this->saveSession($phone, 'quote', 'quote_dimensions', $context);

        return '¿Deseas incluir dimensiones? Envía largo x ancho x alto en cm o responde «no».';
    }

    private function calculateQuote(WhatsAppDecisionTreeSession $session, array $context): string
    {
        try {
            if (($context['service'] ?? null) === 'delivery_express') {
                $quote = $this->deliveryExpress->quote(
                    (float) $context['weight'],
                    (string) $context['destination'],
                    (string) $context['mode'],
                    isset($context['origin']) ? (string) $context['origin'] : null,
                );
                $price = (float) $quote['price'];
                $currency = (string) ($quote['currency'] ?? 'BOB');
                $serviceName = 'Delivery Express';
            } else {
                $category = ($context['scope'] ?? null) === 'internacional' ? 'EMS INT' : 'EMS NAT';
                $quote = $this->postar->quote(
                    $category,
                    (string) $context['destination'],
                    (float) $context['weight'],
                    $context['dimensions'] ?? null,
                );
                $price = (float) $quote['tariff'];
                $currency = (string) ($quote['currency'] ?? 'BOB');
                $serviceName = $category;
            }
        } catch (Throwable $exception) {
            report($exception);
            $session->delete();

            return 'No pude verificar una tarifa para esos datos. No enviaré un precio estimado. Intenta nuevamente más tarde o consulta con una persona.';
        }

        $destinationName = $context['destination_name'] ?? $this->nationalDestinationName((string) $context['destination']);
        $weight = number_format((float) $context['weight'], 2, '.', '');
        $currencyLabel = $currency === 'BOB' ? 'Bs' : $currency;
        $originName = $context['origin_name']
            ?? (isset($context['origin']) ? $this->nationalDestinationName((string) $context['origin']) : null);
        $originLabel = $originName ? " desde {$originName}" : '';
        $session->delete();

        return sprintf(
            'La tarifa verificada de %s para %s kg%s con destino a %s es %s %s. Escribe «cotizar» para otra cotización o «rastrear» para consultar una guía.',
            $serviceName,
            $weight,
            $originLabel,
            $destinationName,
            $currencyLabel,
            number_format($price, 2, '.', ''),
        );
    }

    private function startTracking(string $phone, string $message, ?AutomaticResponse $response): string
    {
        $scope = $this->trackingScope($message, $response);
        $number = $this->trackingNumber($message);
        $context = array_filter(['scope' => $scope, 'tracking_number' => $number], fn ($value) => $value !== null);

        if ($number !== null) {
            return $this->lookupTracking($phone, $context, $number);
        }

        $this->saveSession($phone, 'tracking', 'tracking_number', $context);

        return $response?->type === 'tracking' && trim($response->template) !== ''
            ? trim($response->template)
            : 'Envíame el número completo de guía y consultaré el último estado registrado.';
    }

    private function continueTracking(WhatsAppDecisionTreeSession $session, string $message): string
    {
        $context = $session->context ?? [];

        if ($session->step === 'tracking_number') {
            $number = $this->trackingNumber($message);
            if ($number === null) {
                return 'No reconocí el número de guía. Envíame el código completo, por ejemplo RR123456789XX.';
            }

            return $this->lookupTracking($session->phone, $context, $number);
        }

        if ($session->step === 'tracking_scope') {
            $scope = $this->parseScope($message);
            if ($scope === null) {
                return 'Indica si el envío es nacional o internacional.';
            }
            $context['scope'] = $scope;

            return $this->lookupTracking($session->phone, $context, (string) $context['tracking_number']);
        }

        if ($session->step === 'tracking_another') {
            if ($this->isAffirmative($message)) {
                $context = array_intersect_key($context, array_flip(['scope']));
                $this->saveSession($session->phone, 'tracking', 'tracking_number', $context);

                return 'Envíame el número de guía del otro paquete.';
            }

            $session->delete();

            return 'Listo. Escribe «cotizar» o «rastrear» cuando necesites otra consulta.';
        }

        $session->delete();

        return 'El rastreo se reinició. Envíame «rastrear» para consultar una guía.';
    }

    private function lookupTracking(string $phone, array $context, string $number): string
    {
        $query = PostalPackage::query()
            ->whereRaw('upper(trim(tracking_number)) = ?', [$number]);
        if (isset($context['scope'])) {
            $query->where('scope', $context['scope'])
                ->where('source', $context['scope'] === 'nacional' ? 'siop' : 'sitra');
        } else {
            $query->whereIn('scope', ['nacional', 'internacional']);
        }

        $packages = $query->with('latestEvent')->get();
        if ($packages->isEmpty()) {
            $this->saveSession($phone, 'tracking', 'tracking_number', $context);

            return "No encontré la guía {$number} en los registros disponibles. Verifica el código y envíamelo nuevamente.";
        }

        if ($packages->count() > 1 && ! isset($context['scope'])) {
            $context['tracking_number'] = $number;
            $this->saveSession($phone, 'tracking', 'tracking_scope', $context);

            return "Encontré más de un registro con esa guía. ¿El envío es nacional o internacional?";
        }

        $package = $packages->first();
        $event = $package->latestEvent;
        $status = trim((string) ($package->current_event ?: $event?->event_name));
        if ($status === '') {
            $status = 'En proceso de actualización';
        }
        $updatedAt = $event?->event_at ?: $package->updated_at_api;
        $date = $updatedAt ? "\nÚltimo movimiento: ".$updatedAt->format('d/m/Y H:i').'.' : '';
        $destination = $package->destination_branch ? "\nDestino registrado: {$package->destination_branch}." : '';
        $eventTemplate = $this->trackingEventTemplate($package->scope, $status, $number);
        $this->saveSession($phone, 'tracking', 'tracking_another', [
            'scope' => $package->scope,
            'tracking_number' => $number,
        ]);

        if ($eventTemplate !== null) {
            return $eventTemplate."{$destination}{$date}\n¿Deseas rastrear otra guía? Responde sí o no.";
        }

        $statusMessage = $this->trackingStatusMessage($status);

        return "Guía {$number} ({$package->scope}): {$statusMessage}\nEstado registrado: {$status}.{$destination}{$date}\n¿Deseas rastrear otra guía? Responde sí o no.";
    }

    private function trackingEventTemplate(string $scope, string $status, string $trackingNumber): ?string
    {
        $normalizedStatus = $this->normalizeText($status);
        $bestResponse = null;
        $bestScore = 0;

        $responses = AutomaticResponse::query()
            ->where('scope', $scope)
            ->where('type', 'event')
            ->where('active', true)
            ->latest()
            ->get();

        foreach ($responses as $candidate) {
            $score = 0;
            foreach (array_merge([$candidate->key, $candidate->name], $candidate->trigger_phrases ?? []) as $term) {
                $normalizedTerm = $this->normalizeText(str_replace('_', ' ', trim((string) $term)));
                if ($normalizedTerm !== '' && str_contains($normalizedStatus, $normalizedTerm)) {
                    $score = max($score, mb_strlen($normalizedTerm));
                }
            }

            if ($score > $bestScore) {
                $bestResponse = $candidate;
                $bestScore = $score;
            }
        }

        if ($bestResponse === null) {
            return null;
        }

        return str_replace('{tracking_number}', $trackingNumber, trim($bestResponse->template));
    }

    private function trackingStatusMessage(string $status): string
    {
        $normalized = $this->normalizeText($status);

        if (str_contains($normalized, 'entreg')) {
            return 'el sistema registra el paquete como entregado.';
        }
        if (str_contains($normalized, 'ventanilla') || str_contains($normalized, 'recojo') || str_contains($normalized, 'retiro')) {
            return 'el paquete figura disponible para recojo en una oficina, según el último registro.';
        }
        if (str_contains($normalized, 'aduana') || str_contains($normalized, 'detenid')) {
            return 'el último registro lo ubica en proceso de aduana o detenido.';
        }
        if (str_contains($normalized, 'cartero') || str_contains($normalized, 'reparto') || str_contains($normalized, 'ultima milla')) {
            return 'el último registro indica que está en la etapa final de distribución.';
        }

        return 'se encuentra en seguimiento según el último evento disponible.';
    }

    private function quoteScope(string $message, ?AutomaticResponse $response): ?string
    {
        if ($this->normalizeText((string) $response?->key) === 'ems int'
            || $response?->scope === 'internacional'
            || preg_match('/\binternacional\b/u', $this->normalizeText($message)) === 1) {
            return 'internacional';
        }
        if ($this->normalizeText((string) $response?->key) === 'ems nat'
            || $this->normalizeText((string) $response?->key) === 'delivery express'
            || $response?->scope === 'nacional'
            || preg_match('/\bnacional\b/u', $this->normalizeText($message)) === 1) {
            return 'nacional';
        }

        return null;
    }

    private function quoteService(string $message, ?AutomaticResponse $response): ?string
    {
        $text = $this->normalizeText($message.' '.($response?->key ?? '').' '.($response?->name ?? ''));
        if (str_contains($text, 'delivery')) {
            return 'delivery_express';
        }
        if (preg_match('/\bems\b/u', $text) === 1) {
            return 'ems';
        }

        return null;
    }

    private function parseQuoteChoice(string $message): ?array
    {
        $normalized = $this->normalizeText($message);
        if (preg_match('/\b(delivery|express|3)\b/u', $normalized) === 1) {
            return ['scope' => 'nacional', 'service' => 'delivery_express'];
        }
        if (preg_match('/\b(internacional|exterior|ems int|ems internacional|2)\b/u', $normalized) === 1) {
            return ['scope' => 'internacional', 'service' => 'ems'];
        }
        if (preg_match('/\b(nacional|bolivia|ems nat|ems nacional|1)\b/u', $normalized) === 1) {
            return ['scope' => 'nacional', 'service' => 'ems'];
        }

        return null;
    }

    private function nationalRouteFromText(string $message): ?array
    {
        $codes = $this->postar->destinationCodesFromText($message);
        if (count($codes) < 2) {
            return null;
        }

        return [
            'origin' => $codes[0],
            'destination' => $codes[array_key_last($codes)],
        ];
    }

    private function quoteChoicePrompt(): string
    {
        return "¿Qué tipo de envío deseas cotizar?\n1. Nacional (EMS NAT)\n2. Internacional (EMS INT)\n3. Delivery Express";
    }

    public function quoteChoiceExplanation(): string
    {
        return "Las diferencias son:\n"
            ."1. Nacional (EMS NAT): para envíos dentro de Bolivia; la tarifa EMS depende del destino y el peso.\n"
            ."2. Internacional (EMS INT): para envíos desde Bolivia a otro país; la tarifa depende del país de destino y el peso.\n"
            ."3. Delivery Express: servicio nacional, hasta 5 kg y con una modalidad de entrega requerida.\n\n"
            .$this->quoteChoicePrompt();
    }

    public function isQuoteChoiceQuestion(string $message): bool
    {
        return preg_match(
            '/\b(diferenc\w*|cambi\w*|vari\w*|convien\w*|mejor|compar\w*|explic\w*|consist\w*|cosist\w*|signific\w*|describ\w*|quiere decir|que es|que son)\b/u',
            $this->normalizeText($message),
        ) === 1;
    }

    public function isDeliveryModeQuestion(string $message): bool
    {
        return $this->isQuoteChoiceQuestion($message);
    }

    public function deliveryModePrompt(): string
    {
        return "¿Qué modalidad prefieres?\n1. Puerta a puerta\n2. Puerta a ventanilla\n3. Ventanilla a ventanilla.";
    }

    public function deliveryModeExplanation(): string
    {
        return "Las modalidades cambian según dónde se entrega y se recoge el paquete:\n"
            ."1. Puerta a puerta: Correos recoge el paquete en el domicilio de origen y lo entrega en el domicilio de destino.\n"
            ."2. Puerta a ventanilla: Correos recoge el paquete en el domicilio de origen y quien lo recibe lo retira en una oficina.\n"
            ."3. Ventanilla a ventanilla: quien envía deja el paquete en una oficina y quien lo recibe lo retira en otra.\n\n"
            .$this->deliveryModePrompt();
    }

    private function quoteResponse(array $context, ?AutomaticResponse $response): ?AutomaticResponse
    {
        if ($response?->type === 'quote' && trim($response->template) !== '') {
            return $response;
        }

        if (($context['scope'] ?? null) === null || ($context['service'] ?? null) === null) {
            return null;
        }

        $key = match ([$context['scope'], $context['service']]) {
            ['nacional', 'delivery_express'] => 'Delivery Express',
            ['nacional', 'ems'] => 'EMS NAT',
            ['internacional', 'ems'] => 'EMS INT',
            default => null,
        };

        return $key === null
            ? null
            : AutomaticResponse::query()
                ->where('type', 'quote')
                ->where('key', $key)
                ->where('active', true)
                ->latest()
                ->first();
    }

    private function withTemplate(?string $template, string $question): string
    {
        $template = trim((string) $template);

        return $template === '' ? $question : $template."\n\n".$question;
    }

    private function parseScope(string $message): ?string
    {
        $normalized = $this->normalizeText($message);
        if (preg_match('/\b(nacional|bolivia|1)\b/u', $normalized) === 1) {
            return 'nacional';
        }
        if (preg_match('/\b(internacional|exterior|2)\b/u', $normalized) === 1) {
            return 'internacional';
        }

        return null;
    }

    private function parseService(string $message, ?string $scope): ?string
    {
        $normalized = $this->normalizeText($message);
        if (str_contains($normalized, 'delivery')) {
            return $scope === 'internacional' ? null : 'delivery_express';
        }
        if (preg_match('/\bems\b/u', $normalized) === 1) {
            return 'ems';
        }

        return null;
    }

    private function nationalDestinationFromText(string $message): ?array
    {
        $codes = $this->postar->destinationCodesFromText($message);
        if ($codes === []) {
            return null;
        }
        $code = $codes[array_key_last($codes)];

        return ['code' => $code, 'name' => $this->nationalDestinationName($code)];
    }

    private function internationalDestinationFromText(string $message): ?array
    {
        $normalized = $this->normalizeText($message);
        foreach (self::INTERNATIONAL_DESTINATIONS as $destination) {
            if (str_contains($normalized, $this->normalizeText($destination))) {
                return ['code' => $destination, 'name' => $destination];
            }
        }

        return null;
    }

    private function nationalDestinationName(string $code): string
    {
        return self::NATIONAL_DESTINATIONS[$code] ?? str_replace('_', ' ', $code);
    }

    private function parseWeight(string $message): ?float
    {
        if (preg_match('/(?<!\d)(\d+(?:[.,]\d+)?)\s*(kg|kilos?|kilogramos?|g|gramos?)?/iu', $message, $match) !== 1) {
            return null;
        }

        $weight = (float) str_replace(',', '.', $match[1]);
        if (preg_match('/^(g|gramos?)$/iu', $match[2] ?? '') === 1) {
            $weight /= 1000;
        }

        return $weight > 0 ? $weight : null;
    }

    public function deliveryModeFromText(string $message): ?string
    {
        $normalized = $this->normalizeText($message);

        return match (true) {
            trim($normalized) === '1', str_contains($normalized, 'puerta a puerta') => 'puerta_a_puerta',
            trim($normalized) === '2', str_contains($normalized, 'puerta a ventanilla') => 'puerta_a_ventanilla',
            trim($normalized) === '3', str_contains($normalized, 'ventanilla a ventanilla') => 'ventanilla_a_ventanilla',
            default => null,
        };
    }

    private function parseDimensions(string $message): array|false|null
    {
        if (preg_match('/\b(no|omitir|sin dimensiones)\b/u', $this->normalizeText($message)) === 1) {
            return null;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[x×,]\s*(\d+(?:[.,]\d+)?)\s*[x×,]\s*(\d+(?:[.,]\d+)?)/iu', $message, $matches) !== 1) {
            return false;
        }

        $values = array_map(fn (string $value): float => (float) str_replace(',', '.', $value), array_slice($matches, 1, 3));
        if (min($values) <= 0) {
            return false;
        }

        return ['largo' => $values[0], 'ancho' => $values[1], 'alto' => $values[2]];
    }

    private function trackingNumber(string $message): ?string
    {
        if (preg_match('/(?<![A-Z0-9])([A-Z]{1,3}[\s-]*\d{5,}[\s-]*[A-Z]{0,3})(?![A-Z0-9])/iu', $message, $match) !== 1) {
            return null;
        }

        $number = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $match[1]) ?? '');

        return strlen($number) >= 6 ? $number : null;
    }

    private function trackingScope(string $message, ?AutomaticResponse $response): ?string
    {
        if ($response?->scope === 'nacional' || preg_match('/\bnacional\b/u', $this->normalizeText($message)) === 1) {
            return 'nacional';
        }
        if ($response?->scope === 'internacional' || preg_match('/\binternacional\b/u', $this->normalizeText($message)) === 1) {
            return 'internacional';
        }

        return null;
    }

    private function isAffirmative(string $message): bool
    {
        return preg_match('/^\s*(si|claro|continuar|ok|de acuerdo|1)\b/u', $this->normalizeText($message)) === 1;
    }

    private function isCancellation(string $message): bool
    {
        return preg_match('/^\s*(cancelar|salir|detener|terminar)\b/u', $this->normalizeText($message)) === 1;
    }

    private function isMenuRequest(string $message): bool
    {
        return preg_match('/^\s*(menu|menú|inicio)\s*$/iu', trim($message)) === 1;
    }

    private function anyFlowEnabled(): bool
    {
        return $this->flowEnabled('quote') || $this->flowEnabled('tracking');
    }

    private function flowEnabled(string $flow): bool
    {
        $key = match ($flow) {
            'quote' => 'automatic_decision_flow_quote',
            'tracking' => 'automatic_decision_flow_tracking',
            default => null,
        };
        if ($key === null) {
            return false;
        }

        return filter_var(DB::table('app_settings')->where('key', $key)->value('value') ?? '1', FILTER_VALIDATE_BOOLEAN);
    }

    private function saveSession(string $phone, string $flow, string $step, array $context): WhatsAppDecisionTreeSession
    {
        return WhatsAppDecisionTreeSession::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'flow_key' => $flow,
                'step' => $step,
                'context' => $context,
                'expires_at' => now()->addMinutes(self::SESSION_MINUTES),
            ],
        );
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function normalizeText(string $text): string
    {
        $normalized = mb_strtolower(Str::ascii(trim($text)), 'UTF-8');

        return preg_replace('/\bpauqu?ete\b/u', 'paquete', $normalized) ?? $normalized;
    }
}
