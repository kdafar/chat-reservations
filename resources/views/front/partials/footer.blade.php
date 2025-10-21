{{-- resources/views/front/partials/footer.blade.php --}}
<footer class="mt-12 bg-white border-t border-gray-200">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid gap-10 lg:grid-cols-[1.2fr_2.8fr]">

      {{-- Brand + app badges + social --}}
      <div class="space-y-4">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-gray-900 bg-ink no-underline rounded-2xl">
          <img src="{{ asset('storage/images/logo.svg') }}" alt="Logo" class="h-10 w-auto" onerror="this.style.display='none'">
          <!-- <strong class="text-lg">{{ config('app.name','Zad Hub') }}</strong> -->
        </a>

        <p class="text-sm text-gray-600">
          {{ app()->getLocale()==='ar'
              ? 'اطلب طعامك ومواد البقالة والمزيد بسرعة إلى بابك'
              : 'Order food, groceries and more, delivered fast to your door' }}
        </p>

        {{-- Store badges --}}
        <div class="flex flex-wrap items-center gap-3 mt-3">
          <img src="{{ asset('images/badges/appstore.svg') }}" alt="App Store"
               class="h-10 w-auto" onerror="this.style.display='none'">
          <img src="{{ asset('images/badges/googleplay.svg') }}" alt="Google Play"
               class="h-10 w-auto" onerror="this.style.display='none'">
          {{-- Optional Huawei/AppGallery --}}
          <img src="{{ asset('images/badges/appgallery.svg') }}" alt="AppGallery"
               class="h-10 w-auto" onerror="this.style.display='none'">
        </div>

        {{-- Socials --}}
        <div class="flex items-center gap-3 pt-1">
          <a href="#" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200" aria-label="Instagram">
            <i class="fa-brands fa-instagram"></i>
          </a>
          <a href="#" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200" aria-label="Twitter">
            <i class="fa-brands fa-x-twitter"></i>
          </a>
          <a href="#" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200" aria-label="Facebook">
            <i class="fa-brands fa-facebook-f"></i>
          </a>
          <a href="#" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200" aria-label="TikTok">
            <i class="fa-brands fa-tiktok"></i>
          </a>
        </div>
      </div>

      {{-- Link columns --}}
      @php
        $cuisines = ['Burgers','Pizza','Grills','Asian','Breakfast','Healthy','Desserts','Seafood'];
        $cities   = ['Kuwait City','Hawally','Salmiya','Farwaniya','Jahra','Fahaheel','Ahmadi','Mangaf'];
        $discover = ['Restaurants','Groceries','Pharmacies','Flowers','Cakes','Convenience'];
        $help     = ['Track order','Contact us','FAQs','Privacy','Terms'];
        if(app()->getLocale()==='ar'){
          $cuisines = ['برجر','بيتزا','مشاوي','آسيوي','فطور','صحي','حلويات','مأكولات بحرية'];
          $cities   = ['مدينة الكويت','حولي','السالمية','الفروانية','الجهراء','الفحيحيل','الأحمدي','المنقف'];
          $discover = ['مطاعم','بقالات','صيدليات','زهور','كعكات','متجر سريع'];
          $help     = ['تتبع الطلب','تواصل معنا','الأسئلة الشائعة','الخصوصية','الشروط'];
        }
      @endphp

      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ app()->getLocale()==='ar' ? 'مأكولات شائعة' : 'Popular Cuisines' }}</h4>
          <ul class="space-y-2">
            @foreach($cuisines as $x)
              <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 no-underline">{{ $x }}</a></li>
            @endforeach
          </ul>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ app()->getLocale()==='ar' ? 'مدن نخدمها' : 'Cities we serve' }}</h4>
          <ul class="space-y-2">
            @foreach($cities as $x)
              <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 no-underline">{{ $x }}</a></li>
            @endforeach
          </ul>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ app()->getLocale()==='ar' ? 'اكتشف' : 'Discover' }}</h4>
          <ul class="space-y-2">
            @foreach($discover as $x)
              <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 no-underline">{{ $x }}</a></li>
            @endforeach
          </ul>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ app()->getLocale()==='ar' ? 'مساعدة ودعم' : 'Help & Support' }}</h4>
          <ul class="space-y-2">
            @foreach($help as $x)
              <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 no-underline">{{ $x }}</a></li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </div>

  {{-- Bottom strip --}}
  <div class="border-t border-gray-200">
    <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div class="text-sm text-gray-600">
          © {{ date('Y') }} {{ config('app.name','Zad Hub') }} — {{ app()->getLocale()==='ar' ? 'جميع الحقوق محفوظة' : 'All rights reserved.' }}
        </div>

        <div class="flex flex-wrap items-center gap-4">
          <a href="#" class="text-sm text-gray-600 hover:text-gray-900 no-underline">{{ __('Privacy') }}</a>
          <span class="text-gray-300">|</span>
          <a href="#" class="text-sm text-gray-600 hover:text-gray-900 no-underline">{{ __('Terms') }}</a>
          <span class="text-gray-300">|</span>
          <a href="#" class="text-sm text-gray-600 hover:text-gray-900 no-underline">{{ __('Contact') }}</a>
        </div>

        <div class="flex items-center gap-3">
          <img src="{{ asset('storage/images/payments/knet.svg') }}" alt="KNET" class="h-5 w-auto" onerror="this.style.display='none'">
          <img src="{{ asset('storage/images/payments/visa.svg') }}" alt="Visa" class="h-5 w-auto" onerror="this.style.display='none'">
          <img src="{{ asset('storage/images/payments/mastercard.svg') }}" alt="Mastercard" class="h-5 w-auto" onerror="this.style.display='none'">
        </div>
      </div>
    </div>
  </div>
</footer>
