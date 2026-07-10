<?php

use App\Models\DocumentoInterno;
use App\Services\DocumentoCollaborationService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Canal de presença da edição colaborativa de um Documento Interno.
 * Autoriza apenas utilizadores que possam colaborar (rascunho, mesmo gabinete/nível). O payload
 * de presença identifica o participante (cursores/lista de online) sem expor dados sensíveis.
 */
Broadcast::channel('documento.{documentoInterno}', function ($user, DocumentoInterno $documentoInterno) {
    if (! Gate::forUser($user)->allows('collaborate', $documentoInterno)) {
        return null;
    }

    $service = app(DocumentoCollaborationService::class);
    $nivel = $service->nivelDe($user, $documentoInterno);

    if ($nivel === null) {
        return null;
    }

    return [
        'id' => $user->id,
        'nome' => $user->name,
        'cor' => $service->corDoUtilizador($user->id),
        'nivel' => $nivel->value,
        'podeEditar' => $nivel->podeEditar(),
    ];
});
