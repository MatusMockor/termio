<x-mail::message>
# Dobrý deň, {{ $appointment->client->name }}!

Pripomíname vám **zajtraššiu rezerváciu**.

<x-mail::panel>
**DETAILY REZERVÁCIE**

**Služba:** {{ $appointment->service->name }}
**Dátum:** {{ $dayName }}, {{ $appointment->starts_at->format('d.m.Y') }}
**Čas:** {{ $appointment->starts_at->format('H:i') }}
**Trvanie:** {{ $appointment->service->duration_minutes }} minút
@if($appointment->staff)
**Pracovník:** {{ $appointment->staff->display_name }}
@endif
</x-mail::panel>

<x-mail::panel>
**KDE NÁS NÁJDETE**

**{{ $appointment->tenant->name }}**
@if($appointment->tenant->address)
{{ $appointment->tenant->address }}
@endif
@if($appointment->tenant->phone)
Tel: {{ $appointment->tenant->phone }}
@endif
</x-mail::panel>

Tešíme sa na vás!
</x-mail::message>
