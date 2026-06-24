<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DadosInstituicao extends Model
{
    use HasFactory;

    protected $table = 'dados_instituicao';

    protected $fillable = [
        'nome_oficial',
        'sigla',
        'cidade',
        'nif',
        'telefone',
        'email',
        'endereco',
        'logo_path',
        'cabecalho_linha1',
        'cabecalho_linha2',
        'cabecalho_linha3',
        'rodape_texto',
        'rodape_img_path',
    ];

    /**
     * Retorna a URL pública do logótipo.
     */
    public function getLogoUrlAttribute(): string
    {
        if ($this->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->logo_path)) {
            return asset('storage/'.$this->logo_path);
        }

        return asset('images/insignia.png');
    }

    /**
     * Retorna o caminho absoluto do logótipo para o Dompdf.
     */
    public function getLogoAbsolutePathAttribute(): string
    {
        if ($this->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->logo_path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->path($this->logo_path);
        }

        return public_path('images/insignia.png');
    }

    /**
     * Retorna a URL pública do rodapé.
     */
    public function getRodapeUrlAttribute(): ?string
    {
        if ($this->rodape_img_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->rodape_img_path)) {
            return asset('storage/'.$this->rodape_img_path);
        }

        return null;
    }

    /**
     * Retorna o caminho absoluto do rodapé para o Dompdf.
     */
    public function getRodapeAbsolutePathAttribute(): ?string
    {
        if ($this->rodape_img_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->rodape_img_path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->path($this->rodape_img_path);
        }

        return null;
    }
}
