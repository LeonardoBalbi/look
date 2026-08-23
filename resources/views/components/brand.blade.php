@php($usesLocxLogo = str_contains(mb_strtolower($branding['store_name']), 'locx'))

@if($usesLocxLogo)
    <div {{ $attributes->merge(['class' => 'brand-logo']) }} aria-label="{{ $branding['store_name'] }} — {{ $branding['product_name'] }}">
        <img
            src="{{ \App\Support\RentalSupport::asset('assets/img/locx-logo.svg') }}"
            alt="LocX Aluguel de Motos"
            width="320"
            height="118"
        >
    </div>
@else
    <div {{ $attributes->merge(['class' => 'brand brand-identity']) }}>
        <span class="brand-badge" aria-hidden="true">{{ $branding['store_initials'] }}</span>
        <span class="brand-copy">
            <strong>{{ $branding['store_name'] }}</strong>
            <small>{{ $branding['product_name'] }}</small>
        </span>
    </div>
@endif
