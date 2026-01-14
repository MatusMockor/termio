<x-mail::message>
# Dobrý deň, {{ $appointment->client->name }}!

Vaša rezervácia bola **zrušená**.

<x-mail::panel>
**ZRUŠENÁ REZERVÁCIA**

**Služba:** {{ $appointment->service->name }}
**Dátum:** {{ $dayName }}, {{ $appointment->starts_at->format('d.m.Y') }}
**Čas:** {{ $appointment->starts_at->format('H:i') }}
</x-mail::panel>

Ak máte záujem, môžete si kedykoľvek vytvoriť novú rezerváciu.

<x-mail::panel>
**KONTAKT**

**{{ $appointment->tenant->name }}**
@if($appointment->tenant->phone)
Tel: {{ $appointment->tenant->phone }}
@endif
</x-mail::panel>
</x-mail::message>
