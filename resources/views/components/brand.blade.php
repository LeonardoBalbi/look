<div {{ $attributes->merge(['class' => 'brand brand-identity']) }}>
    <span class="brand-badge" aria-hidden="true">{{ $branding['store_initials'] }}</span>
    <span class="brand-copy">
        <strong>{{ $branding['store_name'] }}</strong>
        <small>{{ $branding['product_name'] }}</small>
    </span>
</div>
