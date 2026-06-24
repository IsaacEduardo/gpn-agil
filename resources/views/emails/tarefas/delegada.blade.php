@component('mail::message')
# Nova Tarefa Designada

Olá, **{{ $notifiable->name }}**,

Uma nova tarefa foi designada para si no sistema **Ondaka (GPN-AGIL)**.

@component('mail::panel')
### Detalhes da Tarefa:
* **Título:** {{ $tarefa->titulo }}
* **Documento:** {{ $numero }} - {{ $documento->assunto }}
* **Designado por:** {{ $tarefa->assignedBy->name }}
@if($tarefa->prazo_at)
* **Prazo Limite:** {{ $tarefa->prazo_at->format('d/m/Y H:i') }}
@else
* **Prazo Limite:** Sem prazo definido
@endif

@if($tarefa->descricao)
**Descrição:**  
{{ $tarefa->descricao }}
@endif
@endcomponent

Para ver o documento associado e gerir a tarefa, por favor clique no botão abaixo:

@component('mail::button', ['url' => $url])
Ver Documento e Tarefa
@endcomponent

Com os melhores cumprimentos,  
A Equipa do **{{ config('app.name') }}**
@endcomponent
