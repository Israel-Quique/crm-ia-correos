<?php
namespace App\Livewire;
use App\Models\AutomaticResponse;
use Livewire\Component;
class AutomaticResponses extends Component
{
    public string $type='event'; public string $key=''; public string $name=''; public string $template=''; public bool $active=true;
    protected array $rules=['type'=>'required|in:event,quote','key'=>'required|string|max:80','name'=>'required|string|max:120','template'=>'required|string|max:1000'];
    public function save(): void { $this->validate(); AutomaticResponse::create(['user_id'=>auth()->id(),'type'=>$this->type,'key'=>trim($this->key),'name'=>trim($this->name),'template'=>trim($this->template),'active'=>$this->active]); $this->reset(['key','name','template']); $this->active=true; session()->flash('automaticResponseSaved','Respuesta automática guardada.'); }
    public function toggle(int $id): void { $response=AutomaticResponse::findOrFail($id); $response->update(['active'=>!$response->active]); }
    public function delete(int $id): void { AutomaticResponse::whereKey($id)->delete(); }
    public function render() { return view('livewire.automatic-responses',['responses'=>AutomaticResponse::latest()->get()]); }
}
