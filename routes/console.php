<?php

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('departamentos:fix-null-gabinete {--gabinete_id=} {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $gabineteId = $this->option('gabinete_id');

    $count = Departamento::whereNull('gabinete_id')->count();
    if ($count === 0) {
        $this->info('Nenhum departamento com gabinete_id nulo encontrado.');

        return 0;
    }

    $this->comment("Departamentos com gabinete_id nulo: {$count}");

    if ($dryRun) {
        Departamento::whereNull('gabinete_id')
            ->select(['id', 'nome'])
            ->orderBy('nome')
            ->chunk(50, function ($chunk) {
                foreach ($chunk as $dep) {
                    $this->line(" - [{$dep->id}] {$dep->nome}");
                }
            });
        $this->warn('Execução em modo dry-run. Nenhuma alteração realizada.');

        return 0;
    }

    if (! $gabineteId) {
        $this->error('Informe um gabinete de destino com --gabinete_id=ID');

        return 1;
    }

    $gabinete = Gabinete::find($gabineteId);
    if (! $gabinete) {
        $this->error("Gabinete {$gabineteId} não encontrado.");

        return 1;
    }

    $updated = Departamento::whereNull('gabinete_id')->update(['gabinete_id' => $gabineteId]);
    $this->info("{$updated} departamentos atualizados para gabinete_id={$gabineteId} ({$gabinete->nome}).");

    return 0;
})->purpose('Corrigir departamentos com gabinete_id nulo (dry-run disponível)');

// Enviar Web Push de teste (classe alternativa em App\Console\Commands\TestWebPushCommand)
Artisan::command('push:test {--user=} {--title=Notificação de teste} {--body=Este é um push de teste.} {--url=}', function () {
    $publicKey = env('VAPID_PUBLIC_KEY');
    $privateKey = env('VAPID_PRIVATE_KEY');
    $subject = env('VAPID_SUBJECT', 'mailto:admin@example.com');

    if (! $publicKey || ! $privateKey) {
        $this->error('VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY não configurados no .env');

        return 1;
    }

    $userId = $this->option('user');
    $query = \App\Models\PushSubscription::query();
    if ($userId) {
        $query->where('user_id', $userId);
    }
    $subs = $query->get();

    if ($subs->isEmpty()) {
        $this->warn('Nenhuma assinatura encontrada. Acesse o app, aceite permissões e recarregue.');

        return 0;
    }

    $payload = [
        'title' => $this->option('title') ?? 'Notificação de teste',
        'body' => $this->option('body') ?? 'Este é um push de teste.',
        'url' => $this->option('url') ?? url('/'),
    ];

    $auth = [
        'VAPID' => [
            'subject' => $subject,
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ],
    ];

    $webPush = new Minishlink\WebPush\WebPush($auth, ['TTL' => 300]);

    $this->info('Enviando push para '.$subs->count().' assinatura(s)...');

    foreach ($subs as $sub) {
        $subscription = Minishlink\WebPush\Subscription::create([
            'endpoint' => $sub->endpoint,
            'publicKey' => $sub->p256dh,
            'authToken' => $sub->auth,
            'contentEncoding' => 'aes128gcm',
        ]);

        $report = $webPush->sendOneNotification($subscription, json_encode($payload));

        if ($report->isSuccess()) {
            $this->line("✔ Enviado para {$sub->endpoint}");
        } else {
            $this->error("✖ Falha para {$sub->endpoint}: ".$report->getReason());
            $statusCode = $report->getResponse()?->getStatusCode();
            if (in_array($statusCode, [404, 410])) {
                $sub->delete();
                $this->warn('Assinatura removida (endpoint inválido).');
            }
        }
    }

    $webPush->flush();

    $this->info('Concluído.');

    return 0;
})->purpose('Enviar uma notificação Web Push de teste para assinaturas salvas');

Artisan::command('push:vapid:generate', function () {
    try {
        $keys = Minishlink\WebPush\VAPID::createVapidKeys();
    } catch (\Throwable $e) {
        $this->error('Falha ao gerar VAPID: '.$e->getMessage());

        return 1;
    }
    $this->info('VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->info('VAPID_PRIVATE_KEY='.$keys['privateKey']);
    $this->info('VAPID_SUBJECT=mailto:admin@example.com');
    $this->line('Copie as linhas acima para o seu .env');

    return 0;
})->purpose('Gerar chaves VAPID para Web Push');

Artisan::command('docs:reorganize-storage {--apply} {--disk=} {--year=} {--gabinete=} {--departamento=}', function () {
    $apply = (bool) $this->option('apply');
    $diskOpt = $this->option('disk');
    $disk = $diskOpt ?: config('filesystems.docs_disk', 'public');
    $year = $this->option('year');
    $gabineteId = $this->option('gabinete');
    $departamentoId = $this->option('departamento');

    $q = DocumentoEntrada::query()->with(['departamento.gabinete', 'anexos']);
    if ($year) {
        $q->where('ano_referencia', (int) $year);
    }
    if ($departamentoId) {
        $q->where('departamento_id', (int) $departamentoId);
    }
    if ($gabineteId) {
        $q->whereHas('departamento', function ($qq) use ($gabineteId) {
            $qq->where('gabinete_id', (int) $gabineteId);
        });
    }

    $total = $q->count();
    $this->info("Documentos: {$total}");
    $moved = 0;
    $skipped = 0;
    $q->orderBy('ano_referencia')->orderBy('numero_sequencial')->chunk(100, function ($docs) use (&$moved, &$skipped, $apply, $disk) {
        foreach ($docs as $doc) {
            $dep = $doc->departamento;
            $gab = optional($dep)->gabinete;
            $depSlug = Str::slug(optional($dep)->nome ?? 'sem-departamento');
            $gabSlug = Str::slug(optional($gab)->sigla ?? (optional($gab)->nome ?? 'sem-gabinete'));
            $base = 'documentos_entradas/'.((int) $doc->ano_referencia).'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', (int) $doc->numero_sequencial);

            if ($doc->arquivo_caminho) {
                $old = $doc->arquivo_caminho;
                $new = $base.'/'.basename($old);
                if (! str_starts_with($old, $base.'/')) {
                    if ($apply) {
                        Storage::disk($disk)->makeDirectory($base);
                        if (Storage::disk($disk)->exists($old)) {
                            Storage::disk($disk)->move($old, $new);
                            $doc->arquivo_caminho = $new;
                            $doc->save();
                            $moved++;
                            $this->line("✔ Documento {$doc->id} movido: {$new}");
                        } else {
                            $skipped++;
                            $this->warn("✖ Arquivo ausente: {$old}");
                        }
                    } else {
                        $this->line("→ Documento {$doc->id} mover: {$old} -> {$new}");
                    }
                } else {
                    $skipped++;
                }
            }

            foreach ($doc->anexos as $an) {
                $old = $an->caminho_arquivo;
                if (! $old) {
                    continue;
                }
                $newBase = $base.'/anexos';
                $new = $newBase.'/'.basename($old);
                if (! str_starts_with($old, $newBase.'/')) {
                    if ($apply) {
                        Storage::disk($disk)->makeDirectory($newBase);
                        if (Storage::disk($disk)->exists($old)) {
                            Storage::disk($disk)->move($old, $new);
                            $an->caminho_arquivo = $new;
                            $an->save();
                            $moved++;
                            $this->line("✔ Anexo {$an->id} movido: {$new}");
                        } else {
                            $skipped++;
                            $this->warn("✖ Arquivo ausente: {$old}");
                        }
                    } else {
                        $this->line("→ Anexo {$an->id} mover: {$old} -> {$new}");
                    }
                } else {
                    $skipped++;
                }
            }
        }
    });

    $this->info("Concluído. Movidos: {$moved}. Ignorados: {$skipped}.");

    return 0;
})->purpose('Reorganizar arquivos de documentos por ano/gabinete/departamento (dry-run por padrão)');

Artisan::command('docs:migrate-storage {--apply} {--from-disk=} {--to-disk=} {--year=} {--gabinete=} {--departamento=}', function () {
    $apply = (bool) $this->option('apply');
    $from = $this->option('from-disk') ?: 'public';
    $to = $this->option('to-disk') ?: config('filesystems.docs_disk', 'public');
    $year = $this->option('year');
    $gabineteId = $this->option('gabinete');
    $departamentoId = $this->option('departamento');

    if ($from === $to) {
        $this->warn('De e para o mesmo disco. Use docs:reorganize-storage.');

        return 1;
    }

    $q = DocumentoEntrada::query()->with(['departamento.gabinete', 'anexos']);
    if ($year) {
        $q->where('ano_referencia', (int) $year);
    }
    if ($departamentoId) {
        $q->where('departamento_id', (int) $departamentoId);
    }
    if ($gabineteId) {
        $q->whereHas('departamento', function ($qq) use ($gabineteId) {
            $qq->where('gabinete_id', (int) $gabineteId);
        });
    }

    $total = $q->count();
    $this->info("Documentos: {$total}");
    $migrated = 0;
    $skipped = 0;
    $missing = 0;
    $q->orderBy('ano_referencia')->orderBy('numero_sequencial')->chunk(100, function ($docs) use (&$migrated, &$skipped, &$missing, $apply, $from, $to) {
        foreach ($docs as $doc) {
            $dep = $doc->departamento;
            $gab = optional($dep)->gabinete;
            $depSlug = Str::slug(optional($dep)->nome ?? 'sem-departamento');
            $gabSlug = Str::slug(optional($gab)->sigla ?? (optional($gab)->nome ?? 'sem-gabinete'));
            $base = 'documentos_entradas/'.((int) $doc->ano_referencia).'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', (int) $doc->numero_sequencial);

            if ($doc->arquivo_caminho) {
                $old = $doc->arquivo_caminho;
                $new = $base.'/'.basename($old);
                $existsFrom = Storage::disk($from)->exists($old);
                $existsTo = Storage::disk($to)->exists($new);
                if ($existsTo) {
                    $skipped++;

                    continue;
                }
                if (! $existsFrom) {
                    $missing++;
                    $this->warn("✖ Ausente (from): {$old}");

                    continue;
                }
                if ($apply) {
                    Storage::disk($to)->makeDirectory($base);
                    $contents = Storage::disk($from)->get($old);
                    Storage::disk($to)->put($new, $contents);
                    Storage::disk($from)->delete($old);
                    $doc->arquivo_caminho = $new;
                    $doc->save();
                    $migrated++;
                    $this->line("✔ Documento {$doc->id} migrado: {$new}");
                } else {
                    $this->line("→ Documento {$doc->id} migrar: {$old} -> {$new}");
                }
            }

            foreach ($doc->anexos as $an) {
                $old = $an->caminho_arquivo;
                if (! $old) {
                    continue;
                }
                $newBase = $base.'/anexos';
                $new = $newBase.'/'.basename($old);
                $existsFrom = Storage::disk($from)->exists($old);
                $existsTo = Storage::disk($to)->exists($new);
                if ($existsTo) {
                    $skipped++;

                    continue;
                }
                if (! $existsFrom) {
                    $missing++;
                    $this->warn("✖ Ausente (from): {$old}");

                    continue;
                }
                if ($apply) {
                    Storage::disk($to)->makeDirectory($newBase);
                    $contents = Storage::disk($from)->get($old);
                    Storage::disk($to)->put($new, $contents);
                    Storage::disk($from)->delete($old);
                    $an->caminho_arquivo = $new;
                    $an->save();
                    $migrated++;
                    $this->line("✔ Anexo {$an->id} migrado: {$new}");
                } else {
                    $this->line("→ Anexo {$an->id} migrar: {$old} -> {$new}");
                }
            }
        }
    });

    $this->info("Concluído. Migrados: {$migrated}. Ignorados: {$skipped}. Ausentes: {$missing}.");

    return 0;
})->purpose('Migrar arquivos entre discos (ex.: public → local) mantendo a hierarquia');
