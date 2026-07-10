@component('mail::message')
# Olá, {{ $user->name }}

Tem **{{ $items->count() }}** notificação(ões) por ler no sistema:

@foreach($items as $item)
---
**{{ $item['title'] }}**
@if($item['body'])
{{ $item['body'] }}
@endif
<small>{{ $item['created_at_human'] }}</small>
@if($item['url'])

@component('mail::button', ['url' => $item['url']])
Abrir
@endcomponent
@endif
@endforeach

---

@component('mail::button', ['url' => route('notifications.page')])
Ver todas as notificações
@endcomponent

Pode ajustar a frequência e os canais em **Preferências de notificação**.

Obrigado,<br>
{{ config('app.name') }}
@endcomponent
