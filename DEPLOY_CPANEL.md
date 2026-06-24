# Guia de Hospedagem no cPanel (InfinityLink)

Este guia foi preparado especificamente para hospedar o **GPN-AGIL** em ambientes de hospedagem compartilhada como a InfinityLink (cPanel).

## 1. Preparação Local (No seu computador)

Antes de enviar os arquivos, precisamos preparar a aplicação para produção.

### 1.1. Gerar Assets (CSS/JS)
Abra o terminal na pasta do projeto e execute:
```bash
npm run build
```
Isso criará os arquivos otimizados na pasta `public/build`.

### 1.2. Limpar Dependências
Para garantir que não enviamos arquivos de desenvolvimento desnecessários:
```bash
composer install --optimize-autoloader --no-dev
```
*(Nota: Se você não tiver o composer instalado globalmente ou der erro, pode pular o `--no-dev`, mas o upload será maior).*

### 1.3. Organizar os Arquivos para Upload
No cPanel, por segurança, **nunca** devemos colocar todo o código do Laravel dentro da pasta `public_html`. A estrutura correta é:

1.  Crie uma pasta chamada `gpn_project` (ou outro nome) no seu computador.
2.  Copie **TODOS** os arquivos do projeto para dentro dela, **EXCETO** a pasta `public`.
3.  Crie uma pasta chamada `public_html` (no seu computador mesmo).
4.  Copie **o conteúdo** da pasta `public` original do projeto para dentro desta nova pasta `public_html`.

**Agora você deve ter duas pastas para zipar separadamente:**
*   `gpn_project.zip`: Contém `app`, `bootstrap`, `config`, `vendor`, `.env`, etc. (Tudo menos a pasta `public`).
*   `public_html.zip`: Contém `index.php`, `.htaccess`, `build`, `images`, etc.

---

## 2. Configuração no cPanel

### 2.1. Banco de Dados
1.  Acesse o cPanel -> **Assistente de Banco de Dados MySQL**.
2.  Crie um banco de dados (ex: `infinit5_gpn`).
3.  Crie um usuário (ex: `infinit5_user`) e uma senha forte.
4.  **Importante:** Dê privilégios totais (All Privileges) ao usuário sobre o banco.
5.  Anote: Nome do Banco, Usuário e Senha.

### 2.2. Upload dos Arquivos
1.  Acesse o cPanel -> **Gerenciador de Arquivos**.
2.  Vá para a raiz (pasta `/home/infinit5/` ou `/mnt/home7/infinit5/` conforme sua imagem). **NÃO entre em public_html ainda.**
3.  Faça upload do arquivo `gpn_project.zip`.
4.  Extraia o arquivo. Agora você deve ter uma pasta `/home/infinit5/gpn_project` ao lado da pasta `public_html`.

### 2.3. Configuração do Site (public_html)
1.  Entre na pasta `public_html` do servidor.
2.  Apague o arquivo `index.html` padrão (da página "Parabéns").
3.  Faça upload do `public_html.zip` (que criamos no passo 1.3) e extraia.
4.  **Edite o arquivo `index.php`**:
    *   Clique com o botão direito em `index.php` -> Edit.
    *   Procure as linhas que carregam o `autoload.php` e o `app.php`.
    *   Altere para apontar para a pasta `gpn_project` que está um nível acima.

**Antes:**
```php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
```

**Depois (Ajuste o caminho conforme o nome da pasta que você criou):**
```php
require __DIR__.'/../gpn_project/vendor/autoload.php';
$app = require __DIR__.'/../gpn_project/bootstrap/app.php';
```
5.  Salve o arquivo.

---

## 3. Configuração do Ambiente (.env)

1.  Vá até a pasta `/home/infinit5/gpn_project`.
2.  Procure o arquivo `.env.example`.
3.  Renomeie para `.env` (ou duplique e renomeie).
4.  Edite o `.env`:

```ini
APP_NAME="GPN-AGIL"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://infinitylink.ao

# Banco de Dados (Use os dados criados no passo 2.1)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=infinit5_gpn
DB_USERNAME=infinit5_user
DB_PASSWORD=SuaSenhaForteAqui

# Email (Se tiver conta criada no cPanel)
MAIL_MAILER=smtp
MAIL_HOST=mail.infinitylink.ao
MAIL_PORT=465
MAIL_USERNAME=noreply@infinitylink.ao
MAIL_PASSWORD=SenhaDoEmail
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@infinitylink.ao"
```

---

## 4. Finalização (Setup Automático)

Como você pode não ter acesso fácil ao terminal (SSH) para rodar comandos, criei uma rota especial para fazer o trabalho pesado.

1.  Acesse no seu navegador:
    `https://infinitylink.ao/deploy-setup?key=InfinityDeploy2024!`

2.  Se tudo der certo, você verá uma mensagem:
    > "Setup concluído com sucesso! Migrações rodadas, storage linkado e caches gerados."

3.  Isso irá:
    *   Criar as tabelas no banco de dados.
    *   Configurar o link das imagens (storage).
    *   Otimizar o carregamento do site.

**Segurança:** Após verificar que o site está funcionando, edite o arquivo `routes/web.php` no servidor e remova ou comente essa rota de setup para que ninguém mais possa executá-la.

## 5. Resolução de Problemas Comuns

*   **Erro 500 (Internal Server Error)**: Geralmente é permissão de pasta.
    *   No Gerenciador de Arquivos, vá em `gpn_project`.
    *   Clique com botão direito em `storage` -> Permissions (Permissões).
    *   Marque 775 (ou 777 se necessário, mas 755/775 é mais seguro).
    *   Faça o mesmo para `bootstrap/cache`.

*   **Imagens não aparecem**:
    *   Verifique se a pasta `public_html/storage` existe. Se não, rode o setup novamente ou crie um link simbólico manualmente no cPanel (Cron Job: `ln -s /home/infinit5/gpn_project/storage/app/public /home/infinit5/public_html/storage`).
