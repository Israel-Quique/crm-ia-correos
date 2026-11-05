<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WhatsAppTestNumberRegistry
{
    public function all(): array
    {
        return array_column($this->allWithNames(), 'number');
    }

    public function allWithNames(): array
    {
        $stored = DB::table('app_settings')->where('key', 'whatsapp_test_numbers')->value('value');
        $storedNumbers = json_decode((string) $stored, true);
        $numbers = [];

        foreach (is_array($storedNumbers) ? $storedNumbers : [] as $entry) {
            $number = $this->normalize(is_array($entry)
                ? (string) ($entry['number'] ?? '')
                : (string) $entry);
            if (strlen($number) < 8 || strlen($number) > 15) {
                continue;
            }

            $name = is_array($entry) ? trim((string) ($entry['name'] ?? '')) : '';
            $numbers[$number] = [
                'name' => $name !== '' ? $name : 'Número existente',
                'number' => $number,
            ];
        }

        $configured = $this->normalize((string) config('services.whatsapp.test_recipient'));

        if (strlen($configured) >= 8 && strlen($configured) <= 15 && ! isset($numbers[$configured])) {
            $numbers = [
                $configured => ['name' => 'Predeterminado (.env)', 'number' => $configured],
            ] + $numbers;
        }

        return array_values($numbers);
    }

    public function add(string $name, string $number): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new InvalidArgumentException('Ingresa un nombre de hasta 80 caracteres.');
        }

        $number = $this->normalize($number);
        if (strlen($number) < 8 || strlen($number) > 15) {
            throw new InvalidArgumentException('Ingresa un número válido con código de país, entre 8 y 15 dígitos.');
        }

        $numbers = $this->allWithNames();
        if (in_array($number, array_column($numbers, 'number'), true)) {
            throw new InvalidArgumentException('Ese número ya está agregado.');
        }

        $numbers[] = ['name' => $name, 'number' => $number];
        $this->save($numbers);

        return $this->allWithNames();
    }

    public function remove(string $number): array
    {
        $number = $this->normalize($number);
        $configured = $this->normalize((string) config('services.whatsapp.test_recipient'));
        if ($number === $configured) {
            return $this->allWithNames();
        }

        $numbers = array_values(array_filter(
            $this->allWithNames(),
            fn (array $registeredNumber): bool => $registeredNumber['number'] !== $number,
        ));
        $this->save($numbers);

        return $this->allWithNames();
    }

    public function rename(string $number, string $name): array
    {
        $number = $this->normalize($number);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new InvalidArgumentException('Ingresa un nombre de hasta 80 caracteres.');
        }

        $numbers = $this->allWithNames();
        $found = false;
        foreach ($numbers as &$entry) {
            if ($entry['number'] === $number) {
                if ($number === $this->normalize((string) config('services.whatsapp.test_recipient'))) {
                    throw new InvalidArgumentException('El contacto predeterminado se administra desde la configuración.');
                }
                $entry['name'] = $name;
                $found = true;
                break;
            }
        }
        unset($entry);

        if (! $found) {
            throw new InvalidArgumentException('No se encontró el número de prueba.');
        }

        $this->save($numbers);

        return $this->allWithNames();
    }

    public function contains(string $number): bool
    {
        $number = $this->normalize($number);

        return $number !== '' && in_array($number, $this->all(), true);
    }

    private function save(array $numbers): void
    {
        $normalized = [];
        foreach ($numbers as $entry) {
            $number = $this->normalize(is_array($entry) ? (string) ($entry['number'] ?? '') : (string) $entry);
            $name = is_array($entry) ? trim((string) ($entry['name'] ?? '')) : 'Número existente';
            if (strlen($number) >= 8 && strlen($number) <= 15 && $name !== '') {
                $normalized[$number] = ['name' => $name, 'number' => $number];
            }
        }
        $numbers = array_values($normalized);
        $now = now();
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'whatsapp_test_numbers'],
            ['value' => json_encode($numbers), 'updated_at' => $now, 'created_at' => $now],
        );
    }

    private function normalize(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }
}
