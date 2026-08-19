@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim(config('app.url'), '/') }}/logo.jpeg"
     class="logo"
     style="max-width: 200px; height: auto;"
     alt="{{ config('app.name', 'Alqibla Clinic') }}">
</a>
</td>
</tr>
