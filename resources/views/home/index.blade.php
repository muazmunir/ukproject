@extends('layouts.app')
@section('title', 'Home')

@section('content')

    @include('partials.hero-search')
    @include('partials.categories')
    {{-- home.blade.php --}}
    @include('partials.services-row', ['services' => $services])



    @include('partials.promo-banner')
   

    
@endsection
