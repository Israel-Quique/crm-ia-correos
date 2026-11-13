<?php

namespace App\Livewire;

use App\Models\AutomaticResponse;
use App\Services\AutomaticResponseMatcher;
use App\Services\DeliveryExpressQuoteService;
use App\Services\PostarQuoteService;
use App\Services\WhatsAppDecisionTreeService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Illuminate\Support\Str;
use Throwable;

class AutomaticDecisionTrees extends Component
{
    private const FLOWS = [
        'quote' => [
            'setting' => 'automatic_decision_flow_quote',
            'name' => 'Cotizar un envío',
        ],
        'tracking' => [
            'setting' => 'automatic_decision_flow_tracking',
            'name' => 'Rastrear un paquete',
        ],
    ];

    public string $simulatorNode = 'root';

    public string $simulatorActiveLabel = 'ROOT NODE · SALUDO Y BIENVENIDA';

    public array $simulatorMessages = [];

    public array $simulatorContext = [];

    public string $simulatorInput = '';

    public string $simulatorPlaceholder = 'Escribe tu respuesta para avanzar en la simulación...';

    public function mount(): void
    {
        $this->resetSimulator();
    }

    private function startSimulatorFlowFromTrigger(string $message): bool
    {
        $response = app(AutomaticResponseMatcher::class)->match($message);
        if ($response?->type === 'event' && Str::lower($response->key) === 'saludo') {
            $this->setSimulatorNode('root', 'ROOT NODE · SALUDO Y BIENVENIDA');
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'ROOT NODE · SALUDO Y BIENVENIDA',
                'text' => trim($response->template),
            ];

            return true;
        }

        if ($response?->type === 'quote') {
            $key = mb_strtolower(Str::ascii(trim($response->key)), 'UTF-8');
            $service = match ($key) {
                'ems nat' => 'ems_nat',
                'ems int' => 'ems_int',
                'delivery express' => 'delivery_express',
                default => null,
            };
            $scope = match ($service) {
                'ems_nat', 'delivery_express' => 'nacional',
                'ems_int' => 'internacional',
                default => null,
            };
            $this->simulatorContext = ['flow' => 'quote', 'initial_message' => $message];
            if ($scope === null) {
                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'DECISION NODE · ALCANCE DEL ENVÍO',
                    'text' => $this->withTemplate(
                        $response->template,
                        "¿Qué tipo de envío deseas cotizar?\n1. Nacional (EMS NAT)\n2. Internacional (EMS INT)\n3. Delivery Express",
                    ),
                ];
                $this->setSimulatorNode('quote_scope', 'DECISION NODE · TIPO DE ENVÍO');
                $this->simulatorPlaceholder = 'Elige Nacional, Internacional o Delivery Express...';

                return true;
            }

            $this->simulatorContext['scope'] = $scope;
            $this->simulatorContext['service'] = $service;
            if ($scope === 'internacional') {
                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'DECISION NODE · DESTINO INTERNACIONAL',
                    'text' => $this->withTemplate($response->template, 'Tomaré Bolivia como origen. ¿A qué país se enviará el paquete?'),
                ];
                $this->setSimulatorNode('quote_destination', 'DECISION NODE · PAÍS DE DESTINO');
                $this->simulatorPlaceholder = 'Escribe el país de destino...';

                return true;
            }

            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'DECISION NODE · ORIGEN NACIONAL',
                'text' => $this->withTemplate($response->template, '¿Desde qué ciudad de Bolivia se enviará el paquete?'),
            ];
            $this->setSimulatorNode('quote_origin', 'DECISION NODE · CIUDAD DE ORIGEN');
            $this->simulatorPlaceholder = 'Escribe la ciudad de origen...';

            return true;
        }

        if ($response?->type === 'tracking') {
            $this->simulatorContext = ['flow' => 'tracking', 'scope' => $response->scope];
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'DECISION NODE · NÚMERO DE GUÍA',
                'text' => trim($response->template) !== ''
                    ? trim($response->template)
                    : 'Envíame el número completo de guía para consultar el último estado registrado.',
            ];
            $this->setSimulatorNode('tracking_number', 'DECISION NODE · NÚMERO DE GUÍA');
            $this->simulatorPlaceholder = 'Ejemplo: RR123456789XX';

            return true;
        }

        return false;
    }

    public function resetSimulator(): void
    {
        $this->simulatorNode = 'root';
        $this->simulatorActiveLabel = 'ROOT NODE · SALUDO Y BIENVENIDA';
        $this->simulatorMessages = [];
        $this->simulatorContext = [];
        $this->simulatorInput = '';
        $this->simulatorPlaceholder = 'Escribe una frase para cotizar o rastrear...';
    }

    public function sendSimulatorMessage(): void
    {
        $message = trim($this->simulatorInput);
        if ($message === '') {
            return;
        }

        $this->simulatorInput = '';
        $this->advanceSimulator($message, $message);
    }

    private function advanceSimulator(string $value, string $display): void
    {
        $this->simulatorMessages[] = ['speaker' => 'client', 'node' => null, 'text' => $display];
        $normalized = $this->normalizeSimulatorText($value);

        switch ($this->simulatorNode) {
            case 'root':
                if ($this->startSimulatorFlowFromTrigger($value)) {
                    return;
                }

                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'ROOT NODE · SALUDO Y BIENVENIDA',
                    'text' => 'No reconocí esa intención. Prueba «Quisiera cotizar el envío de un paquete», «Quiero enviar un paquete», «Delivery Express» o «rastrear mi paquete».',
                ];

                return;

            case 'quote_scope':
                $decisionTree = app(WhatsAppDecisionTreeService::class);
                if ($decisionTree->isQuoteChoiceQuestion($value)) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · TIPO DE ENVÍO',
                        'text' => $decisionTree->quoteChoiceExplanation(),
                    ];

                    return;
                }

                $codes = app(PostarQuoteService::class)->destinationCodesFromText($value);
                if (count($codes) > 1) {
                    $this->simulatorContext['scope'] = 'nacional';
                    $this->simulatorContext['origin'] = $codes[0];
                    $this->simulatorContext['destination'] = $codes[array_key_last($codes)];
                    $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($codes[0]);
                    $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($codes[array_key_last($codes)]);
                }

                $service = null;
                if (preg_match('/\b(delivery|express|3)\b/u', $normalized) === 1) {
                    $this->simulatorContext['scope'] = 'nacional';
                    $service = 'delivery_express';
                } elseif (preg_match('/\b(internacional|exterior|ems int|ems internacional|2)\b/u', $normalized) === 1) {
                    $this->simulatorContext['scope'] = 'internacional';
                    $service = 'ems_int';
                } elseif (preg_match('/\b(nacional|bolivia|ems nat|ems nacional|1)\b/u', $normalized) === 1) {
                    $this->simulatorContext['scope'] = 'nacional';
                    $service = 'ems_nat';
                }

                if ($service === null) {
                    if (isset($this->simulatorContext['origin'], $this->simulatorContext['destination'])) {
                        $this->simulatorMessages[] = [
                            'speaker' => 'bot',
                            'node' => 'DECISION NODE · SERVICIO NACIONAL',
                            'text' => 'Entendido, el envío es nacional. ¿Qué servicio prefieres: EMS Nacional o Delivery Express?',
                        ];
                        $this->setSimulatorNode('quote_service', 'DECISION NODE · SERVICIO NACIONAL');

                        return;
                    }

                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · TIPO DE ENVÍO',
                        'text' => "Elige una opción:\n1. Nacional (EMS NAT)\n2. Internacional (EMS INT)\n3. Delivery Express",
                    ];

                    return;
                }

                $this->simulatorContext['service'] = $service;
                $codes = app(PostarQuoteService::class)->destinationCodesFromText($value);
                if (count($codes) < 2) {
                    $codes = app(PostarQuoteService::class)->destinationCodesFromText(
                        (string) ($this->simulatorContext['initial_message'] ?? ''),
                    );
                }
                if ($service !== 'ems_int' && count($codes) > 1) {
                    $this->simulatorContext['origin'] = $codes[0];
                    $this->simulatorContext['destination'] = $codes[array_key_last($codes)];
                    $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($codes[0]);
                    $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($codes[array_key_last($codes)]);
                    $templateKey = match ($service) {
                        'delivery_express' => 'Delivery Express',
                        default => 'EMS NAT',
                    };
                    $this->appendQuotePrompt($templateKey, '¿Cuánto pesa el paquete? Indica el peso en kg.');
                    $this->setSimulatorNode('quote_weight', 'DECISION NODE · PESO');
                    $this->simulatorPlaceholder = 'Ejemplo: 2 kg';

                    return;
                }

                if ($service !== 'ems_int'
                    && isset($this->simulatorContext['origin'], $this->simulatorContext['destination'])) {
                    $this->appendQuotePrompt(
                        $service === 'delivery_express' ? 'Delivery Express' : 'EMS NAT',
                        '¿Cuánto pesa el paquete? Indica el peso en kg.',
                    );
                    $this->setSimulatorNode('quote_weight', 'DECISION NODE · PESO');
                    $this->simulatorPlaceholder = 'Ejemplo: 2 kg';

                    return;
                }

                $prompt = match ($service) {
                    'ems_int' => 'Tomaré Bolivia como país de origen. ¿A qué país se enviará el paquete?',
                    'delivery_express', 'ems_nat' => '¿Desde qué ciudad de Bolivia se enviará el paquete?',
                };
                $this->appendQuotePrompt(match ($service) {
                    'ems_int' => 'EMS INT',
                    'delivery_express' => 'Delivery Express',
                    default => 'EMS NAT',
                }, $prompt);
                $node = $service === 'ems_int' ? 'quote_destination' : 'quote_origin';
                $label = $service === 'ems_int' ? 'DECISION NODE · PAÍS DE DESTINO' : 'DECISION NODE · CIUDAD DE ORIGEN';
                $this->setSimulatorNode($node, $label);
                $this->simulatorPlaceholder = $service === 'ems_int'
                    ? 'Escribe el país de destino...'
                    : 'Escribe la ciudad de origen...';

                return;

            case 'quote_international':
                if (preg_match('/\b(ems|si|claro|continuar|ok|1)\b/u', $normalized) !== 1) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'TERMINAL NODE · EMS INT',
                        'text' => 'Para envíos internacionales responde EMS para continuar con EMS INT.',
                    ];

                    return;
                }
                $this->simulatorContext['service'] = 'ems_int';
                $this->appendQuotePrompt('EMS INT', 'Tomaré Bolivia como país de origen. ¿A qué país se enviará el paquete?');
                $this->setSimulatorNode('quote_destination', 'DECISION NODE · PAÍS DE DESTINO');
                $this->simulatorPlaceholder = 'Escribe el país de destino...';

                return;

            case 'quote_service':
                if (str_contains($normalized, 'delivery') || $normalized === '2') {
                    $this->simulatorContext['service'] = 'delivery_express';
                } elseif (preg_match('/\b(ems|1)\b/u', $normalized) === 1) {
                    $this->simulatorContext['service'] = 'ems_nat';
                } else {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · SERVICIO NACIONAL',
                        'text' => 'Responde EMS o Delivery Express.',
                    ];

                    return;
                }
                if (isset($this->simulatorContext['origin'], $this->simulatorContext['destination'])) {
                    $this->appendQuotePrompt(
                        $this->simulatorContext['service'] === 'delivery_express' ? 'Delivery Express' : 'EMS NAT',
                        '¿Cuánto pesa el paquete? Indica el peso en kg.',
                    );
                    $this->setSimulatorNode('quote_weight', 'DECISION NODE · PESO');
                    $this->simulatorPlaceholder = 'Ejemplo: 2 kg';

                    return;
                }
                $this->appendQuotePrompt(
                    $this->simulatorContext['service'] === 'delivery_express' ? 'Delivery Express' : 'EMS NAT',
                    '¿Desde qué ciudad de Bolivia se enviará el paquete?',
                );
                $this->setSimulatorNode('quote_origin', 'DECISION NODE · CIUDAD DE ORIGEN');
                $this->simulatorPlaceholder = 'Escribe la ciudad de origen...';

                return;

            case 'quote_origin':
                $codes = app(PostarQuoteService::class)->destinationCodesFromText($value);
                if ($codes === []) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · CIUDAD DE ORIGEN',
                        'text' => 'No reconocí esa ciudad. Indica una ciudad de Bolivia, por ejemplo La Paz o Santa Cruz.',
                    ];

                    return;
                }
                $this->simulatorContext['origin'] = $codes[0];
                $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($codes[0]);
                $question = ($this->simulatorContext['scope'] ?? '') === 'internacional'
                    ? '¿A qué país se enviará el paquete?'
                    : '¿A qué ciudad de Bolivia se enviará el paquete?';
                $this->simulatorMessages[] = ['speaker' => 'bot', 'node' => 'DECISION NODE · DESTINO', 'text' => $question];
                $this->setSimulatorNode('quote_destination', 'DECISION NODE · DESTINO');
                $this->simulatorPlaceholder = 'Escribe la ciudad o país de destino...';

                return;

            case 'quote_destination':
                if (($this->simulatorContext['scope'] ?? 'nacional') === 'internacional') {
                    $this->simulatorContext['destination'] = $display;
                    $this->simulatorContext['destination_name'] = $display;
                } else {
                    $codes = app(PostarQuoteService::class)->destinationCodesFromText($value);
                    if ($codes === []) {
                        $this->simulatorMessages[] = [
                            'speaker' => 'bot',
                            'node' => 'DECISION NODE · DESTINO',
                            'text' => 'No reconocí esa ciudad. Indica una ciudad de destino de Bolivia, por ejemplo Oruro o Tarija.',
                        ];

                        return;
                    }
                    $destination = $codes[array_key_last($codes)];
                    $this->simulatorContext['destination'] = $destination;
                    $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($destination);
                }
                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'DECISION NODE · PESO',
                    'text' => '¿Cuánto pesa el paquete? Indica el peso en kg.',
                ];
                $this->setSimulatorNode('quote_weight', 'DECISION NODE · PESO');
                $this->simulatorPlaceholder = 'Ejemplo: 2 kg';

                return;

            case 'quote_weight':
                if (preg_match('/(?<!\d)(\d+(?:[.,]\d+)?)/u', $value, $weightMatch) !== 1 || (float) str_replace(',', '.', $weightMatch[1]) <= 0) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · PESO',
                        'text' => 'Indica un peso válido en kg, por ejemplo 2 kg.',
                    ];

                    return;
                }
                $weight = (float) str_replace(',', '.', $weightMatch[1]);
                $this->simulatorContext['weight'] = $weight;
                if (($this->simulatorContext['service'] ?? '') === 'delivery_express' && $weight > 5) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · LÍMITE DELIVERY EXPRESS',
                        'text' => 'Delivery Express admite hasta 5 kg. ¿Quieres continuar con EMS?',
                    ];
                    $this->setSimulatorNode('quote_oversize', 'DECISION NODE · LÍMITE DE PESO');

                    return;
                }
                $this->askSimulatorQuoteNextStep();

                return;

            case 'quote_oversize':
                if (preg_match('/\b(si|claro|continuar|ok|1|ems)\b/u', $normalized) !== 1) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'TERMINAL NODE · COTIZACIÓN CANCELADA',
                        'text' => 'No continué la cotización. Puedes volver al inicio para empezar otra consulta.',
                    ];
                    $this->setSimulatorNode('quote_terminal', 'TERMINAL NODE · COTIZACIÓN CANCELADA');

                    return;
                }
                $this->simulatorContext['service'] = 'ems_nat';
                $this->askSimulatorDimensions();

                return;

            case 'quote_mode':
                $decisionTree = app(WhatsAppDecisionTreeService::class);
                if ($decisionTree->isDeliveryModeQuestion($value)) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · MODALIDAD',
                        'text' => $decisionTree->deliveryModeExplanation(),
                    ];

                    return;
                }
                $mode = $decisionTree->deliveryModeFromText($value);
                if ($mode === null) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · MODALIDAD',
                        'text' => $decisionTree->deliveryModePrompt(),
                    ];

                    return;
                }
                $this->simulatorContext['mode'] = $mode;
                $this->finishSimulatorQuote();

                return;

            case 'quote_dimensions':
                $this->simulatorContext['dimensions'] = $display;
                $this->finishSimulatorQuote();

                return;

            case 'tracking_number':
                if (preg_match('/(?<![A-Z0-9])([A-Z]{1,3}[\s-]*\d{5,}[\s-]*[A-Z]{0,3})(?![A-Z0-9])/iu', $value, $trackingMatch) !== 1) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · NÚMERO DE GUÍA',
                        'text' => 'No reconocí el número. Envíame el código completo, por ejemplo RR123456789XX.',
                    ];

                    return;
                }
                $trackingNumber = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $trackingMatch[1]) ?? '');
                $this->simulatorContext['tracking_number'] = $trackingNumber;
                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'DECISION NODE · ÚLTIMO EVENTO',
                    'text' => 'Escribe el último evento para ver una respuesta de ejemplo: cartero, ventanilla, otro o no encontrada. Esta simulación no consulta SIOP ni SITRA.',
                ];
                $this->setSimulatorNode('tracking_event', 'DECISION NODE · CLASIFICACIÓN DEL EVENTO');

                return;

            case 'tracking_event':
                $trackingNumber = $this->simulatorContext['tracking_number'] ?? '';
                $result = match (true) {
                    preg_match('/\b(cartero|reparto|distribuci[oó]n)\b/u', $normalized) === 1 => 'courier',
                    preg_match('/\b(ventanilla|recojo|retiro)\b/u', $normalized) === 1 => 'pickup',
                    preg_match('/\b(no encontrada|no encontrado|no aparece|no existe)\b/u', $normalized) === 1 => 'not_found',
                    default => 'other',
                };
                $responses = [
                    'courier' => "La guía {$trackingNumber} está en proceso de distribución según el último evento registrado.",
                    'pickup' => "La guía {$trackingNumber} está disponible para recojo en ventanilla, según el último evento registrado.",
                    'other' => "La guía {$trackingNumber} continúa su proceso postal. La respuesta real dependerá del último evento registrado.",
                    'not_found' => "No encontré la guía {$trackingNumber} en los registros disponibles. Verifica el código y envíamelo nuevamente.",
                ];
                $this->simulatorContext['tracking_result'] = $result;
                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'TERMINAL NODE · RESPUESTA DE RASTREO',
                    'text' => $responses[$result].' Escribe otra guía para continuar o «inicio» para reiniciar.',
                ];
                $this->setSimulatorNode('tracking_terminal', 'TERMINAL NODE · RESPUESTA DE RASTREO');

                return;

            case 'tracking_terminal':
                if (preg_match('/\b(inicio|reiniciar|menu)\b/u', $normalized) === 1) {
                    $this->resetSimulator();

                    return;
                }
                if ($this->startSimulatorFlowFromTrigger($value)) {
                    return;
                }
                if (preg_match('/\b(no|terminar|listo|gracias)\b/u', $normalized) === 1) {
                    $this->simulatorContext = [];
                    $this->setSimulatorNode('root', 'ROOT NODE · SALUDO Y BIENVENIDA');
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'ROOT NODE · SALUDO Y BIENVENIDA',
                        'text' => 'De acuerdo. Cuando necesites otra consulta, escribe una frase de cotización o rastreo.',
                    ];

                    return;
                }
                $this->simulatorContext = ['flow' => 'tracking'];
                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'DECISION NODE · NÚMERO DE GUÍA',
                    'text' => 'Envíame el número de guía del siguiente paquete, o escribe «inicio» para volver al saludo.',
                ];
                $this->setSimulatorNode('tracking_number', 'DECISION NODE · NÚMERO DE GUÍA');
                $this->simulatorPlaceholder = 'Ejemplo: RR123456789XX';

                return;

            case 'quote_terminal':
                if (preg_match('/\b(inicio|reiniciar|menu)\b/u', $normalized) === 1) {
                    $this->resetSimulator();

                    return;
                }
                $this->simulatorNode = 'root';
                $this->simulatorContext = [];
                if (! $this->startSimulatorFlowFromTrigger($value)) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'ROOT NODE · SALUDO Y BIENVENIDA',
                        'text' => 'Para otra consulta, escribe una frase de cotización o rastreo que coincida con un detonante activo.',
                    ];
                }

                return;
        }
    }

    private function askSimulatorQuoteNextStep(): void
    {
        if (($this->simulatorContext['service'] ?? '') === 'delivery_express') {
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'DECISION NODE · MODALIDAD',
                'text' => "¿Qué modalidad prefieres?\n1. Puerta a puerta\n2. Puerta a ventanilla\n3. Ventanilla a ventanilla.",
            ];
            $this->setSimulatorNode('quote_mode', 'DECISION NODE · MODALIDAD');

            return;
        }

        $this->askSimulatorDimensions();
    }

    private function askSimulatorDimensions(): void
    {
        $this->simulatorMessages[] = [
            'speaker' => 'bot',
            'node' => 'DECISION NODE · DIMENSIONES',
            'text' => '¿Deseas incluir dimensiones? Envía largo x ancho x alto en cm o responde «no».',
        ];
        $this->setSimulatorNode('quote_dimensions', 'DECISION NODE · DIMENSIONES');
        $this->simulatorPlaceholder = 'Ejemplo: 20 x 15 x 10 cm, o responde no...';
    }

    private function finishSimulatorQuote(): void
    {
        $service = match ($this->simulatorContext['service'] ?? '') {
            'ems_int' => 'EMS INT',
            'delivery_express' => 'Delivery Express',
            default => 'EMS NAT',
        };
        $message = "Fin de la demostración para {$service}. En WhatsApp, aquí se consulta el tarifario y solo se envía un precio verificado.";
        if ($service === 'Delivery Express') {
            try {
                $quote = app(DeliveryExpressQuoteService::class)->quote(
                    (float) ($this->simulatorContext['weight'] ?? 0),
                    (string) ($this->simulatorContext['destination'] ?? ''),
                    (string) ($this->simulatorContext['mode'] ?? ''),
                    isset($this->simulatorContext['origin']) ? (string) $this->simulatorContext['origin'] : null,
                );
                $currency = (string) ($quote['currency'] ?? 'BOB');
                $currencyLabel = $currency === 'BOB' ? 'Bs' : $currency;
                $origin = $this->simulatorContext['origin_name']
                    ?? $this->simulatorDestinationName((string) $this->simulatorContext['origin']);
                $destination = $this->simulatorContext['destination_name']
                    ?? $this->simulatorDestinationName((string) $this->simulatorContext['destination']);
                $weight = number_format((float) $this->simulatorContext['weight'], 2, '.', '');
                $mode = str_replace('_', ' ', (string) $this->simulatorContext['mode']);
                $message = sprintf(
                    'Tarifa oficial verificada de Delivery Express: %s %s para %s kg, desde %s hasta %s, modalidad %s.',
                    $currencyLabel,
                    number_format((float) $quote['price'], 2, '.', ''),
                    $weight,
                    $origin,
                    $destination,
                    $mode,
                );
                $this->simulatorContext['verified_quote'] = [
                    'price' => (float) $quote['price'],
                    'currency' => $currency,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $message = 'No pude verificar una tarifa para esos datos. No mostraré un precio estimado. Intenta nuevamente más tarde o consulta con una persona.';
            }
        }
        $this->simulatorMessages[] = [
            'speaker' => 'bot',
            'node' => 'TERMINAL NODE · COTIZACIÓN',
            'text' => $message,
        ];
        $this->setSimulatorNode('quote_terminal', 'TERMINAL NODE · COTIZACIÓN');
    }

    private function simulatorDestinationName(string $code): string
    {
        return match ($code) {
            'nacional_beni' => 'Beni',
            'nacional_chuquisaca' => 'Chuquisaca',
            'nacional_cochabamba' => 'Cochabamba',
            'nacional_la_paz' => 'La Paz',
            'nacional_oruro' => 'Oruro',
            'nacional_pando' => 'Pando',
            'nacional_potosi' => 'Potosí',
            'nacional_santa_cruz' => 'Santa Cruz',
            'nacional_tarija' => 'Tarija',
            default => Str::title(str_replace('_', ' ', preg_replace('/^nacional_/', '', $code) ?? $code)),
        };
    }

    private function appendQuotePrompt(string $key, string $prompt): void
    {
        $this->simulatorMessages[] = [
            'speaker' => 'bot',
            'node' => 'DECISION NODE · DATOS DE ENVÍO',
            'text' => $this->withTemplate($this->activeTemplate('quote', $key), $prompt),
        ];
    }

    private function setSimulatorNode(string $node, string $label): void
    {
        $this->simulatorNode = $node;
        $this->simulatorActiveLabel = $label;
    }

    private function activeTemplate(string $type, string $key): ?string
    {
        return AutomaticResponse::query()
            ->where('type', $type)
            ->where('key', $key)
            ->where('active', true)
            ->latest()
            ->value('template');
    }

    private function withTemplate(?string $template, string $prompt): string
    {
        $template = trim((string) $template);

        return $template === '' ? $prompt : $template."\n\n".$prompt;
    }

    private function normalizeSimulatorText(string $text): string
    {
        $normalized = mb_strtolower(Str::ascii(trim($text)), 'UTF-8');

        return preg_replace('/\bpauqu?ete\b/u', 'paquete', $normalized) ?? $normalized;
    }

    public function toggleDecisionFlow(string $flow): void
    {
        abort_unless(isset(self::FLOWS[$flow]), 404);

        $setting = self::FLOWS[$flow]['setting'];
        $current = filter_var(
            DB::table('app_settings')->where('key', $setting)->value('value') ?? '1',
            FILTER_VALIDATE_BOOLEAN,
        );
        $next = ! $current;
        DB::table('app_settings')->updateOrInsert(
            ['key' => $setting],
            ['value' => $next ? '1' : '0', 'created_at' => now(), 'updated_at' => now()],
        );

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Flujo automático actualizado',
            'category' => 'automatizacion',
            'description' => ($next ? 'Se activó' : 'Se desactivó').' el árbol: '.self::FLOWS[$flow]['name'],
            'ip_address' => request()->ip(),
        ]);
        session()->flash(
            'decisionFlowUpdated',
            self::FLOWS[$flow]['name'].($next ? ' activado.' : ' desactivado.'),
        );
    }

    public function render()
    {
        $flowSettings = DB::table('app_settings')
            ->whereIn('key', array_column(self::FLOWS, 'setting'))
            ->pluck('value', 'key');
        $decisionFlows = collect(self::FLOWS)
            ->map(fn (array $flow) => $flow + [
                'active' => filter_var($flowSettings->get($flow['setting'], '1'), FILTER_VALIDATE_BOOLEAN),
            ])
            ->all();

        return view('livewire.automatic-decision-trees', [
            'decisionFlows' => $decisionFlows,
        ]);
    }
}
