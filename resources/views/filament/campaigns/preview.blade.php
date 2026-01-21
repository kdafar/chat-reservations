@php
use Illuminate\Support\Facades\Storage;

/** Safe state accessor */
$reader = (isset($get) && is_callable($get))
    ? $get
    : ((isset($state) && is_callable($state)) ? $state : function ($k, $d = null) { return $d; });

$getState = function ($key, $default = null) use ($reader) {
    try {
        $out = $reader($key);
        return is_null($out) ? $default : $out;
    } catch (\Throwable $e) {
        return $default;
    }
};

/** Template details */
$details    = $getState('template_details', []);
$details    = is_array($details) ? $details : [];
$components = (array) data_get($details, 'components', []);

/** HEADER */
$headerFormat    = 'NONE';
$headerComponent = null;
foreach ($components as $c) {
    if (($c['type'] ?? null) === 'HEADER') {
        $headerFormat    = strtoupper((string) ($c['format'] ?? 'NONE'));
        $headerComponent = $c;
        break;
    }
}

/** BODY */
$bodyText = '';
foreach ($components as $c) {
    if (($c['type'] ?? null) === 'BODY') {
        $bodyText = (string) data_get($c, 'text', '');
        break;
    }
}

/** Vars + robust replacement (handles {{1}} and {{ 1 }}) */
$vars = $getState('template_variables', []);
$vars = is_array($vars) ? $vars : [];
$previewBody = $bodyText;
if ($bodyText !== '' && !empty($vars)) {
    foreach ($vars as $index => $value) {
        $previewBody = preg_replace(
            '/\{\{\s*' . preg_quote((string) $index, '/') . '\s*\}\}/',
            (string) $value,
            $previewBody
        );
    }
}

/** Header image: support temp upload & saved path */
$headerUpload    = $getState('header_image_path');
$headerUploadUrl = null;

// Handle cases where the upload state might be an array
if (is_array($headerUpload) && count($headerUpload) > 0) {
    // FileUpload state is often an associative array (by UUID), so get the first file.
    $headerUpload = array_values($headerUpload)[0];
}

if (is_string($headerUpload) && $headerUpload !== '') {
    if (Storage::disk('public')->exists($headerUpload)) {
        $headerUploadUrl = Storage::disk('public')->url($headerUpload);
    }
} elseif (is_object($headerUpload) && method_exists($headerUpload, 'temporaryUrl')) {
    try { $headerUploadUrl = $headerUpload->temporaryUrl(); } catch (\Throwable $e) {}
}

/** FOOTER */
$footerText = '';
foreach ($components as $c) {
    if (($c['type'] ?? null) === 'FOOTER') {
        $footerText = (string) data_get($c, 'text', '');
        break;
    }
}

/** BUTTONS */
$buttonComponents = null;
foreach ($components as $c) {
    $t = $c['type'] ?? null;
    if ($t === 'BUTTONS' || $t === 'QUICK_REPLY_BUTTONS') {
        $buttonComponents = $c;
        break;
    }
}

/** Misc */
$locale       = strtoupper((string) $getState('default_locale', 'EN'));
$templateName = (string) $getState('template_name', '[No template selected]');

/** Reply Arrow Icon */
$replyIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 transform -scale-x-100"><path fill-rule="evenodd" d="M7.793 2.232a.75.75 0 011.06 0l6.25 6.25a.75.75 0 010 1.06l-6.25 6.25a.75.75 0 11-1.06-1.06L13.44 10 7.793 4.293a.75.75 0 010-1.06z" clip-rule="evenodd" /></svg>';

@endphp

<!-- 
  Outer container to simulate the chat app background.
  Using a light gray, but you could add a subtle pattern background image.
-->
<div class="space-y-4 p-4 rounded-lg bg-gray-100 dark:bg-gray-900">

    <!-- Title, like in the screenshot -->
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Your template
        </h3>
        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-800 text-xs font-medium text-blue-900 dark:text-blue-100">
            🌐 {{ $locale }} ({{ $templateName }})
        </span>
    </div>

    <!-- 
      The main chat bubble.
      'rounded-tl-none' helps create the bubble "tail" effect.
    -->
    <div class="relative max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg rounded-tl-none shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">

        <!-- Header -->
        @if($headerFormat === 'IMAGE' && $headerUploadUrl)
            <!-- Header Image -->
            <div class="w-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                <img src="{{ $headerUploadUrl }}" alt="Message Header" class="w-full h-auto object-cover">
            </div>
        @elseif($headerFormat === 'TEXT' && $headerComponent)
            <!-- Header Text -->
            <div class="w-full p-4 pb-2">
                <div class="text-base font-bold text-gray-900 dark:text-gray-100 whitespace-pre-wrap break-words">
                    {{ (string) data_get($headerComponent, 'text', '') }}
                </div>
            </div>
        @endif

        <!-- Body -->
        <div class="p-4 pt-2">
            <!-- Body Text -->
            <div class="text-sm text-gray-800 dark:text-gray-100 leading-relaxed whitespace-pre-wrap break-words">
                @if($previewBody !== '')
                    {!! nl2br(e($previewBody)) !!}
                @else
                    <span class="text-gray-400 dark:text-gray-500 italic">[No body content available]</span>
                @endif
            </div>

            <!-- Footer & Timestamp -->
            <div class="flex justify-end items-center mt-2 space-x-2">
                @if($footerText !== '')
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $footerText }}
                    </div>
                @endif
                <div class="text-xs text-gray-400 dark:text-gray-500" style="font-size: 0.7rem; line-height: 1rem;">
                    {{ now()->format('H:i') }}
                </div>
            </div>
        </div>

        <!-- Buttons/Actions -->
        @if(is_array($buttonComponents))
            @php $buttons = (array) data_get($buttonComponents, 'buttons', []); @endphp
            @if(!empty($buttons))
                <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                    @foreach($buttons as $btn)
                        <!-- 
                          Add a border-t to every button *except* the first one 
                          to create the stacked button look.
                        -->
                        <button 
                            class="w-full px-4 py-3 text-center text-blue-600 dark:text-blue-400 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center justify-center gap-2 @if(!$loop->first) border-t border-gray-100 dark:border-gray-700 @endif" 
                            disabled
                        >
                            <span>{!! $replyIcon !!}</span>
                            <span>
                                {{ (string) data_get($btn, 'text', 'Button') }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    <!-- Variables Summary (Kept from original, as it's useful) -->
    @if(!empty($vars))
        <div class_comment="This block is outside the chat bubble">
            <div class="max-w-md mx-auto mt-4 p-3 rounded bg-yellow-50 dark:bg-yellow-950 border border-yellow-200 dark:border-yellow-900">
                <div class="text-xs font-semibold text-yellow-900 dark:text-yellow-100 mb-2">
                    Template Variables
                </div>
                <div class="space-y-1">
                    @foreach($vars as $idx => $varValue)
                        <div class="text-xs text-yellow-800 dark:text-yellow-200">
                            <span class="font-mono bg-yellow-100 dark:bg-yellow-900 px-1.5 py-0.5 rounded">
                                {!! '&#123;&#123;'.$idx.'&#125;&#125;' !!}
                            </span>
                            <span class="text-yellow-700 dark:text-yellow-300 ml-2">
                                =
                                @if(strlen((string) $varValue))
                                    <span class="font-semibold">{{ $varValue }}</span>
                                @else
                                    <span class="italic text-yellow-600 dark:text-yellow-400">[empty]</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

</div>

