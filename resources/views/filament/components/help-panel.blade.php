@php
    /** @var array $sections */
@endphp

<div class="fi-help-panel space-y-6 text-sm text-gray-700 dark:text-gray-300">
    @foreach ($sections as $section)
        <div>
            @if (! empty($section['heading']))
                <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-2">
                    {{ $section['heading'] }}
                </h3>
            @endif

            @if (! empty($section['body']))
                <div class="prose prose-sm dark:prose-invert max-w-none leading-relaxed">
                    {!! nl2br(e($section['body'])) !!}
                </div>
            @endif

            @if (! empty($section['items']))
                @php
                    $itemsAreQA = is_array($section['items'][0] ?? null) && isset($section['items'][0]['q']);
                @endphp

                @if ($itemsAreQA)
                    <dl class="space-y-3">
                        @foreach ($section['items'] as $item)
                            <div>
                                <dt class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $item['q'] }}
                                </dt>
                                <dd class="text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $item['a'] }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <ul class="list-disc pl-5 space-y-1.5">
                        @foreach ($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    @endforeach
</div>
