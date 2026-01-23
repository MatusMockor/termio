<x-mail::message>
# Nová online rezervácia

Práve ste prijali novú rezerváciu cez váš online rezervačný systém.

<x-mail::panel>
**DETAILY REZERVÁCIE**

**Zákazník:** {{ $client->name }}
**Telefón:** {{ $client->phone }}
@if($client->email)
**Email:** {{ $client->email }}
@endif

**Služba:** {{ $appointment->service->name }}
**Dátum:** {{ $dayName }}, {{ $appointment->starts_at->format('d.m.Y') }}
**Čas:** {{ $appointment->starts_at->format('H:i') }} - {{ $appointment->ends_at->format('H:i') }}
**Trvanie:** {{ $appointment->service->duration_minutes }} minút
**Cena:** {{ number_format((float) $appointment->service->price, 2, ',', ' ') }} €
@if($appointment->staff)
**Pracovník:** {{ $appointment->staff->display_name }}
@endif
@if($appointment->notes)

**Poznámka od zákazníka:**
{{ $appointment->notes }}
@endif
</x-mail::panel>

<x-mail::button :url="config('app.url').'/appointments'" color="primary">
Zobraziť rezervácie
</x-mail::button>

Táto rezervácia bola vytvorená automaticky cez váš online rezervačný systém.
</x-mail::message>
