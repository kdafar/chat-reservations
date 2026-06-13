@component('mail::message')
# Vendor payment {{ $po['overdue'] ? 'overdue' : 'due soon' }}

A purchase order has a vendor payment that is **{{ $po['overdue'] ? 'overdue' : 'coming due' }}**.

@component('mail::panel')
**Vendor:** {{ $po['vendor'] }}
**Purchase order:** {{ $po['code'] }}
**Outstanding:** {{ $po['outstanding'] }} KWD
**Due date:** {{ $po['due_date'] }}
@if($po['overdue'])
**Overdue by:** {{ abs($po['days']) }} day(s)
@else
**Due in:** {{ $po['days'] }} day(s)
@endif
@endcomponent

@component('mail::button', ['url' => $po['url']])
Open purchase order
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
