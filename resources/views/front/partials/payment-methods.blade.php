@php
    // This converts the incoming array to a Laravel Collection for easier handling.
    $allowed = collect($allowedMethods ?? []);
    $default = old('payment', $defaultPayment ?? $allowed->first());
@endphp

<div class="space-y-3">
    {{-- FIX: Use the correct ->contains() method for a Laravel Collection --}}
    @if ($allowed->contains('online'))
        <label for="pm_online" class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 bg-white hover:border-gray-300 cursor-pointer has-[:checked]:border-brand has-[:checked]:ring-2 has-[:checked]:ring-brand transition-all">
            <input 
                id="pm_online" 
                type="radio" 
                name="payment" 
                value="online" 
                {{ $default === 'online' ? 'checked' : '' }}
                class="mt-1 h-4 w-4 border-gray-300 text-brand focus:ring-brand"
            >
            <div class="text-sm flex-1">
                <div class="flex items-center gap-3">
                    <span class="font-semibold text-ink">{{ __('Pay Online') }}</span>
                </div>
                <p class="text-gray-500 mt-1">{{ __('Securely pay with KNET, Visa, or MasterCard.') }}</p>
            </div>
        </label>
    @endif
    
    {{-- FIX: Use the correct ->contains() method for a Laravel Collection --}}
    @if ($allowed->contains('cash'))
         <label for="pm_cash" class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 bg-white hover:border-gray-300 cursor-pointer has-[:checked]:border-brand has-[:checked]:ring-2 has-[:checked]:ring-brand transition-all">
            <input 
                id="pm_cash" 
                type="radio" 
                name="payment" 
                value="cash" 
                {{ $default === 'cash' ? 'checked' : '' }}
                class="mt-1 h-4 w-4 border-gray-300 text-brand focus:ring-brand"
            >
            <div class="text-sm flex-1">
                <div class="flex items-center gap-3">
                     <span class="font-semibold text-ink">{{ __('Cash on Delivery') }}</span>
                </div>
                 <p class="text-gray-500 mt-1">{{ __('Please have the exact amount ready for the driver.') }}</p>
            </div>
        </label>
    @endif
</div>

@error('payment')
    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
@enderror

