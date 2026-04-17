@extends('layouts.admin')
@section('title', 'HomePage Customization')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin-websettings.css') }}">
@endpush

@section('content')
    <section class="card settings-card">
        <div class="card__head">
            <div>
                <div class="card__title">HomePage Customization</div>
                <div class="muted text-capitalize">Edit Images For The Homepage and Middle Banner.</div>
            </div>
        </div>

        @include('admin.website._settings_nav')

        <form method="post" action="{{ route('admin.settings.appearance.update') }}" enctype="multipart/form-data"
            class="settings-form grid-2">
            @csrf

            <div class="field">
                @include('admin.website._image_uploader', [
                    'name' => 'homepage_search_bg',
                    'id' => 'homepage_search_bg',
                    'labelText' => 'Homepage Search Area Background Image',
                    'currentPath' => $searchBg,
                    'deleteRoute' => route('admin.settings.appearance.search_bg.delete'),
                    'removeField' => 'remove_homepage_search_bg', // only if you added remove logic in controller
                ])

                @error('homepage_search_bg')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>


            <div class="field">
                @include('admin.website._image_uploader', [
                    'name' => 'homepage_middle_img',
                    'id' => 'homepage_middle_img',
                    'labelText' => 'Homepage Middle Banner Image',
                    'currentPath' => $middleImg,
                    'deleteRoute' => route('admin.settings.appearance.middle_banner.delete'),
                    'removeField' => 'remove_homepage_middle_banner', // only if you added remove logic in controller
                ])

                @error('homepage_middle_img')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>


            <div class="form-foot full">
                <button class="btn bg-black" type="submit">Update</button>
            </div>
        </form>
    </section>
@endsection
