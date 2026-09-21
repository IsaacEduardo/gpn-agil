<?php

namespace Tests\Feature;

use App\Events\DocumentoArquivado;
use App\Events\TaskAssigned;
use Illuminate\Notifications\Events\NotificationSending;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cada ouvinte tem de estar registado exatamente uma vez.
 *
 * A descoberta automática de eventos do Laravel varre app/Listeners e regista o
 * handle() de cada ouvinte por cima do mapa explícito de EventServiceProvider.
 * As duas entradas ('Ouvinte' e 'Ouvinte@handle') não são iguais para o
 * array_unique do framework, pelo que sobreviviam ambas e cada ouvinte corria
 * duas vezes por evento: o executante recebia a notificação de tarefa delegada
 * a dobrar e os ouvintes de auditoria gravavam dois registos por facto.
 *
 * A descoberta está desligada em bootstrap/app.php (withEvents(discover: false)).
 * Este teste impede que volte a ser ligada sem se dar por isso.
 */
class OuvintesRegistadosUmaVezTest extends TestCase
{
    public static function eventosComOuvinteUnico(): array
    {
        return [
            'tarefa delegada' => [TaskAssigned::class],
            'documento arquivado' => [DocumentoArquivado::class],
            'preferências de notificação' => [NotificationSending::class],
        ];
    }

    #[DataProvider('eventosComOuvinteUnico')]
    public function test_evento_tem_um_unico_ouvinte(string $evento): void
    {
        $this->assertCount(
            1,
            app('events')->getListeners($evento),
            $evento.' está registado mais do que uma vez: o ouvinte vai correr em duplicado.'
        );
    }

    public function test_descoberta_automatica_de_eventos_esta_desligada(): void
    {
        $provider = new \App\Providers\EventServiceProvider($this->app);

        $this->assertFalse(
            $provider->shouldDiscoverEvents(),
            'A descoberta automática duplicaria o mapa explícito de ouvintes.'
        );
    }
}
