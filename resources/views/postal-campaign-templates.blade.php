@extends('layouts.crm', ['title' => 'Mensajes predeterminados ' . ucfirst($scope)])
@section('content')
    <div class="content">
        <livewire:postal-campaign-templates :scope="$scope" :key="'postal-campaign-templates-'.$scope" />
    </div>
@endsection
