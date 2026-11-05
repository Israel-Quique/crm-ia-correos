<?php
namespace App\Livewire;
use App\Models\WhatsAppMessage;
use Livewire\Component;
use Livewire\WithPagination;
class WhatsAppMessageHistory extends Component
{
    use WithPagination;
    public string $search = ''; public string $direction = 'all'; public string $source = 'all'; public string $status = 'all';
    public function updated($property): void { if (in_array($property, ['search', 'direction', 'source', 'status'], true)) $this->resetPage(); }
    public function render() { $messages = WhatsAppMessage::query()->with('sender')->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('phone', 'like', '%'.$this->search.'%')->orWhere('contact_name', 'like', '%'.$this->search.'%')->orWhere('message', 'like', '%'.$this->search.'%')))->when($this->direction !== 'all', fn ($q) => $q->where('direction', $this->direction))->when($this->source !== 'all', fn ($q) => $q->where('source', $this->source))->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))->latest('received_at')->paginate(25); return view('livewire.whatsapp-message-history', compact('messages')); }
}
