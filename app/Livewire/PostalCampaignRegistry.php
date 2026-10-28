<?php

namespace App\Livewire;

use App\Models\PostalCampaign;
use App\Models\PostalCampaignMessage;
use Livewire\Component;

class PostalCampaignRegistry extends Component
{
    public string $scope = 'internacional';
    public ?int $editingCampaignId = null;
    public string $editingCampaignName = '';

    public function mount(string $scope = 'internacional'): void
    {
        $this->scope = $scope;
    }

    public function getCampaignsProperty()
    {
        return PostalCampaign::withCount('messages')->whereJsonContains('filters->scope', $this->scope)->latest()->get();
    }

    public function getMessagesProperty()
    {
        return PostalCampaignMessage::with(['campaign', 'package'])->whereHas('campaign', fn ($query) => $query->whereJsonContains('filters->scope', $this->scope))->latest()->get();
    }

    public function editCampaign(int $id): void
    {
        $campaign = PostalCampaign::findOrFail($id);
        $this->editingCampaignId = $campaign->id;
        $this->editingCampaignName = $campaign->name;
    }

    public function saveCampaign(): void
    {
        $this->validate(['editingCampaignName' => 'required|string|max:120']);
        PostalCampaign::whereKey($this->editingCampaignId)->update(['name' => $this->editingCampaignName]);
        $this->reset(['editingCampaignId', 'editingCampaignName']);
    }

    public function render()
    {
        return view('livewire.postal-campaign-registry');
    }
}
