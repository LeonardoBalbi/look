<div {{ $attributes->merge(['class' => 'brand brand-identity']) }}>
    @if($branding['product_logo'])
        <img class="brand-product-logo" src="{{ asset(ltrim($branding['product_logo'], '/')) }}" alt="{{ $branding['product_name'] }}">
    @else
        <span class="brand-badge" aria-hidden="true">{{ $branding['store_initials'] }}</span>
    @endif
    <span class="brand-copy">
        <strong>{{ $branding['store_name'] }}</strong>
        <small>{{ $branding['product_name'] }}</small>
    </span>
</div>
