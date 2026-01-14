<x-mail::message>
# Dobrý deň, {{ $appointment->client->name }}!

Vaša rezervácia bola **úspešne vytvorená**.

<x-mail::panel>
**DETAILY REZERVÁCIE**

**Služba:** {{ $appointment->service->name }}
**Dátum:** {{ $dayName }}, {{ $appointment->starts_at->format('d.m.Y') }}
**Čas:** {{ $appointment->starts_at->format('H:i') }} - {{ $appointment->ends_at->format('H:i') }}
**Trvanie:** {{ $appointment->service->duration_minutes }} minút
**Cena:** {{ number_format((float) $appointment->service->price, 2, ',', ' ') }} €
@if($appointment->staff)
**Pracovník:** {{ $appointment->staff->display_name }}
@endif
</x-mail::panel>

<x-mail::panel>
**{{ $appointment->tenant->name }}**
@if($appointment->tenant->address)
{{ $appointment->tenant->address }}
@endif
@if($appointment->tenant->phone)
Tel: {{ $appointment->tenant->phone }}
@endif
</x-mail::panel>

Ďakujeme za vašu rezerváciu! Tešíme sa na vás.
</x-mail::message>
