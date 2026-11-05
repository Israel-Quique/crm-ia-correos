@extends('layouts.crm', ['title' => 'Campañas '.(($scope ?? 'internacional') === 'nacional' ? 'nacionales' : 'internacionales')])
@section('content')
    @livewire('postal-campaign-registry', ['scope' => $scope ?? 'internacional'])
@endsection
