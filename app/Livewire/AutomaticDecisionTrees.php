<?php

namespace App\Livewire;

use App\Models\AutomaticResponse;
use App\Models\PostalPackage;
use App\Services\AutomaticResponseMatcher;
use App\Services\DecisionTreeInputNormalizer;
use App\Services\DeliveryExpressQuoteService;
use App\Services\PostalEventPresenter;
use App\Services\PostalTrackingNumberParser;
use App\Services\PostarQuoteService;
use App\Services\WhatsAppDecisionTreeService;
use App\Services\LanguageService;
use App\Services\OllamaService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Illuminate\Support\Str;
use Throwable;

class AutomaticDecisionTrees extends Component
{
    private const MAX_SIMULATOR_INPUT = 2000;
    private const MAX_SIMULATOR_MESSAGES = 24;
    private const MAX_SIMULATOR_CONTEXT_TEXT = 500;
    private const ALLOWED_SIMULATOR_NODES = ['root', 'quote_scope', 'quote_service', 'quote_origin', 'quote_destination', 'quote_weight', 'quote_oversize', 'quote_mode', 'quote_dimensions', 'quote_terminal', 'tracking_number', 'tracking_scope', 'tracking_terminal'];
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

    public string $simulatorLanguage = 'es';

    public string $simulatorInput = '';

    public string $simulatorPlaceholder = 'Escribe tu respuesta para avanzar en la simulación...';

    public function mount(): void
    {
        $this->resetSimulator();
    }

    private function startSimulatorFlowFromTrigger(string $message): bool
    {
        $normalizedMessage = $this->normalizeSimulatorText($message);
        $isGreeting = in_array($normalizedMessage, ['ola', 'olá', 'oi', 'hello', 'hellow', 'helo', 'hi', 'hey'], true)
            || preg_match('/^\s*(good morning|good afternoon|good evening|bom dia|boa tarde|boa noite|bonjour|salut|guten morgen|guten tag|ciao|buongiorno|buonasera|привет|здравствуйте|你好|您好|こんにちは|おはようございます)[\s,!?.]*$/u', $normalizedMessage) === 1;
        if ($isGreeting) {
            $greeting = $this->activeTemplate('event', 'saludo')
                ?? '👋 ¡Hola! Soy el asistente de Correos de Bolivia. ¿Deseas cotizar un envío o rastrear un paquete? 😊';
            $this->setSimulatorNode('root', 'ROOT NODE · SALUDO Y BIENVENIDA');
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'ROOT NODE · SALUDO Y BIENVENIDA',
                'text' => $greeting,
            ];

            return true;
        }
        $response = app(AutomaticResponseMatcher::class)->match($message);
        $intent = in_array($response?->type, ['quote', 'tracking'], true)
            ? $response->type
            : app(WhatsAppDecisionTreeService::class)->detectIntent($message);

        if ($response?->type === 'event' && Str::lower($response->key) === 'saludo' && $intent === null) {
            $this->setSimulatorNode('root', 'ROOT NODE · SALUDO Y BIENVENIDA');
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'ROOT NODE · SALUDO Y BIENVENIDA',
                'text' => trim($response->template),
            ];

            return true;
        }

        if ($intent === 'quote') {
            $key = $response?->type === 'quote'
                ? mb_strtolower(Str::ascii(trim($response->key)), 'UTF-8')
                : '';
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
            $normalizedInitial = $this->normalizeSimulatorText($message);
            $initialInternationalDestination = app(WhatsAppDecisionTreeService::class)
                ->internationalDestinationFromText($message);
            if ($initialInternationalDestination !== null) {
                $service = 'ems_int';
                $scope = 'internacional';
                $this->simulatorContext['destination'] = $initialInternationalDestination['code'];
                $this->simulatorContext['destination_name'] = $initialInternationalDestination['name'];
            }
            if ($service === null && preg_match('/\b(delivery|express)\b/u', $normalizedInitial) === 1) {
                $service = 'delivery_express';
                $scope = 'nacional';
            } elseif ($service === null && preg_match('/\bnacional\b/u', $normalizedInitial) === 1) {
                $service = 'ems_nat';
                $scope = 'nacional';
            } elseif ($service === null && preg_match('/\b(internacional|exterior)\b/u', $normalizedInitial) === 1) {
                $service = 'ems_int';
                $scope = 'internacional';
            }
            $this->simulatorContext = ['flow' => 'quote', 'initial_message' => $message];
            $initialWeight = $this->simulatorWeightFromText($message);
            if ($initialWeight !== null) {
                $this->simulatorContext['pending_weight'] = $initialWeight;
            }
            if ($scope === null) {
                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'DECISION NODE · ALCANCE DEL ENVÍO',
                    'text' => $this->withTemplate(
                        $response?->template,
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
                if (isset($this->simulatorContext['destination'])) {
                    $destinationName = (string) ($this->simulatorContext['destination_name'] ?? $this->simulatorContext['destination']);
                    $this->appendQuotePrompt('EMS INT', 'Reconocí '.$destinationName.' como destino internacional. ¿Cuánto pesa el paquete? Indica el peso en kg.');
                    $this->setSimulatorNode('quote_weight', 'DECISION NODE · PESO');
                    $this->simulatorPlaceholder = 'Ejemplo: 1 kg';
                    return true;
                }
                $this->simulatorMessages[] = [
                    'speaker' => 'bot',
                    'node' => 'DECISION NODE · DESTINO INTERNACIONAL',
                    'text' => $this->withTemplate($response?->template, 'Tomaré Bolivia como origen. ¿A qué país se enviará el paquete?'),
                ];
                $this->setSimulatorNode('quote_destination', 'DECISION NODE · PAÍS DE DESTINO');
                $this->simulatorPlaceholder = 'Escribe el país de destino...';

                return true;
            }

            $route = $this->simulatorRouteFromText($message);
            if ($route !== null) {
                $this->simulatorContext['origin'] = $route['origin'];
                $this->simulatorContext['destination'] = $route['destination'];
                $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($route['origin']);
                $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($route['destination']);
                $templateKey = match ($service) {
                    'delivery_express' => 'Delivery Express',
                    default => 'EMS NAT',
                };
                if (isset($this->simulatorContext['pending_weight'])) {
                    $weight = (float) $this->simulatorContext['pending_weight'];
                    $this->appendQuotePrompt($templateKey, 'Tomaré el peso indicado: '.number_format($weight, 2, '.', '').' kg.');
                    $this->advanceSimulatorAfterWeight($weight);

                    return true;
                }
                $this->appendQuotePrompt($templateKey, '¿Cuánto pesa el paquete? Indica el peso en kg.');
                $this->setSimulatorNode('quote_weight', 'DECISION NODE · PESO');
                $this->simulatorPlaceholder = 'Ejemplo: 2 kg';

                return true;
            }

            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'DECISION NODE · ORIGEN NACIONAL',
                'text' => $this->withTemplate($response?->template, '¿Desde qué ciudad de Bolivia se enviará el paquete?'),
            ];
            $this->setSimulatorNode('quote_origin', 'DECISION NODE · CIUDAD DE ORIGEN');
            $this->simulatorPlaceholder = 'Escribe la ciudad de origen...';

            return true;
        }

        if ($intent === 'tracking') {
            $scope = in_array($response?->scope, ['nacional', 'internacional'], true)
                ? $response->scope
                : $this->simulatorTrackingScope($message);
            $this->simulatorContext = ['flow' => 'tracking', 'scope' => $scope];
            $trackingTemplate = $response?->type === 'tracking'
                ? trim((string) $response->template)
                : '';
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'DECISION NODE · NÚMERO DE GUÍA',
                'text' => $trackingTemplate !== ''
                    ? $trackingTemplate
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
        $this->simulatorLanguage = 'es';
        $this->simulatorInput = '';
        $this->simulatorPlaceholder = 'Escribe una frase para cotizar o rastrear...';
    }

    public function sendSimulatorMessage(): void
    {
        $this->compactSimulatorState();
        $message = trim((string) $this->simulatorInput);
        if ($message === '') {
            return;
        }
        if (mb_strlen($message, 'UTF-8') > self::MAX_SIMULATOR_INPUT) {
            $this->simulatorInput = '';
            $this->simulatorMessages[] = ['speaker' => 'bot', 'node' => 'VALIDATION', 'text' => 'El mensaje es demasiado largo. Limítalo a 2.000 caracteres.'];
            $this->compactSimulatorState();
            return;
        }

        $this->simulatorInput = '';
        $beforeReplyCount = count($this->simulatorMessages);
        if ($this->simulatorLanguage === 'es') {
            $detected = app(LanguageService::class)->detect($message);
            if ($detected !== 'es') {
                $this->simulatorLanguage = $detected;
            }
        }
        $treeMessage = $this->translateSimulatorInput($message);
        $this->advanceSimulator($treeMessage, $message);
        $this->translateSimulatorReplies($beforeReplyCount);
    }

    private function translateSimulatorInput(string $message): string
    {
        if (! in_array($this->simulatorLanguage, ['en', 'pt', 'fr', 'de', 'it', 'ru', 'zh', 'ja'], true)) return $message;

        // Fallback local para las intenciones frecuentes cuando Ollama no está disponible.
        $normalized = $this->normalizeSimulatorText($message);
        $normalized = preg_replace('/\bhow much does it cost to send\b/u', 'cuanto cuesta enviar', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bhow much is it to send\b/u', 'cuanto cuesta enviar', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bi want to send\b/u', 'quiero enviar', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bi would like to send\b/u', 'quisiera enviar', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bship\b|\bshipping\b|\bshipment\b/u', 'envio', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bpackage\b|\bparcel\b/u', 'paquete', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bsend\b/u', 'enviar', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bcost\b|\bprice\b|\brate\b/u', 'cuesta', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bto\b/u', 'a', $normalized) ?? $normalized;

        if ($this->simulatorLanguage === 'pt') {
            $normalized = preg_replace('/\bquanto me custaria enviar\b/u', 'cuanto cuesta enviar', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bquanto custa enviar\b/u', 'cuanto cuesta enviar', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bum pacote\b|\bo pacote\b|\bpacote\b/u', 'un paquete', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bpara o\b|\bpara a\b|\bpara\b/u', 'a', $normalized) ?? $normalized;
            $normalized = preg_replace('/\benviar\b/u', 'enviar', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bolá\b|\bola\b|\boi\b/u', '', $normalized) ?? $normalized;
        }

        $patterns = [
            'fr' => ['/combien coûte.*?(?:envoyer|expédier).*?(?:colis|paquet).*?\b(?:au|en)\s+([[:alpha:]À-ÿ]+)/u'],
            'de' => ['/wie viel kostet.*?paket.*?(?:nach|in)\s+([[:alpha:]Ä-ÿ]+)/u'],
            'it' => ['/quanto costa.*?(?:spedire|inviare).*?pacc[oa].*?(?:in|a)\s+([[:alpha:]À-ÿ]+)/u'],
            'ru' => ['/сколько стоит.*?посылк.*?(?:в|до)\s+([^,?]+)/u'],
            'zh' => ['/.*(?:多少|多少钱).*包裹.*?(?:到|寄到)\s*([^，。！？]+)/u'],
            'ja' => ['/.*(?:いくら|料金).*?(?:荷物|小包).*?(?:チリ|アルゼンチン|ブラジル|コロンビア)/u'],
        ];
        $patternInput = in_array($this->simulatorLanguage, ['ru', 'zh', 'ja'], true)
            ? mb_strtolower($message, 'UTF-8')
            : $normalized;
        foreach ($patterns[$this->simulatorLanguage] ?? [] as $pattern) {
            if (preg_match($pattern, $patternInput, $matches) === 1) {
                $destination = isset($matches[1]) ? trim($matches[1]) : '';
                $destination = $destination !== '' ? ' a '.$destination : '';
                return 'cuanto cuesta enviar un paquete'.$destination;
            }
        }

        return $normalized;
    }

    private function translateSimulatorReplies(int $fromIndex): void
    {
        if ($this->simulatorLanguage === 'es') {
            return;
        }
        foreach (array_keys($this->simulatorMessages) as $index) {
            if ($index < $fromIndex || ($this->simulatorMessages[$index]['speaker'] ?? '') !== 'bot') {
                continue;
            }
            try {
                $text = (string) ($this->simulatorMessages[$index]['text'] ?? '');
                $localGreeting = str_contains($text, 'Hola! Soy') ? match ($this->simulatorLanguage) {
                    'en' => '👋 Hello! I am the Correos de Bolivia assistant. Would you like to quote a shipment or track a package? 😊',
                    'pt' => '👋 Olá! Sou o assistente dos Correios da Bolívia. Você quer cotar um envio ou rastrear um pacote? 😊',
                    'fr' => '👋 Bonjour ! Je suis l’assistant de la Poste de Bolivie. Voulez-vous obtenir un devis d’envoi ou suivre un colis ? 😊',
                    'de' => 'Hallo! Ich bin der Assistent der Post von Bolivien. MÃ¶chten Sie einen Versandpreis berechnen oder ein Paket verfolgen?',
                    'it' => 'Ciao! Sono l’assistente delle Poste della Bolivia. Vuoi calcolare il costo di una spedizione o rintracciare un pacco?',
                    'ru' => 'Ð—Ð´Ñ€Ð°Ð²ÑÑ‚Ð²ÑƒÐ¹Ñ‚Ðµ! Ð¯ Ð°ÑÑÐ¸ÑÑ‚ÐµÐ½Ñ‚ ÐŸÐ¾Ñ‡Ñ‚Ñ‹ Ð‘Ð¾Ð»Ð¸Ð²Ð¸Ð¸. Ð¥Ð¾Ñ‚Ð¸Ñ‚Ðµ Ñ€Ð°ÑÑÑ‡Ð¸Ñ‚Ð°Ñ‚ÑŒ Ð¾Ñ‚Ð¿Ñ€Ð°Ð²ÐºÑƒ Ð¸Ð»Ð¸ Ð¾Ñ‚ÑÐ»ÐµÐ´Ð¸Ñ‚ÑŒ Ð¿Ð¾ÑÑ‹Ð»ÐºÑƒ?',
                    'zh' => 'ä½ å¥½ï¼æˆ‘æ˜¯çŽ»åˆ©ç»´äºšé‚®æ”¿çš„åŠ©ç�†ã€‚æ‚¨æƒ³è¦è®¡ç®—é‚®ä»¶è´¹ç”¨è¿˜æ˜¯è¿½è¸ªåŒ…è£¹ï¼Ÿ',
                    'ja' => 'ã“ã‚“ã«ã¡ã¯ï¼ãƒœãƒªãƒ“ã‚¢éƒµä¾¿å±€ã®ã‚¢ã‚·ã‚¹ã‚¿ãƒ³ãƒˆã§ã™ã€‚é€ã‚Šã¾ãŸã¯è·ç‰©ã‚’è¿½è·¡ã—ã¾ã™ã‹ï¼Ÿ',
                    default => null,
                } : null;
                if ($localGreeting !== null) {
                    $this->simulatorMessages[$index]['text'] = $localGreeting;
                    continue;
                }
                $localFallback = $this->localSimulatorReplyTranslation($text);
                if ($localFallback !== null) {
                    $this->simulatorMessages[$index]['text'] = $localFallback;
                    continue;
                }
                try {
                    $this->simulatorMessages[$index]['text'] = app(OllamaService::class)
                        ->translate($text, $this->simulatorLanguage);
                } catch (Throwable $exception) {
                    report($exception);
                    if ($localFallback !== null) {
                        $this->simulatorMessages[$index]['text'] = $localFallback;
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function localSimulatorReplyTranslation(string $text): ?string
    {
        $destinationPrompt = str_contains($text, 'Tomar') || str_contains($text, 'Tomaré');
        if ($destinationPrompt) {
            return match ($this->simulatorLanguage) {
                'fr' => 'Je prendrai la Bolivie comme origine. Vers quel pays le colis sera-t-il envoyé ?',
                'de' => 'Ich nehme Bolivien als Herkunftsland. In welches Land wird das Paket gesendet?',
                'it' => 'Considererò la Bolivia come origine. In quale paese verrà inviato il pacco?',
                'ru' => 'Я укажу Боливию как страну отправления. В какую страну будет отправлена посылка?',
                'zh' => '我将玻利维亚作为寄件国。包裹将寄往哪个国家？',
                'ja' => 'ボリビアを発送元とします。荷物はどの国に送りますか？',
                default => null,
            };
        }

        if ($this->simulatorLanguage === 'pt') {
            if (str_contains($text, 'Claro') && str_contains($text, 'Tomar')) {
                return '📦 Claro! Vou ajudar você a calcular o envio. O preço é confirmado somente com a tarifa vigente. ✉️\nVou considerar a Bolívia como origem. Para qual país o pacote será enviado?';
            }
            if (str_contains($text, 'Tomar') && str_contains($text, 'país')) {
                return 'Vou considerar a Bolívia como país de origem. Para qual país o pacote será enviado?';
            }
            if (str_contains($text, 'Tomar')) {
                return 'Vou considerar a Bolívia como origem. Para qual país o pacote será enviado?';
            }
            if (str_contains($text, 'Cuánto pesa') || str_contains($text, 'peso')) {
                return 'Quanto pesa o pacote? Informe o peso em kg.';
            }
            if (str_contains($text, 'dimensiones')) {
                return 'Deseja incluir as dimensões? Envie comprimento x largura x altura em cm ou responda “não”.';
            }
        }

        if ($this->simulatorLanguage !== 'en') {
            return null;
        }

        if (preg_match('/^Tarifa oficial verificada de EMS INT: Bs\.?\s*([0-9.,]+) para ([0-9.,]+) kg, desde Bolivia hasta (.+)\.$/u', $text, $matches) === 1) {
            return sprintf(
                'Official verified EMS INT rate: Bs. %s for %s kg, from Bolivia to %s.',
                $matches[1],
                $matches[2],
                $matches[3],
            );
        }

        if (preg_match('/^Tarifa oficial verificada de EMS NAT: Bs\.?\s*([0-9.,]+) para ([0-9.,]+) kg, desde (.+) hasta (.+)\.$/u', $text, $matches) === 1) {
            return sprintf(
                'Official verified EMS NAT rate: Bs. %s for %s kg, from %s to %s.',
                $matches[1],
                $matches[2],
                $matches[3],
                $matches[4],
            );
        }

        $translations = [
            'Tomaré Bolivia como origen. ¿A qué país se enviará el paquete?' =>
                'I will use Bolivia as the origin. Which country will the package be sent to?',
            'Tomaré Bolivia como país de origen. ¿A qué país se enviará el paquete?' =>
                'I will use Bolivia as the country of origin. Which country will the package be sent to?',
            '¿Cuánto pesa el paquete? Indica el peso en kg.' =>
                'How much does the package weigh? Enter the weight in kg.',
            '¿Deseas incluir dimensiones? Envía largo x ancho x alto en cm o responde «no».' =>
                'Would you like to include the dimensions? Send length x width x height in cm or reply “no”.',
            '¿Qué tipo de envío deseas cotizar?\n1. Nacional (EMS NAT)\n2. Internacional (EMS INT)\n3. Delivery Express' =>
                "What type of shipment would you like to quote?\n1. National (EMS NAT)\n2. International (EMS INT)\n3. Delivery Express",
        ];

        return $translations[$text] ?? null;
    }

    private function advanceSimulator(string $value, string $display): void
    {
        $this->compactSimulatorState();
        $this->simulatorMessages[] = ['speaker' => 'client', 'node' => null, 'text' => $display];
        $normalized = $this->normalizeSimulatorText($value);

        $trackingIntent = preg_match('/\b(detall\w*|informaci[oó]n|datos|nombre|consulta\w*|averiguar|saber|rastrea\w*|seguimiento|estado|ubicaci[oó]n)\b/u', $normalized) === 1
            && preg_match('/\b(paquet\w*|env[ií]o|gu[ií]a)\b/u', $normalized) === 1;
        $humanIntent = preg_match('/\b(asesor|agente|humano|operador|atenci[oó]n al cliente|servicio al cliente|hablar con|persona real)\b/u', $normalized) === 1;
        if ($humanIntent) {
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'HUMAN HANDOFF',
                'text' => 'Pausé las respuestas automáticas para que un asesor pueda continuar esta conversación desde el CRM.',
            ];
            $this->setSimulatorNode('root', 'ROOT NODE · DERIVACIÓN A HUMANO');

            return;
        }
        if ($trackingIntent && $this->simulatorNode !== 'tracking_number') {
            $this->simulatorContext = ['flow' => 'tracking', 'scope' => null];
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'DECISION NODE · NÚMERO DE GUÍA',
                'text' => 'Entendido. Envíame el número completo de guía del paquete y consultaré su último estado.',
            ];
            $this->setSimulatorNode('tracking_number', 'DECISION NODE · NÚMERO DE GUÍA');
            $this->simulatorPlaceholder = 'Ejemplo: RR123456789XX';

            return;
        }

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

                $route = $this->simulatorRouteFromText($value);
                if ($route !== null) {
                    $this->simulatorContext['scope'] = 'nacional';
                    $this->simulatorContext['origin'] = $route['origin'];
                    $this->simulatorContext['destination'] = $route['destination'];
                    $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($route['origin']);
                    $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($route['destination']);
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
                $route = $this->simulatorRouteFromText($value);
                if ($route === null) {
                    $route = $this->simulatorRouteFromText(
                        (string) ($this->simulatorContext['initial_message'] ?? ''),
                    );
                }
                if ($service !== 'ems_int' && $route !== null) {
                    $this->simulatorContext['origin'] = $route['origin'];
                    $this->simulatorContext['destination'] = $route['destination'];
                    $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($route['origin']);
                    $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($route['destination']);
                    $templateKey = match ($service) {
                        'delivery_express' => 'Delivery Express',
                        default => 'EMS NAT',
                    };
                    if (isset($this->simulatorContext['pending_weight'])) {
                        $weight = (float) $this->simulatorContext['pending_weight'];
                        $this->appendQuotePrompt($templateKey, 'Tomaré el peso indicado: '.number_format($weight, 2, '.', '').' kg.');
                        $this->advanceSimulatorAfterWeight($weight);

                        return;
                    }
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
                    $route = $this->simulatorRouteFromText($value);
                    if ($route !== null && ($this->simulatorContext['scope'] ?? null) === 'nacional') {
                        $this->simulatorContext['origin'] = $route['origin'];
                        $this->simulatorContext['destination'] = $route['destination'];
                        $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($route['origin']);
                        $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($route['destination']);
                    }
                    $weight = $this->simulatorWeightFromText($value);
                    if ($weight !== null) {
                        $this->simulatorContext['pending_weight'] = $weight;
                    }
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · SERVICIO NACIONAL',
                        'text' => 'Responde EMS o Delivery Express.',
                    ];

                    return;
                }
                if (isset($this->simulatorContext['origin'], $this->simulatorContext['destination'])) {
                    if (isset($this->simulatorContext['pending_weight'])) {
                        $weight = (float) $this->simulatorContext['pending_weight'];
                        $this->appendQuotePrompt(
                            $this->simulatorContext['service'] === 'delivery_express' ? 'Delivery Express' : 'EMS NAT',
                            'Tomaré el peso indicado: '.number_format($weight, 2, '.', '').' kg.',
                        );
                        $this->advanceSimulatorAfterWeight($weight);

                        return;
                    }
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
                $route = $this->simulatorRouteFromText($value);
                if (($this->simulatorContext['scope'] ?? 'nacional') === 'nacional' && $route !== null) {
                    $this->simulatorContext['origin'] = $route['origin'];
                    $this->simulatorContext['destination'] = $route['destination'];
                    $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($route['origin']);
                    $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($route['destination']);
                    $weight = $this->simulatorWeightFromText($value)
                        ?? (isset($this->simulatorContext['pending_weight']) ? (float) $this->simulatorContext['pending_weight'] : null);
                    if ($weight !== null) {
                        $templateKey = ($this->simulatorContext['service'] ?? '') === 'delivery_express'
                            ? 'Delivery Express'
                            : 'EMS NAT';
                        $this->appendQuotePrompt($templateKey, 'Tomaré el peso indicado: '.number_format($weight, 2, '.', '').' kg.');
                        $this->advanceSimulatorAfterWeight($weight);

                        return;
                    }
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · PESO',
                        'text' => '¿Cuánto pesa el paquete? Indica el peso en kg.',
                    ];
                    $this->setSimulatorNode('quote_weight', 'DECISION NODE · PESO');
                    $this->simulatorPlaceholder = 'Ejemplo: 2 kg';

                    return;
                }

                $codes = $this->simulatorDestinationCodesFromText($value);
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
                if (preg_match('/\b(nacional|bolivia)\b/u', $normalized) === 1
                    && preg_match('/\b(me confundi|equivoc|no era|nacional)\b/u', $normalized) === 1) {
                    unset($this->simulatorContext['destination'], $this->simulatorContext['destination_name']);
                    $this->simulatorContext['scope'] = 'nacional';
                    $this->simulatorContext['service'] = 'ems_nat';
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · CIUDAD DE ORIGEN',
                        'text' => 'Entendido, cambiaré la cotización a nacional. ¿Desde qué ciudad de Bolivia se enviará el paquete?',
                    ];
                    $this->setSimulatorNode('quote_origin', 'DECISION NODE · CIUDAD DE ORIGEN');

                    return;
                }
                if (($this->simulatorContext['scope'] ?? 'nacional') === 'internacional') {
                    $this->simulatorContext['destination'] = $display;
                    $this->simulatorContext['destination_name'] = $display;
                } else {
                    $route = $this->simulatorRouteFromText($value);
                    if ($route !== null) {
                        $this->simulatorContext['origin'] = $route['origin'];
                        $this->simulatorContext['destination'] = $route['destination'];
                        $this->simulatorContext['origin_name'] = $this->simulatorDestinationName($route['origin']);
                        $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($route['destination']);
                    }

                    $codes = $route === null
                        ? $this->simulatorDestinationCodesFromText($value)
                        : [];
                    if ($codes === []) {
                        if ($route === null) {
                            $this->simulatorMessages[] = [
                                'speaker' => 'bot',
                                'node' => 'DECISION NODE · DESTINO',
                                'text' => 'No reconocí esa ciudad. Indica una ciudad de destino de Bolivia, por ejemplo Oruro o Tarija.',
                            ];

                            return;
                        }
                    } else {
                        $destination = $codes[array_key_last($codes)];
                        $this->simulatorContext['destination'] = $destination;
                        $this->simulatorContext['destination_name'] = $this->simulatorDestinationName($destination);
                    }
                }
                $weight = $this->simulatorWeightFromText($value)
                    ?? (isset($this->simulatorContext['pending_weight']) ? (float) $this->simulatorContext['pending_weight'] : null);
                if ($weight !== null) {
                    $templateKey = match ($this->simulatorContext['service'] ?? '') {
                        'delivery_express' => 'Delivery Express',
                        'ems_int' => 'EMS INT',
                        default => 'EMS NAT',
                    };
                    $this->appendQuotePrompt($templateKey, 'Tomaré el peso indicado: '.number_format($weight, 2, '.', '').' kg.');
                    $this->advanceSimulatorAfterWeight($weight);

                    return;
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
                if (preg_match('/\b(nacional|bolivia)\b/u', $normalized) === 1
                    && preg_match('/\b(me confundi|equivoc|no era|cambio|nacional)\b/u', $normalized) === 1) {
                    unset($this->simulatorContext['destination'], $this->simulatorContext['destination_name']);
                    $this->simulatorContext['scope'] = 'nacional';
                    $this->simulatorContext['service'] = 'ems_nat';
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · CIUDAD DE ORIGEN',
                        'text' => 'Entendido, cambiaré la cotización a nacional. ¿Desde qué ciudad de Bolivia se enviará el paquete?',
                    ];
                    $this->setSimulatorNode('quote_origin', 'DECISION NODE · CIUDAD DE ORIGEN');

                    return;
                }
                $weight = app(\App\Services\PackageWeightParser::class)->parse($value);
                if (! is_float($weight)) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · PESO',
                        'text' => 'Indica un único peso mayor que cero, por ejemplo 2 kg o 500 g. No envíes precios ni varias opciones de peso.',
                    ];

                    return;
                }
                $this->advanceSimulatorAfterWeight($weight);

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
                $dimensions = $this->parseSimulatorDimensions($value);
                if ($dimensions === false) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · DIMENSIONES',
                        'text' => 'Envía las dimensiones como largo x ancho x alto en cm, por ejemplo 20 x 15 x 10, o responde «no».',
                    ];

                    return;
                }
                $this->simulatorContext['dimensions'] = $dimensions;
                $this->finishSimulatorQuote();

                return;

            case 'tracking_number':
                $trackingNumber = app(PostalTrackingNumberParser::class)->extract($value);
                if ($trackingNumber === null) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · NÚMERO DE GUÍA',
                        'text' => 'No reconocí el número. Envíame el código completo, por ejemplo RR123456789XX o C0001A89911BO.',
                    ];

                    return;
                }
                $this->simulatorContext['tracking_number'] = $trackingNumber;
                $this->lookupSimulatorTracking($trackingNumber);

                return;

            case 'tracking_scope':
                $scope = $this->simulatorTrackingScope($value);
                if ($scope === null) {
                    $this->simulatorMessages[] = [
                        'speaker' => 'bot',
                        'node' => 'DECISION NODE · ALCANCE DEL RASTREO',
                        'text' => 'Indica si el paquete es nacional o internacional.',
                    ];

                    return;
                }
                $this->simulatorContext['scope'] = $scope;
                $this->lookupSimulatorTracking((string) $this->simulatorContext['tracking_number']);

                return;

            case 'tracking_terminal':
                if (preg_match('/\b(inicio|reiniciar|menu)\b/u', $normalized) === 1) {
                    $this->resetSimulator();

                    return;
                }
                $nextTrackingNumber = app(PostalTrackingNumberParser::class)->extract($value);
                if ($nextTrackingNumber !== null) {
                    $this->simulatorContext = [
                        'flow' => 'tracking',
                        'tracking_number' => $nextTrackingNumber,
                    ];
                    $this->lookupSimulatorTracking($nextTrackingNumber);

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
                    'text' => 'Para consultar otro paquete, envíame su número de guía.',
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

    private function simulatorWeightFromText(string $message): ?float
    {
        $weight = app(\App\Services\PackageWeightParser::class)->parse($message);

        return is_float($weight) ? $weight : null;
    }

    private function advanceSimulatorAfterWeight(float $weight): void
    {
        unset($this->simulatorContext['pending_weight']);
        $this->simulatorContext['weight'] = $weight;
        if (($this->simulatorContext['service'] ?? '') === 'delivery_express' && $weight > 5) {
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'DECISION NODE · LÍMITE DELIVERY EXPRESS',
                'text' => 'Delivery Express admite hasta 5 kg. Incluye hasta 3 kg; cada kilo adicional suma Bs 10 y el pago en destino suma Bs 2,50. ¿Quieres continuar con EMS?',
            ];
            $this->setSimulatorNode('quote_oversize', 'DECISION NODE · LÍMITE DE PESO');

            return;
        }

        $this->askSimulatorQuoteNextStep();
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
        try {
            if ($service === 'Delivery Express') {
                $quote = app(DeliveryExpressQuoteService::class)->quote(
                    (float) ($this->simulatorContext['weight'] ?? 0),
                    (string) ($this->simulatorContext['destination'] ?? ''),
                    (string) ($this->simulatorContext['mode'] ?? ''),
                    isset($this->simulatorContext['origin']) ? (string) $this->simulatorContext['origin'] : null,
                );
                $price = (float) $quote['price'];
            } else {
                $quote = app(PostarQuoteService::class)->quote(
                    $service,
                    (string) ($this->simulatorContext['destination'] ?? ''),
                    (float) ($this->simulatorContext['weight'] ?? 0),
                    $this->simulatorContext['dimensions'] ?? null,
                );
                $price = (float) $quote['tariff'];
            }

            $currency = (string) ($quote['currency'] ?? 'BOB');
            $currencyLabel = $currency === 'BOB' ? 'Bs' : $currency;
            $origin = $this->simulatorContext['origin_name']
                ?? (($this->simulatorContext['scope'] ?? '') === 'internacional'
                    ? 'Bolivia'
                    : $this->simulatorDestinationName((string) ($this->simulatorContext['origin'] ?? '')));
            $destination = $this->simulatorContext['destination_name']
                ?? $this->simulatorDestinationName((string) ($this->simulatorContext['destination'] ?? ''));
            $weight = number_format((float) $this->simulatorContext['weight'], 2, '.', '');
            $message = sprintf(
                'Tarifa oficial verificada de %s: %s %s para %s kg, desde %s hasta %s%s.',
                $service,
                $currencyLabel,
                number_format($price, 2, '.', ''),
                $weight,
                $origin,
                $destination,
                $service === 'Delivery Express'
                    ? ', modalidad '.str_replace('_', ' ', (string) $this->simulatorContext['mode'])
                    : '',
            );
            $this->simulatorContext['verified_quote'] = [
                'price' => $price,
                'currency' => $currency,
            ];
        } catch (Throwable $exception) {
            report($exception);
            $message = 'No pude verificar una tarifa para esos datos. No mostraré un precio estimado. Intenta nuevamente más tarde o consulta con una persona.';
        }
        $this->simulatorMessages[] = [
            'speaker' => 'bot',
            'node' => 'TERMINAL NODE · COTIZACIÓN',
            'text' => $message,
        ];
        $this->setSimulatorNode('quote_terminal', 'TERMINAL NODE · COTIZACIÓN');
    }

    private function lookupSimulatorTracking(string $trackingNumber): void
    {
        $query = PostalPackage::query()
            ->whereRaw('upper(trim(tracking_number)) = ?', [$trackingNumber]);
        $scope = $this->simulatorContext['scope'] ?? null;
        if ($scope !== null) {
            $query->where('scope', $scope)
                ->where('source', $scope === 'nacional' ? 'siop' : 'sitra');
        } else {
            $query->whereIn('scope', ['nacional', 'internacional']);
        }

        $packages = $query->with('latestEvent')->get();
        if ($packages->isEmpty()) {
            $this->simulatorContext['tracking_result'] = 'not_found';
            $message = "📦 No encontré el paquete con guía {$trackingNumber}. Verifica el código y comunícate al ".config('services.postal.support_phone', '64229403').' para confirmar su estado.';
        } elseif ($packages->count() > 1 && $scope === null) {
            $this->setSimulatorNode('tracking_scope', 'DECISION NODE · ALCANCE DEL RASTREO');
            $this->simulatorPlaceholder = 'Responde Nacional o Internacional...';
            $this->simulatorMessages[] = [
                'speaker' => 'bot',
                'node' => 'DECISION NODE · ALCANCE DEL RASTREO',
                'text' => "📦 Encontré más de un paquete con la guía {$trackingNumber}. ¿El paquete es nacional o internacional?",
            ];

            return;
        } else {
            $package = $packages->first();
            $event = $package->latestEvent;
            $rawStatus = trim((string) $package->current_event);
            $presenter = app(PostalEventPresenter::class);
            if ($presenter->isEmptyStatus($rawStatus)) {
                $rawStatus = trim((string) $event?->event_name);
            }
            $status = $presenter->currentStatus($package);
            $category = $presenter->currentCategory($package);
            $this->simulatorContext['scope'] = $package->scope;
            if ($status === null) {
                $this->simulatorContext['tracking_result'] = 'other';
                $message = "📦 No encontré movimientos registrados para el paquete con guía {$trackingNumber}; todavía no puedo confirmar su último estado. Para más información, comunícate al ".config('services.postal.support_phone', '64229403').'.';
            } else {
                $status = $presenter->packageStatus($status);
                $normalizedStatus = $this->normalizeSimulatorText($rawStatus);
                $this->simulatorContext['tracking_result'] = match (true) {
                    str_contains($normalizedStatus, 'ventanilla'),
                    str_contains($normalizedStatus, 'recojo'),
                    str_contains($normalizedStatus, 'retiro') => 'pickup',
                    str_contains($normalizedStatus, 'cartero'),
                    str_contains($normalizedStatus, 'reparto'),
                    str_contains($normalizedStatus, 'ultima milla') => 'courier',
                    default => 'other',
                };

                $message = "📦 El último estado del paquete con guía {$trackingNumber} es: {$status}.\n🏷️ Clasificación: {$category}.";
                if ($package->recipient) {
                    $message .= "\n👤 Destinatario: {$package->recipient}.";
                }
                if ($package->sender) {
                    $message .= "\n📨 Remitente: {$package->sender}.";
                }
                if ($package->destination_branch) {
                    $message .= "\n📍 Destino registrado: {$package->destination_branch}.";
                }
                $updatedAt = $event?->event_at ?: $package->updated_at_api;
                if ($updatedAt) {
                    $message .= "\n🕒 Último movimiento: ".$updatedAt->format('d/m/Y H:i').'.';
                }
            }
            $message .= "\nPara consultar otro paquete, envíame su número de guía.";
        }

        $this->simulatorMessages[] = [
            'speaker' => 'bot',
            'node' => 'TERMINAL NODE · RESPUESTA DE RASTREO',
            'text' => $message,
        ];
        $this->setSimulatorNode('tracking_terminal', 'TERMINAL NODE · RESPUESTA DE RASTREO');
    }

    private function simulatorTrackingScope(string $message): ?string
    {
        $normalized = $this->normalizeSimulatorText($message);

        return match (true) {
            preg_match('/\b(nacional|bolivia|1)\b/u', $normalized) === 1 => 'nacional',
            preg_match('/\b(internacional|exterior|2)\b/u', $normalized) === 1 => 'internacional',
            default => null,
        };
    }

    private function parseSimulatorDimensions(string $message): array|false|null
    {
        $normalized = $this->normalizeSimulatorText($message);
        if (preg_match('/\b(no|nose|omitir|sin dimensiones)\b/u', $normalized) === 1) {
            return null;
        }

        $normalizedMessage = preg_replace('/\b(\d{1,3})\.(\d{3})\b/', '$1$2', $message) ?? $message;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[x×,]\s*(\d+(?:[.,]\d+)?)\s*[x×,]\s*(\d+(?:[.,]\d+)?)/iu', $normalizedMessage, $matches) !== 1) {
            return false;
        }

        $values = array_map(
            fn (string $value): float => (float) str_replace(',', '.', $value),
            array_slice($matches, 1, 3),
        );
        if (min($values) <= 0) {
            return false;
        }

        return ['largo' => $values[0], 'ancho' => $values[1], 'alto' => $values[2]];
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
        if (! in_array($node, self::ALLOWED_SIMULATOR_NODES, true)) {
            $node = 'root';
            $label = 'ROOT NODE · SALUDO Y BIENVENIDA';
        }
        $this->simulatorNode = $node;
        $this->simulatorActiveLabel = $label;
    }

    private function compactSimulatorState(): void
    {
        $this->simulatorMessages = array_values(array_slice($this->simulatorMessages, -self::MAX_SIMULATOR_MESSAGES));
        foreach ($this->simulatorMessages as &$message) {
            if (isset($message['text'])) {
                $message['text'] = mb_substr((string) $message['text'], 0, self::MAX_SIMULATOR_CONTEXT_TEXT, 'UTF-8');
            }
        }
        unset($message);
        if (! is_array($this->simulatorContext)) {
            $this->simulatorContext = [];
        }
        foreach ($this->simulatorContext as $key => $value) {
            if (is_string($value)) {
                $this->simulatorContext[$key] = mb_substr($value, 0, self::MAX_SIMULATOR_CONTEXT_TEXT, 'UTF-8');
            }
        }
        $this->setSimulatorNode($this->simulatorNode, $this->simulatorActiveLabel);
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
        return app(DecisionTreeInputNormalizer::class)->normalize($text);
    }

    private function simulatorRouteFromText(string $text): ?array
    {
        return app(PostarQuoteService::class)->routeFromText($this->normalizeSimulatorText($text));
    }

    private function simulatorDestinationCodesFromText(string $text): array
    {
        return app(PostarQuoteService::class)->destinationCodesFromText($this->normalizeSimulatorText($text));
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
