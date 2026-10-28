@extends('layouts.crm', ['title' => 'Campañas Postales · Conversa OS'])
@section('content')
    @livewire('postal-campaign-manager', ['scope' => $scope ?? 'nacional'])
@endsection
