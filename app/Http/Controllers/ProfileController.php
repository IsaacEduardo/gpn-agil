<?php

namespace App\Http\Controllers;

use App\Models\UserCertificate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Mostra o formulário para editar o perfil do usuário.
     *
     * @return \Illuminate\View\View
     */
    public function edit()
    {
        $user = Auth::user();
        $user->load('certificate');

        return view('profile.edit', compact('user'));
    }

    /**
     * Atualiza o perfil do usuário.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update($validated);

        return redirect()->route('profile.edit')->with('status', 'Perfil atualizado com sucesso!');
    }

    /**
     * Atualiza a senha do usuário.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('profile.edit')->with('status', 'Senha atualizada com sucesso!');
    }

    /**
     * Associa um novo certificado digital (PKCS#12) ao perfil.
     */
    public function uploadCertificate(Request $request)
    {
        $request->validate([
            'certificate_file' => ['required', 'file', 'max:5120'], // Max 5MB
            'certificate_password' => ['nullable', 'string'],
        ]);

        $user = Auth::user();
        $file = $request->file('certificate_file');
        $p12Content = file_get_contents($file->getRealPath());
        $p12Password = $request->input('certificate_password') ?? '';

        // Tentar ler o certificado para validar
        $certs = [];
        if (! openssl_pkcs12_read($p12Content, $certs, $p12Password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'certificate_password' => ['A senha informada do certificado está incorreta ou o arquivo está corrompido.'],
            ]);
        }

        // Parsear o certificado x509 para extrair metadados
        if (empty($certs['cert'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'certificate_file' => ['Arquivo de certificado digital inválido ou ausente do arquivo P12.'],
            ]);
        }

        $certData = openssl_x509_parse($certs['cert']);
        if (! $certData) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'certificate_file' => ['Não foi possível interpretar os dados do certificado digital.'],
            ]);
        }

        $issuer = isset($certData['issuer']['CN']) ? $certData['issuer']['CN'] : (isset($certData['issuer']['O']) ? $certData['issuer']['O'] : 'Emissor Desconhecido');
        $validFrom = Carbon::createFromTimestamp($certData['validFrom_time_t']);
        $validTo = Carbon::createFromTimestamp($certData['validTo_time_t']);

        // Extrair chave pública
        $pubKeyDetails = openssl_pkey_get_details(openssl_pkey_get_public($certs['cert']));
        $publicKey = $pubKeyDetails ? $pubKeyDetails['key'] : '';

        // Excluir certificados anteriores do mesmo usuário
        UserCertificate::where('user_id', $user->id)->delete();

        // Salvar novo certificado. A senha do P12 foi usada apenas para validar o ficheiro
        // e NÃO é persistida — será solicitada ao utilizador no momento de cada assinatura.
        // (o cast encripta automaticamente o campo encrypted_p12)
        UserCertificate::create([
            'user_id' => $user->id,
            'encrypted_p12' => $p12Content,
            'public_key' => $publicKey,
            'issuer' => $issuer,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
        ]);

        return redirect()->route('profile.edit')->with('status', 'Certificado digital associado com sucesso!');
    }

    /**
     * Remove o certificado digital do perfil.
     */
    public function destroyCertificate()
    {
        UserCertificate::where('user_id', Auth::user()->id)->delete();

        return redirect()->route('profile.edit')->with('status', 'Certificado digital removido com sucesso!');
    }
}
