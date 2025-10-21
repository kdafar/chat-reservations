<div class="cart-drawer-backdrop" onclick="closeCart()"></div>
<aside class="cart-drawer" {{ app()->getLocale()==='ar' ? 'style=left:0' : 'style=right:0' }}>
    <div class="cart-drawer-header zw-flex zw-items-center zw-justify-between">
        <strong>{{ __('Your Cart') }}</strong>
        <button class="zw-btn" data-close-cart>&times;</button>
    </div>
    <div class="cart-drawer-body" id="cart-lines">
        {{-- Lines will be populated progressively; for now we rely on summary & user feedback --}}
        <div class="zw-text-muted" style="text-align:center; padding:1rem 0">{{ __('Items will appear here as you add them.') }}</div>
    </div>
    <div class="cart-drawer-footer">
        <div class="zw-flex zw-justify-between"><span>{{ __('Subtotal') }}</span><span>KD <span data-cart-summary-subtotal>0.000</span></span></div>
        <div class="zw-flex zw-justify-between"><span>{{ __('Delivery') }}</span><span>KD <span data-cart-summary-delivery>0.000</span></span></div>
        <div class="zw-flex zw-justify-between" style="font-weight:700; margin-top:.35rem"><span>{{ __('Total') }}</span><span>KD <span data-cart-summary-total>0.000</span></span></div>
        <a href="{{ route('checkout.index') }}" class="zw-btn zw-btn-primary" style="width:100%; margin-top:.75rem">{{ __('Checkout') }}</a>
    </div>
</aside>
<script>
    // OPTIONAL: implement list rendering by hitting a dedicated endpoint later
    window.loadCartLines = async function(){ /* placeholder */ };
</script>

]
