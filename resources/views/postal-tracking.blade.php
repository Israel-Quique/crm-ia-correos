@extends('layouts.crm', ['title' => 'Rastreo '.(($scope ?? 'nacional') === 'nacional' ? 'nacional' : 'internacional')])
@section('content')
    @livewire('postal-tracking-manager', ['scope' => $scope ?? 'nacional'])
@endsection
