<?php

namespace App\Services;

use App\Models\AutomaticResponse;
use App\Models\PostalPackage;
use App\Models\WhatsAppConversationSetting;
use App\Models\WhatsAppDecisionTreeSession;
use Illuminate\Support\Facades\DB;
use Throwable;

class WhatsAppDecisionTreeService
{
    private const SESSION_MINUTES = 30;

    private const MAX_TRACKING_ATTEMPTS = 3;

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
        private readonly PostalTrackingNumberParser $trackingNumberParser,
        private readonly DecisionTreeInputNormalizer $inputNormalizer,
        private readonly PackageWeightParser $weightParser,
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

        if ($this->isHumanHandoffRequest($message)) {
            return $this->handoffToHuman($phone);
        }

        if ($session && $this->isCancellation($message)) {
            $session->delete();

            return 'Cancelé el flujo. Puedes elegir otra opción: «cotizar», «rastrear» o «hablar con un asesor».';
        }

        // La solicitud de explicación tiene prioridad sobre saludos y sobre
        // la detección de intención del paso actual de la cotización.
        if ($session
            && $this->isQuoteChoiceQuestion($message)
            && $session->step !== 'quote_mode') {
            $this->saveSession($phone, $session->flow_key, $session->step, $session->context ?? []);

            return $this->quoteChoiceExplanation();
        }

        // Un saludo durante una sesión activa significa que el usuario desea
        // empezar de nuevo. No debe interpretarse como un dato de cotización.
        if ($session && $this->isGreeting($message)) {
            $session->delete();

            return $this->startMenu($phone);
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

    public function detectIntent(string $message): ?string
    {
        return $this->intent($message, null);
    }

    private function intent(string $message, ?AutomaticResponse $response): ?string
    {
        $normalized = $this->normalizeText($message);

        $hasPackageDetails = preg_match('/\b(detall\w*|informaci[oó]n|datos|nombre|consulta\w*|averiguar|saber|estado|ubicaci[oó]n|rastreo|rastrear|seguimiento)\b/u', $normalized) === 1
            && preg_match('/\b(paquet\w*|env[ií]o|gu[ií]a)\b/u', $normalized) === 1;
        if (in_array($response?->type, ['quote', 'tracking'], true)) {
            return $response->type;
        }

        $hasPackageDetails = preg_match('/\b(detall\w*|informaci[oÃ³]n|datos|nombre|consulta\w*|averiguar|saber|estado|ubicaci[oÃ³]n|rastreo|rastrear|seguimiento)\b/u', $normalized) === 1
            && preg_match('/\b(paquet\w*|env[iÃ­]o|gu[iÃ³]a)\b/u', $normalized) === 1;

        $hasShippingVerb = preg_match('/\b(mand\w*|envi\w*|despach\w*)\b/u', $normalized) === 1;
        $hasShipmentNoun = preg_match('/\b(paquet\w*|envio|carta|sobre)\b/u', $normalized) === 1;
        if (preg_match('/\b(cotiz\w*|precio|tarifa|cuanto cuesta|por cuanto)\b/u', $normalized) === 1
            || ($hasShippingVerb && $hasShipmentNoun && ! $hasPackageDetails)) {
            return 'quote';
        }

        if ($hasPackageDetails) {
            return 'tracking';
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

        // En el menú principal, una petición de explicación debe conservar la
        // sesión y explicar las opciones en lugar de tratarla como una opción
        // inválida o volver a disparar el saludo general.
        if ($this->isQuoteChoiceQuestion($message)) {
            $this->saveSession($session->phone, 'menu', 'main_menu', []);

            return $this->quoteChoiceExplanation();
        }

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
        // Si el usuario ya indicó un país, no volvemos a mostrar el menú:
        // un destino internacional implica EMS Internacional.
        $initialInternationalDestination = $this->internationalDestinationFromText($message);
        if ($initialInternationalDestination !== null) {
            $context['scope'] = 'internacional';
            $context['service'] = 'ems';
            $context['origin_name'] = 'Bolivia';
            $context['destination'] = $initialInternationalDestination['code'];
            $context['destination_name'] = $initialInternationalDestination['name'];
        }
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
            $weight = $this->parseWeight($message);
            if ($weight !== null) {
                $context['pending_weight'] = $weight;
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
                    // Preserve valid quote details that arrive before the service
                    // selection; the service is still required before continuing.
                    $route = $this->nationalRouteFromText($message);
                    if ($route !== null && ($context['scope'] ?? null) === 'nacional') {
                        $context = array_merge($context, $route, [
                            'origin_name' => $this->nationalDestinationName($route['origin']),
                            'destination_name' => $this->nationalDestinationName($route['destination']),
                        ]);
                    }
                    $weight = $this->parseWeight($message);
                    if ($weight !== null) {
                        $context['pending_weight'] = $weight;
                    }
                    $this->saveSession($session->phone, 'quote', 'quote_service', $context);

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
                $route = $this->nationalRouteFromText($message);
                if ($route !== null) {
                    $context = array_merge($context, $route, [
                        'origin_name' => $this->nationalDestinationName($route['origin']),
                        'destination_name' => $this->nationalDestinationName($route['destination']),
                    ]);

                    return $this->askQuoteWeight($session->phone, $context, $message);
                }

                $codes = $this->postar->destinationCodesFromText($this->normalizeText($message));
                if ($codes === []) {
                    return 'No reconocí esa ciudad. Indica una ciudad de origen de Bolivia, por ejemplo La Paz o Santa Cruz.';
                }
                $context['origin'] = $codes[0];
                if (count($codes) > 1) {
                    $context['destination'] = $codes[array_key_last($codes)];

                    return $this->askQuoteWeight($session->phone, $context, $message);
                }
                $this->saveSession($session->phone, 'quote', 'quote_destination', $context);

                return '¿A qué ciudad o país se enviará?';

            case 'quote_destination':
                if (preg_match('/\b(nacional|bolivia)\b/u', $this->normalizeText($message)) === 1
                    && preg_match('/\b(me confundi|equivoc|no era|nacional)\b/u', $this->normalizeText($message)) === 1) {
                    unset($context['destination'], $context['destination_name'], $context['pending_national_route']);
                    $context['scope'] = 'nacional';
                    $context['service'] = 'ems';
                    $this->saveSession($session->phone, 'quote', 'quote_origin', $context);

                    return 'Entendido, cambiaré la cotización a nacional. ¿Desde qué ciudad de Bolivia se enviará el paquete?';
                }
                if (($context['scope'] ?? null) === 'internacional') {
                    $destination = $this->internationalDestinationFromText($message);
                } else {
                    $route = $this->nationalRouteFromText($message);
                    if ($route !== null) {
                        $context['origin'] = $route['origin'];
                        $context['origin_name'] = $this->nationalDestinationName($route['origin']);
                        $destination = [
                            'code' => $route['destination'],
                            'name' => $this->nationalDestinationName($route['destination']),
                        ];
                    } else {
                        $destination = $this->nationalDestinationFromText($message);
                    }
                }
                if ($destination === null) {
                    return ($context['scope'] ?? null) === 'internacional'
                        ? 'No reconocí el país. Indica el país de destino, por ejemplo Argentina, Chile o España.'
                        : 'No reconocí esa ciudad. Indica una ciudad de destino de Bolivia.';
                }
                $context['destination'] = $destination['code'];
                $context['destination_name'] = $destination['name'];

                return $this->askQuoteWeight($session->phone, $context, $message);

            case 'quote_weight':
                if (preg_match('/\b(nacional|bolivia)\b/u', $this->normalizeText($message)) === 1
                    && preg_match('/\b(me confundi|equivoc|no era|cambio|nacional)\b/u', $this->normalizeText($message)) === 1) {
                    unset($context['destination'], $context['destination_name'], $context['pending_national_route']);
                    $context['scope'] = 'nacional';
                    $context['service'] = 'ems';
                    $this->saveSession($session->phone, 'quote', 'quote_origin', $context);

                    return 'Entendido, cambiaré la cotización a nacional. ¿Desde qué ciudad de Bolivia se enviará el paquete?';
                }
                $weight = $this->weightParser->parse($message);
                if ($weight === null || $weight === false) {
                    return 'Indica un único peso mayor que cero, por ejemplo 2 kg o 500 g. No envíes precios ni varias opciones de peso.';
                }

                return $this->acceptQuoteWeight($session->phone, $context, $weight);

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
            $route = $this->nationalRouteFromText($message);
            if ($route !== null) {
                $context = array_merge($context, $route, [
                    'origin_name' => $this->nationalDestinationName($route['origin']),
                    'destination_name' => $this->nationalDestinationName($route['destination']),
                ]);
                return $this->askQuoteWeight($phone, $context, $message);
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

    private function askQuoteWeight(string $phone, array $context, string $message = ''): string
    {
        $weight = $this->parseWeight($message) ?? ($context['pending_weight'] ?? null);
        unset($context['pending_weight']);
        if ($weight !== null) {
            return $this->acceptQuoteWeight($phone, $context, (float) $weight);
        }

        $this->saveSession($phone, 'quote', 'quote_weight', $context);

        return '¿Cuánto pesa el paquete? Indica el peso en kg.';
    }

    private function acceptQuoteWeight(string $phone, array $context, float $weight): string
    {
        $context['weight'] = $weight;
        if (($context['service'] ?? null) === 'delivery_express' && $weight > 5) {
            $this->saveSession($phone, 'quote', 'quote_oversize', $context);

            return 'Delivery Express admite hasta 5 kg. Incluye hasta 3 kg; cada kilo adicional suma Bs 10 y el pago en destino suma Bs 2,50. ¿Quieres continuar con EMS? Responde sí o no.';
        }

        return $this->afterQuoteWeight($phone, $context);
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
            : '📦 Envíame el número completo de guía del paquete y consultaré su último estado.';
    }

    private function continueTracking(WhatsAppDecisionTreeSession $session, string $message): string
    {
        $context = $session->context ?? [];

        if ($session->step === 'tracking_number') {
            $number = $this->trackingNumber($message);
            if ($number === null) {
                return $this->trackingInputFailure(
                    $session->phone,
                    $context,
                    'No reconocí el formato de la guía.',
                );
            }

            return $this->lookupTracking($session->phone, $context, $number);
        }

        if ($session->step === 'tracking_recovery') {
            $number = $this->trackingNumber($message);
            if ($number !== null) {
                $context = array_intersect_key($context, array_flip(['scope']));

                return $this->lookupTracking($session->phone, $context, $number);
            }

            $choice = $this->normalizeText($message);
            if (preg_match('/^\s*(1|otra|otro|nueva|nuevo)\b/u', $choice) === 1) {
                $context = array_intersect_key($context, array_flip(['scope']));
                $context['tracking_attempts'] = 0;
                $this->saveSession($session->phone, 'tracking', 'tracking_number', $context);

                return 'Envíame el número de guía del nuevo paquete.';
            }
            if (preg_match('/^\s*2\b/u', $choice) === 1) {
                return $this->handoffToHuman($session->phone);
            }
            if (preg_match('/^\s*3\b/u', $choice) === 1) {
                return $this->startMenu($session->phone);
            }

            return "Elige una opción:\n1. Enviar otra guía\n2. Hablar con un asesor\n3. Volver al menú.";
        }

        if ($session->step === 'tracking_scope') {
            $scope = $this->parseScope($message);
            if ($scope === null) {
                return 'Indica si el paquete es nacional o internacional.';
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
            return $this->trackingInputFailure(
                $phone,
                $context,
                "📦 No encontré el paquete con guía {$number} en los registros. Verifica el código y, para confirmar su estado, comunícate al ".config('services.postal.support_phone', '64229403').'.',
            );
        }

        if ($packages->count() > 1 && ! isset($context['scope'])) {
            $context['tracking_number'] = $number;
            $this->saveSession($phone, 'tracking', 'tracking_scope', $context);

            return "📦 Encontré más de un paquete con la guía {$number}. ¿El paquete es nacional o internacional?";
        }

        $package = $packages->first();
        $event = $package->latestEvent;
        $presenter = app(PostalEventPresenter::class);
        $eventStatus = trim((string) $event?->event_name);
        $status = $presenter->isEmptyStatus($eventStatus) ? trim((string) $package->current_event) : $eventStatus;
        $friendlyStatus = $presenter->currentStatus($package);
        $eventCategory = $presenter->currentCategory($package);
        $updatedAt = $event?->event_at ?: $package->updated_at_api;
        $date = $updatedAt ? "\n🕒 Último movimiento: ".$updatedAt->format('d/m/Y H:i').'.' : '';
        $destination = $package->destination_branch ? "\n📍 Destino registrado: {$package->destination_branch}." : '';
        $recipient = $package->recipient ? "\n👤 Destinatario: {$package->recipient}." : '';
        $sender = $package->sender ? "\n📨 Remitente: {$package->sender}." : '';
        $this->saveSession($phone, 'tracking', 'tracking_another', [
            'scope' => $package->scope,
            'tracking_number' => $number,
        ]);

        if ($friendlyStatus === null) {
            return "📦 No encontré movimientos registrados para el paquete con guía {$number}; todavía no puedo confirmar su último estado. Comunícate al ".config('services.postal.support_phone', '64229403')." para más información.\n¿Deseas rastrear otro paquete? Responde sí o no.";
        }

        $packageStatus = $presenter->packageStatus($friendlyStatus);
        $eventTemplate = $this->trackingEventTemplate($package->scope, $status, $number);
        $eventDetails = $eventTemplate === null
            ? ''
            : "\n".$presenter->packageStatus($eventTemplate);

        return "📦 El último estado del paquete con guía {$number} es: {$packageStatus}.{$eventDetails}\n🏷️ Clasificación: {$eventCategory}.{$recipient}{$sender}{$destination}{$date}\n¿Deseas rastrear otro paquete? Responde sí o no.";
    }

    private function trackingInputFailure(string $phone, array $context, string $reason): string
    {
        $attempts = (int) ($context['tracking_attempts'] ?? 0) + 1;
        $context['tracking_attempts'] = $attempts;
        unset($context['tracking_number']);

        if ($attempts >= self::MAX_TRACKING_ATTEMPTS) {
            $this->saveSession($phone, 'tracking', 'tracking_recovery', $context);

            return "{$reason}\nYa van {$attempts} intentos. Elige una opción:\n1. Enviar otra guía\n2. Hablar con un asesor\n3. Volver al menú.";
        }

        $this->saveSession($phone, 'tracking', 'tracking_number', $context);

        return "{$reason} Intento {$attempts} de ".self::MAX_TRACKING_ATTEMPTS.". Envíame el código completo, por ejemplo RR123456789XX o C0001A89911BO.";
    }

    private function isHumanHandoffRequest(string $message): bool
    {
        $normalized = $this->normalizeText($message);

        return preg_match('/\b(asesor|agente|humano|reclamo|representante|operador|persona real)\b/u', $normalized) === 1
            || preg_match('/\b(hablar con|comunicarme con|atenci[oó]n al cliente|servicio al cliente|ayuda humana|contactar a (?:un|una) persona)\b/u', $normalized) === 1
            || preg_match('/^\s*ayuda\s*$/u', $normalized) === 1;
    }

    private function handoffToHuman(string $phone): string
    {
        WhatsAppConversationSetting::query()->updateOrCreate(
            ['phone' => $phone],
            ['mode' => 'manual'],
        );
        WhatsAppDecisionTreeSession::query()->where('phone', $phone)->delete();

        return 'Pausé las respuestas automáticas para que un asesor pueda continuar esta conversación desde el CRM. El equipo responderá por este chat cuando esté disponible.';
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
        if (preg_match('/\b(nacional|bolivia|ems nat|ems nacional)\b/u', $normalized) === 1
            && preg_match('/\b(internacional|ems int|exterior)\b/u', $normalized) !== 1) {
            return ['scope' => 'nacional', 'service' => 'ems'];
        }
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
        return $this->postar->routeFromText($this->normalizeText($message));
    }

    private function quoteChoicePrompt(): string
    {
        return "¿Qué tipo de envío deseas cotizar?\n1. Nacional (EMS NAT)\n2. Internacional (EMS INT)\n3. Delivery Express";
    }

    public function quoteChoiceExplanation(): string
    {
        return "Las diferencias son:\n"
            ."1) Nacional (EMS NAT): para envíos dentro de Bolivia; la tarifa EMS depende del destino y el peso.\n"
            ."2) Internacional (EMS INT): para envíos desde Bolivia a otro país; la tarifa depende del país de destino y el peso.\n"
            ."3) Delivery Express: servicio nacional, hasta 5 kg y con una modalidad de entrega requerida. Incluye hasta 3 kg; cada kilo adicional suma Bs 10 y el pago en destino suma Bs 2,50.\n\n"
            .$this->quoteChoicePrompt();
    }

    public function isQuoteChoiceQuestion(string $message): bool
    {
        $normalized = $this->normalizeText($message);

        return preg_match(
            '/\b(diferenc\w*|cambi\w*|vari\w*|convien\w*|mejor|compar\w*|explic\w*|consist\w*|cosist\w*|signific\w*|describ\w*|aclar\w*|detall\w*|opcion\w*|modalidad\w*|quiere decir|que es|que son|cual elegir|cual conviene|me puedes explicar|puedes explicar|podrias explicar)\b/u',
            $normalized,
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
        $codes = $this->postar->destinationCodesFromText($this->normalizeText($message));
        if ($codes === []) {
            return null;
        }
        $code = $codes[array_key_last($codes)];

        return ['code' => $code, 'name' => $this->nationalDestinationName($code)];
    }

    public function internationalDestinationFromText(string $message): ?array
    {
        $normalized = $this->normalizeText($message);
        foreach (self::INTERNATIONAL_DESTINATIONS as $destination) {
            if (str_contains($normalized, $this->normalizeText($destination))) {
                return ['code' => $destination, 'name' => $destination];
            }
        }

        // Acepta errores ortográficos frecuentes en el nombre completo del país.
        $aliases = [
            'brazil' => 'Brasil', 'brasil' => 'Brasil', 'argentinaa' => 'Argentina',
            'columbia' => 'Colombia', 'colomibia' => 'Colombia', 'chille' => 'Chile',
            'peru' => 'Perú', 'paraguai' => 'Paraguay', 'urugay' => 'Uruguay',
            'venezuelaa' => 'Venezuela', 'mexcio' => 'México', 'mexixo' => 'México',
            'espana' => 'España', 'franciaa' => 'Francia', 'alemanía' => 'Alemania',
            'estados unidos' => 'Estados Unidos', 'reino unido' => 'Reino Unido',
            'costarica' => 'Costa Rica', 'puertorico' => 'Puerto Rico',
        ];
        foreach ($aliases as $alias => $destination) {
            if (str_contains($normalized, $this->normalizeText($alias))) {
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
        $weight = $this->weightParser->parse($message);

        return is_float($weight) ? $weight : null;
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
        $normalized = $this->normalizeText($message);
        if (preg_match('/\b(no|nose|omitir|sin dimensiones)\b/u', $normalized) === 1) {
            return null;
        }

        $normalizedMessage = preg_replace('/\b(\d{1,3})\.(\d{3})\b/', '$1$2', $message) ?? $message;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[x×,]\s*(\d+(?:[.,]\d+)?)\s*[x×,]\s*(\d+(?:[.,]\d+)?)/iu', $normalizedMessage, $matches) !== 1) {
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
        return $this->trackingNumberParser->extract($message);
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
        $normalized = $this->normalizeText($message);

        return preg_match('/^\s*(cancelar|salir|detener|terminar)\b/u', $normalized) === 1
            || preg_match('/\b(?:no quiero|no deseo|no necesito|no voy a)\b.*\b(?:cotizar|realizar|hacer)\b/u', $normalized) === 1
            || preg_match('/\b(?:no quiero|no deseo|no necesito)\b.*\b(?:env[ií]o|envio|paquete)\b/u', $normalized) === 1;
    }

    private function isGreeting(string $message): bool
    {
        $normalized = $this->normalizeText($message);

        return preg_match(
            '/^\s*(hola|buenas|buenos dias|buenas tardes|buenas noches)(?:[\s,!?.]*(como estas|que tal))?[\s,!?.]*$/u',
            $normalized,
        ) === 1;
    }

    private function isMenuRequest(string $message): bool
    {
        return preg_match('/^\s*(menu|inicio)\s*$/u', $this->normalizeText($message)) === 1;
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
        return $this->inputNormalizer->normalize($text);
    }
}
