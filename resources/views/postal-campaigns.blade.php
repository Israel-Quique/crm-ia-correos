@extends('layouts.crm', ['title' => 'Campañas Postales · Conversa OS'])
@section('content')
    @livewire('postal-campaign-registry', ['scope' => $scope ?? 'nacional'])
@endsection
