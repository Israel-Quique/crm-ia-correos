@extends('layouts.crm', ['title' => 'Registro de campañas internacionales'])
@section('content')
    @livewire('postal-campaign-registry', ['scope' => $scope ?? 'internacional'])
@endsection
