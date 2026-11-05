<?php
namespace App\Livewire;
use App\Models\AutomaticResponse;
use Livewire\Component;
use Livewire\WithPagination;
class AutomaticResponses extends Component
{
    use WithPagination;
    public int $perPage = 8;
    public string $scopeFilter='all'; public string $typeFilter='all'; public string $keyFilter='all';
    public string $scope='general'; public string $type='event'; public string $key=''; public string $name=''; public string $template=''; public string $triggerPhrases=''; public bool $active=true;
    protected array $rules=['scope'=>'required|in:general,nacional,internacional','type'=>'required|in:event,quote','key'=>'required|string|max:80','name'=>'required|string|max:120','template'=>'required|string|max:1000','triggerPhrases'=>'nullable|string|max:1000'];
    public function save(): void { $this->validate(); AutomaticResponse::create(['user_id'=>auth()->id(),'scope'=>$this->scope,'type'=>$this->type,'key'=>trim($this->key),'name'=>trim($this->name),'template'=>trim($this->template),'trigger_phrases'=>$this->parseTriggers($this->triggerPhrases),'active'=>$this->active]); $this->reset(['key','name','template','triggerPhrases']); $this->active=true; session()->flash('automaticResponseSaved','Respuesta automática guardada.'); $this->dispatch('automatic-response-saved'); }
    private function parseTriggers(string $value): array { return array_values(array_filter(array_map(fn($item)=>trim($item), preg_split('/[,\n]+/', $value)))); }
    public function toggle(int $id): void { $response=AutomaticResponse::findOrFail($id); $response->update(['active'=>!$response->active]); }
    public function delete(int $id): void { AutomaticResponse::whereKey($id)->delete(); }
    public function updatedScopeFilter(): void { $this->resetPage(); } public function updatedTypeFilter(): void { $this->resetPage(); } public function updatedKeyFilter(): void { $this->resetPage(); }
    public function clearFilters(): void { $this->scopeFilter='all'; $this->typeFilter='all'; $this->keyFilter='all'; $this->resetPage(); }
    public function render() { $query=AutomaticResponse::latest(); if($this->scopeFilter!=='all')$query->where('scope',$this->scopeFilter); if($this->typeFilter!=='all')$query->where('type',$this->typeFilter); if($this->keyFilter!=='all')$query->where('key',$this->keyFilter); return view('livewire.automatic-responses',['responses'=>$query->paginate($this->perPage),'keys'=>AutomaticResponse::query()->select('key')->distinct()->orderBy('key')->pluck('key')]); }
}
