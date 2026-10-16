@extends('layouts.app')

@section('title', 'Add Customer')
@section('page-title', 'Add Customer')

@section('content')
    @include('customers._form', [
        'dailyItems'   => $dailyItems,
        'allCustomers' => $allCustomers,
    ])
@endsection