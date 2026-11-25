<?php

namespace App\Services;

use RuntimeException;

class LanguageService
{
    public function detect(string $text): string
    {
        $text = mb_strtolower($text);
        // Acepta errores frecuentes de escritura en saludos en inglés.
        if (preg_match('/^\s*(hello|hellow|helo|hi|hey)[\s,!?.]*$/u', $text) === 1) {
            return 'en';
        }
        if (preg_match('/^\s*(bonjour|salut)[\s,!?.]*$/u', $text) === 1) return 'fr';
        if (preg_match('/^\s*(hallo|guten morgen|guten tag)[\s,!?.]*$/u', $text) === 1) return 'de';
        if (preg_match('/^\s*(ciao|buongiorno|buonasera)[\s,!?.]*$/u', $text) === 1) return 'it';
        if (preg_match('/^\s*(olá|ola|oi|bom dia|boa tarde|boa noite)[\s,!?.]*$/u', $text) === 1) return 'pt';
        if (preg_match('/\b(olá|ola|oi|quanto|custaria|enviar|pacote|para o|no chile|brasil|argentina)\b/u', $text) === 1
            && ! preg_match('/\b(hello|package|send|shipment|cost)\b/u', $text)) {
            return 'pt';
        }
        if (preg_match('/^\s*(привет|здравствуйте)[\s,!?.]*$/u', $text) === 1) return 'ru';
        $scores = ['es' => 0, 'en' => 0, 'pt' => 0, 'fr' => 0, 'de' => 0, 'it' => 0, 'nl' => 0, 'ru' => 0, 'ar' => 0, 'zh' => 0, 'ja' => 0, 'ko' => 0];
        foreach (['es' => [' el ', ' la ', ' los ', ' las ', ' que ', ' de ', ' para ', ' donde ', ' paquete ', ' envío ', ' hola '], 'en' => [' the ', ' is ', ' are ', ' where ', ' what ', ' package ', ' shipment ', ' hello ', ' please ', ' i ', ' want ', ' to ', ' quote ', ' send ', ' from ', ' my '], 'pt' => [' pacote ', ' encomenda ', ' olá ', ' você ', ' enviar ', ' enviar '], 'fr' => [' le ', ' la ', ' les ', ' que ', ' pour ', ' colis ', ' envoi ', ' bonjour ', ' vous ', ' je ', ' veux ', ' envoyer '], 'de' => [' der ', ' die ', ' das ', ' und ', ' für ', ' paket ', ' hallo ', ' bitte ', ' ich ', ' möchte ', ' senden '], 'it' => [' il ', ' lo ', ' gli ', ' che ', ' per ', ' pacco ', ' ciao ', ' grazie ', ' voglio ', ' spedire '], 'nl' => [' de ', ' het ', ' een ', ' voor ', ' pakket ', ' hallo ', ' dank ', ' wil ', ' versturen '], 'ru' => [' это ', ' что ', ' для ', ' посылка ', ' здравствуйте ']] as $language => $terms) {
            foreach ($terms as $term) if (str_contains(' '.$text.' ', $term)) $scores[$language]++;
        }
        foreach (['es' => ['¿', '¡', ' está', ' dónde', ' cuál', ' cómo'], 'en' => ['where', 'which', 'how'], 'pt' => [' você', ' não', ' onde'], 'fr' => [' vous', ' où', ' pourquoi'], 'de' => [' nicht', ' wo ', ' wie '], 'it' => [' dove ', ' come ', ' non '], 'nl' => [' waar ', ' niet ']] as $language => $terms) {
            foreach ($terms as $term) if (str_contains($text, $term)) $scores[$language]++;
        }
        if (preg_match('/[\x{0600}-\x{06ff}]/u', $text)) return 'ar';
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) return 'zh';
        if (preg_match('/[\x{3040}-\x{30ff}]/u', $text)) return 'ja';
        if (preg_match('/[\x{ac00}-\x{d7af}]/u', $text)) return 'ko';
        if (preg_match('/[\x{0400}-\x{04ff}]/u', $text)) return 'ru';
        if (preg_match('/[\x{0e00}-\x{0e7f}]/u', $text)) return 'th';
        if (preg_match('/[\x{0900}-\x{097f}]/u', $text)) return 'hi';
        if (preg_match('/[\x{0600}-\x{06ff}]/u', $text)) return 'ar';
        if (preg_match('/[\x{0980}-\x{09ff}]/u', $text)) return 'bn';
        if (preg_match('/[\x{0590}-\x{05ff}]/u', $text)) return 'he';
        if (preg_match('/[\x{1e00}-\x{1eff}]/u', $text)) return 'vi';
        arsort($scores);
        return array_key_first($scores) ?: 'es';
    }

    public function label(string $language): string
    {
        return ['es' => 'español', 'en' => 'inglés', 'pt' => 'portugués', 'fr' => 'francés', 'de' => 'alemán', 'it' => 'italiano', 'nl' => 'neerlandés', 'ru' => 'ruso', 'ar' => 'árabe', 'zh' => 'chino mandarín', 'ja' => 'japonés', 'ko' => 'coreano', 'th' => 'tailandés', 'hi' => 'hindú', 'bn' => 'bengalí', 'he' => 'hebreo', 'vi' => 'vietnamita'][$language] ?? 'español';
    }
}
