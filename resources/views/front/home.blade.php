@extends('layouts.front')

@section('title', app()->getLocale()==='ar' ? 'اختر الخدمة' : 'Choose a service')

@section('content')
  {{-- Hero location picker (needs $states, $homepage) --}}
  @include('front.sections.hero-location', ['states' => $states, 'homepage' => $homepage])

  {{-- Services grid --}}
  @if($services->count())
    <section class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
      <h2 class="sr-only">{{ app()->getLocale()==='ar' ? 'الخدمات' : 'Services' }}</h2>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($services as $service)
          @include('front.components.service-card', ['service' => $service])
        @endforeach
      </div>
    </section>
  @endif

  {{-- App banner --}}
  @includeWhen(optional($homepage)->hero_image_path, 'front.sections.app-banner', ['homepage' => $homepage])

  {{-- Partner / Career CTAs --}}
  @include('front.sections.partner-career')

  {{-- Cities we serve (from $states) --}}
  @include('front.sections.cities-serve', ['states' => $states])
@endsection
