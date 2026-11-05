<?php

namespace App\Services;

use RuntimeException;

class LanguageService
{
    public function detect(string $text): string
    {
        $text = mb_strtolower($text);
        $scores = ['es' => 0, 'en' => 0, 'pt' => 0, 'fr' => 0, 'de' => 0, 'it' => 0, 'nl' => 0, 'ru' => 0, 'ar' => 0, 'zh' => 0, 'ja' => 0, 'ko' => 0];
        foreach (['es' => [' el ', ' la ', ' los ', ' las ', ' que ', ' de ', ' para ', ' donde ', ' paquete ', ' envío ', ' hola '], 'en' => [' the ', ' is ', ' are ', ' where ', ' what ', ' package ', ' shipment ', ' hello ', ' please '], 'pt' => [' o ', ' a ', ' os ', ' as ', ' que ', ' para ', ' pacote ', ' encomenda ', ' olá ', ' você '], 'fr' => [' le ', ' la ', ' les ', ' que ', ' pour ', ' colis ', ' envoi ', ' bonjour ', ' vous '], 'de' => [' der ', ' die ', ' das ', ' und ', ' für ', ' paket ', ' hallo ', ' bitte '], 'it' => [' il ', ' lo ', ' gli ', ' che ', ' per ', ' pacco ', ' ciao ', ' grazie '], 'nl' => [' de ', ' het ', ' een ', ' voor ', ' pakket ', ' hallo ', ' dank '], 'ru' => [' это ', ' что ', ' для ', ' посылка ', ' здравствуйте ']] as $language => $terms) {
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
        arsort($scores);
        return array_key_first($scores) ?: 'es';
    }

    public function label(string $language): string
    {
        return ['es' => 'español', 'en' => 'inglés', 'pt' => 'portugués', 'fr' => 'francés', 'de' => 'alemán', 'it' => 'italiano', 'nl' => 'neerlandés', 'ru' => 'ruso', 'ar' => 'árabe', 'zh' => 'chino mandarín', 'ja' => 'japonés', 'ko' => 'coreano'][$language] ?? 'español';
    }
}
