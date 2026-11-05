<?php

namespace App\Livewire;

use App\Services\WhatsAppTestNumberRegistry;
use InvalidArgumentException;
use Livewire\Component;

class WhatsAppTestNumbers extends Component
{
    public array $testNumbers = [];
    public string $newTestName = '';
    public string $newTestNumber = '';
    public string $editingTestNumber = '';
    public string $editingTestName = '';

    public function mount(WhatsAppTestNumberRegistry $registry): void
    {
        $this->testNumbers = $registry->allWithNames();
    }

    public function addTestNumber(WhatsAppTestNumberRegistry $registry): void
    {
        $this->validate([
            'newTestName' => 'required|string|max:80',
            'newTestNumber' => 'required|string|min:8|max:24',
        ]);

        try {
            $this->testNumbers = $registry->add($this->newTestName, $this->newTestNumber);
        } catch (InvalidArgumentException $exception) {
            $this->addError('newTestNumber', $exception->getMessage());
            return;
        }

        $this->newTestName = '';
        $this->newTestNumber = '';
        $this->resetValidation();
        session()->flash('testNumberSaved', 'Contacto de prueba guardado.');
    }

    public function removeTestNumber(string $number, WhatsAppTestNumberRegistry $registry): void
    {
        $this->testNumbers = $registry->remove($number);
        session()->flash('testNumberSaved', 'Contacto de prueba eliminado.');
    }

    public function editTestName(string $number): void
    {
        $entry = collect($this->testNumbers)->firstWhere('number', $number);
        if ($entry === null || $number === preg_replace('/\D+/', '', (string) config('services.whatsapp.test_recipient'))) {
            return;
        }

        $this->editingTestNumber = $number;
        $this->editingTestName = $entry['name'];
    }

    public function saveTestName(WhatsAppTestNumberRegistry $registry): void
    {
        $this->validate(['editingTestName' => 'required|string|max:80']);

        try {
            $this->testNumbers = $registry->rename($this->editingTestNumber, $this->editingTestName);
        } catch (InvalidArgumentException $exception) {
            $this->addError('editingTestName', $exception->getMessage());
            return;
        }

        $this->editingTestNumber = '';
        $this->editingTestName = '';
        $this->resetValidation('editingTestName');
        session()->flash('testNumberSaved', 'Nombre del contacto actualizado.');
    }

    public function render()
    {
        return view('livewire.whatsapp-test-numbers');
    }
}
