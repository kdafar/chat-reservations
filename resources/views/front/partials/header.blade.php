<header x-data="headerComponent()" 
        class="sticky top-0 z-50 bg-surface/95 backdrop-blur-sm border-b border-gray-200/60 transition-all duration-200"
        :class="{ 'shadow-lg': scrolled }">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    
    {{-- MOBILE LAYOUT --}}
    <div class="md:hidden flex items-center justify-between py-3">
      {{-- Mobile Menu Button --}}
      <button @click="mobileMenuOpen = !mobileMenuOpen" 
              class="inline-flex items-center justify-center p-2 rounded-xl text-ink hover:bg-gray-100 transition-colors"
              :class="{ 'bg-gray-100': mobileMenuOpen }">
        <svg class="w-6 h-6 transition-transform duration-200" 
             :class="{ 'rotate-90': mobileMenuOpen }" 
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>

      {{-- Brand Logo --}}
      <a href="{{ route('home') }}" 
         class="inline-flex items-center gap-2 transition-transform hover:scale-105">
        <img src="{{ asset('storage/images/logo.svg') }}" 
             alt="{{ config('app.name', 'Zad Hub') }}" 
             class="h-8 w-auto"
             onerror="this.style.display='none'">
        <span class="font-bold text-lg text-ink hidden xs:block">
          {{ config('app.name', 'Zad Hub') }}
        </span>
      </a>

      {{-- Mobile Actions --}}
      <div class="flex items-center gap-2">
        {{-- Cart Button --}}
        <!-- <button @click="$dispatch('cart:open')" 
                class="relative p-2 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
            <svg class="w-5 h-5 text-ink" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                aria-hidden="true">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 12.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>

          <span x-show="$store.cart?.count > 0" 
                x-text="$store.cart?.count"
                class="absolute -top-1 -right-1 bg-brand text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-medium">
          </span>
        </button> -->

        {{-- Profile/Login --}}
        @auth
        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
          <button @click.stop="open = !open" 
                  class="flex items-center gap-2 p-2 rounded-xl hover:bg-gray-100 transition-colors">
            <div class="w-8 h-8 bg-brand rounded-full flex items-center justify-center text-white text-sm font-medium">
              {{ substr(auth()->user()->name, 0, 1) }}
            </div>
          </button>
          
          <div x-show="open" 
               x-cloak
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 scale-95"
               x-transition:enter-end="opacity-100 scale-100"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="opacity-100 scale-100"
               x-transition:leave-end="opacity-0 scale-95"
               class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-200 py-2 z-50">
            <div class="px-4 py-2 border-b border-gray-100">
              <p class="text-sm font-medium text-ink truncate">{{ auth()->user()->name }}</p>
              <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
            </div>
            <a href="{{ route('account.orders') }}" 
               class="flex items-center gap-3 px-4 py-2 text-sm text-ink hover:bg-gray-50">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
              </svg>
              {{ __('My Orders') }}
            </a>
            <a href="{{ route('account.profile.edit') }}" 
               class="flex items-center gap-3 px-4 py-2 text-sm text-ink hover:bg-gray-50">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
              {{ __('Profile') }}
            </a>
            <hr class="my-2 border-gray-100">
            <form method="post" action="{{ route('logout') }}">
              @csrf
              <button type="submit" 
                      class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 w-full text-left">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                {{ __('Logout') }}
              </button>
            </form>
          </div>
        </div>
        @else
        <a href="{{ route('login') }}" 
           class="p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
          </svg>
        </a>
        @endauth
      </div>
    </div>

    {{-- Mobile Menu --}}
    <div x-show="mobileMenuOpen" 
     x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="md:hidden border-t border-gray-200 py-4 space-y-2">
      
      {{-- Mobile Navigation Links --}}
      <div class="space-y-1">
        <a href="{{ route('home') }}" 
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-ink hover:bg-gray-50 transition-colors"
           :class="{ 'bg-brand/10 text-brand font-medium': $el.href === window.location.href }">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
          </svg>
          {{ __('Home') }}
        </a>
        
        @if(Route::has('restaurants.index'))
        <a href="{{ route('restaurants.index') }}" 
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-ink hover:bg-gray-50 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
          </svg>
          {{ __('Restaurants') }}
        </a>
        @endif
        
        @if(Route::has('offers.index'))
        <a href="{{ route('offers.index') }}" 
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-ink hover:bg-gray-50 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
          </svg>
          {{ __('Offers') }}
          <span class="ml-auto bg-brand text-white text-xs px-2 py-1 rounded-full font-medium">{{ __('New') }}</span>
        </a>
        @endif
      </div>

      {{-- Language Switcher --}}
      <div class="px-4 py-2">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-700">{{ __('Language') }}</span>
          <div class="flex bg-gray-100 rounded-lg p-1">
            <a href="{{ route('language.switch', 'ar') }}" 
               class="px-3 py-1 text-xs font-medium rounded-md transition-colors {{ app()->getLocale() === 'ar' ? 'bg-white text-ink shadow-sm' : 'text-gray-600 hover:text-ink' }}">
              العربية
            </a>
            <a href="{{ route('language.switch', 'en') }}" 
               class="px-3 py-1 text-xs font-medium rounded-md transition-colors {{ app()->getLocale() === 'en' ? 'bg-white text-ink shadow-sm' : 'text-gray-600 hover:text-ink' }}">
              English
            </a>
          </div>
        </div>
      </div>

      {{-- Mobile Auth Section --}}
      @guest
      <div class="px-4 pt-2 space-y-2">
        <a href="{{ route('login') }}" 
           class="btn btn-outline w-full justify-center">
          {{ __('Login') }}
        </a>
        <a href="{{ route('register') }}" 
           class="btn btn-primary w-full justify-center">
          {{ __('Sign Up') }}
        </a>
      </div>
      @endguest
    </div>

    {{-- DESKTOP LAYOUT --}}
    <div class="hidden md:flex items-center justify-between py-3 lg:py-4">
      {{-- Brand Logo --}}
      <a href="{{ route('home') }}" 
         class="inline-flex items-center gap-3 transition-transform hover:scale-105">
        <img src="{{ asset('storage/images/logo.svg') }}" 
             alt="{{ config('app.name', 'Zad Hub') }}" 
             class="h-9 w-auto"
             onerror="this.style.display='none'">
        <span class="font-bold text-xl text-ink">
          {{ config('app.name', 'Zad Hub') }}
        </span>
      </a>

      {{-- Desktop Navigation --}}
      <nav class="flex items-center space-x-1">
        <a href="{{ route('home') }}" 
           class="px-4 py-2 rounded-xl text-sm font-medium text-ink/80 hover:text-ink hover:bg-gray-50 transition-colors">
          {{ __('Home') }}
        </a>
        
        @if(Route::has('restaurants.index'))
        <a href="{{ route('restaurants.index') }}" 
           class="px-4 py-2 rounded-xl text-sm font-medium text-ink/80 hover:text-ink hover:bg-gray-50 transition-colors">
          {{ __('Restaurants') }}
        </a>
        @endif
        
        @if(Route::has('offers.index'))
        <div class="relative">
          <a href="{{ route('offers.index') }}" 
             class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-ink/80 hover:text-ink hover:bg-gray-50 transition-colors">
            {{ __('Offers') }}
            <span class="bg-brand text-white text-xs px-2 py-0.5 rounded-full font-medium">{{ __('New') }}</span>
          </a>
        </div>
        @endif
      </nav>

      {{-- Desktop Actions --}}
      <div class="flex items-center gap-4">
        {{-- Enhanced Cart Button --}}
        <button @click="$dispatch('cart:open')" 
                class="relative inline-flex items-center gap-3 px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 transition-all duration-200 shadow-sm hover:shadow-md">
            <div class="relative inline-block w-5 h-5">
                <svg class="w-5 h-5 text-ink" viewBox="0 0 24 24" fill="none"
                      stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                      aria-hidden="true">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 12.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span
                    x-show="$store.cart?.count > 0"
                    x-text="$store.cart?.count"
                    class="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 z-10
                            bg-brand text-white text-[10px] leading-none min-w-[1.1rem] h-5 px-1
                            rounded-full flex items-center justify-center font-medium pointer-events-none">
                </span>
            </div>

            <div class="text-left">
                <div class="text-xs text-gray-500 leading-none">{{ __('Cart') }}</div>
                <div class="text-sm font-semibold text-ink leading-tight">
                    KD <span x-text="$store.cart?.subtotal ?? '0.000'">0.000</span>
                </div>
            </div>
        </button>

        {{-- Language Switcher --}}
        <div class="flex bg-gray-100 rounded-lg p-1 border-l border-gray-200 pl-4 ml-4">
          <a href="{{ route('language.switch', 'ar') }}" 
             class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ app()->getLocale() === 'ar' ? 'bg-white text-ink shadow-sm' : 'text-gray-600 hover:text-ink' }}">
            العربية
          </a>
          <a href="{{ route('language.switch', 'en') }}" 
             class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ app()->getLocale() === 'en' ? 'bg-white text-ink shadow-sm' : 'text-gray-600 hover:text-ink' }}">
            English
          </a>
        </div>

        {{-- Enhanced Auth Section --}}
        @auth
        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
          <button @click.stop="open = !open" 
                  class="flex items-center gap-3 px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 bg-gradient-to-br from-brand to-accent rounded-full flex items-center justify-center text-white text-sm font-bold shadow-sm">
              {{ substr(auth()->user()->name, 0, 1) }}
            </div>
            <div class="text-left hidden lg:block">
              <div class="text-xs text-gray-500 leading-none">{{ __('Welcome') }}</div>
              <div class="text-sm font-medium text-ink leading-tight truncate max-w-[8rem]">
                {{ Str::limit(auth()->user()->name, 12) }}
              </div>
            </div>
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" 
                 :class="{ 'rotate-180': open }" 
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          
          <div x-show="open" 
               x-cloak
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 scale-95"
               x-transition:enter-end="opacity-100 scale-100"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="opacity-100 scale-100"
               x-transition:leave-end="opacity-0 scale-95"
               class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl border border-gray-200 py-2 z-50">
            
            {{-- User Info Header --}}
            <div class="px-4 py-3 border-b border-gray-100">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-brand to-accent rounded-full flex items-center justify-center text-white font-bold">
                  {{ substr(auth()->user()->name, 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-ink truncate">{{ auth()->user()->name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                </div>
              </div>
            </div>

            {{-- Menu Items --}}
            <div class="py-2">
              <a href="{{ route('account.orders') }}" 
                 class="flex items-center gap-3 px-4 py-2 text-sm text-ink hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                {{ __('My Orders') }}
                <span class="ml-auto bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">3</span>
              </a>
              
              <a href="{{ route('account.profile.edit') }}" 
                 class="flex items-center gap-3 px-4 py-2 text-sm text-ink hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                {{ __('Profile Settings') }}
              </a>
              
              <a href="#" class="flex items-center gap-3 px-4 py-2 text-sm text-ink hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                </svg>
                {{ __('Favorites') }}
              </a>
              
              <a href="#" class="flex items-center gap-3 px-4 py-2 text-sm text-ink hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                {{ __('Settings') }}
              </a>
            </div>

            {{-- Logout --}}
            <div class="border-t border-gray-100 pt-2">
              <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" 
                        class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors w-full text-left">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                  </svg>
                  {{ __('Sign Out') }}
                </button>
              </form>
            </div>
          </div>
        </div>
        @else
        {{-- Guest Actions --}}
        <div class="flex items-center gap-3">
          <a href="{{ route('login') }}" 
             class="btn btn-outline">
            {{ __('Login') }}
          </a>
          <a href="{{ route('register') }}" 
             class="btn btn-primary">
            {{ __('Sign Up') }}
          </a>
        </div>
        @endauth
      </div>
    </div>
  </div>
</header>
