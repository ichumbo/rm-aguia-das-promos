# CRM Águia das Promos

CRM web operacional desenvolvido para o **Águia das Promos** organizar contatos interessados, gerenciar grupos de ofertas, registrar autorizações (opt-in) e facilitar o envio individual de convites pelo WhatsApp — seja pelo **Modo Assistido (`wa.me`)** ou pelo **Modo Oficial (WhatsApp Business Platform / Cloud API)**.

> **Limites explícitos do MVP:** O sistema não realiza extração de participantes de outros grupos, leitura automatizada do WhatsApp Web, compra de listas, adição automática de pessoas a grupos nem contorno de bloqueios. A entrada no grupo é voluntária pelo link oficial recebido, e a confirmação de entrada no CRM é manual.

---

## Stack e Versões Verificadas

| Componente | Versão instalada / requisito | Observação |
|---|---|---|
| PHP | `^8.2` (testado em `8.5.10`) | Extensões: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`, `xml`, `zip`, `gd` |
| Laravel Framework | `v12.72.0` | Monolito único + starter kit oficial (`laravel/livewire-starter-kit v1.0.1`) |
| Livewire | `v4.4.8` | Componentes interativos no servidor |
| Livewire Volt & Flux UI | `volt v1.11.2` / `flux v2.21.0` | Autenticação do starter kit e componentes UI |
| Tailwind CSS & Vite | `tailwindcss ^4.0.7` / `vite v6.1.1` | Compilação de assets com identidade própria Águia das Promos |
| QR Code (local) | `bacon/bacon-qr-code v3.1.1` | Geração de QR Code SVG no servidor, sem dependência de CDN externo |
| Banco de dados | MySQL `8.0+` (produção) | Testes automatizados usam SQLite `:memory:` via `phpunit.xml` |
| Filas | Driver `database` | Persistido em `jobs` e `failed_jobs` |
| Testes | PHPUnit `11.5.58` | Suite unitária e de integração |

---

## Arquitetura e Módulos

- **Dashboard (`/dashboard`):** Métricas estritamente separadas em 3 blocos: **Operação Real (API Oficial)**, **Registros Manuais (Modo Assistido)** e **Dados de Demonstração/Simulação**, além de saúde da fila e alerta de worker parado.
- **Contatos (`/contatos`):** Tabela ampla no desktop e cartões responsivos no celular; busca superior por nome/telefone; filtros combináveis (etapa, autorização, grupo, origem, fonte, elegíveis); ordenação e paginação no servidor; ficha lateral com notas livres (separadas da prova de autorização) e linha do tempo; cadastro manual e importação (CSV ou colagem `nome;telefone;origem;interesse`) com prévia e mapeamento de colunas.
- **Grupos (`/grupos`):** Cadastro de grupos oficiais, link de convite fornecido pelo admin, observação manual de capacidade, revogação e substituição auditada de link.
- **Convites (`/convites`):** Fila de envios, status técnico separado da etapa comercial, confirmação manual no modo assistido, reenvio explícito com justificativa auditada, tabela de `failed_jobs` e pausa/retomada de fila.
- **Modelos de mensagem (`/modelos`):** Templates Marketing/Utility com validação de variáveis (`{{nome}}`, `{{categoria}}`, `{{link_grupo}}`), bloqueio automático de modelos `PAUSED`/`REJECTED`/`DISABLED` e sincronização com a Meta Graph API.
- **Captação (`/captacao` e `/inscrever`):** Página pública responsiva com seleção de categorias, DDI explícito, caixa de autorização **desmarcada por padrão**, aviso de privacidade e gravação da versão do termo em `consent_events`; link compartilhável e QR Code SVG local.
- **Configurações (`/configuracoes`, exclusivo Admin):** Alternância entre Modo Assistido, API Oficial e Modo Simulado; checklist seguro de variáveis de ambiente (sem expor segredos no browser); paleta visual editável (`#F9C80E`, `#171717`, `#FFFFFF`, `#F5F6F8`); política de retenção configurável; gestão de usuários (`admin` / `operator`); e logs de auditoria.

---

## Requisitos de Hospedagem

Escolha um servidor ou VPS capaz de executar **PHP 8.2+ (CLI e FPM)**, **MySQL 8.0+**, **Nginx/Apache com HTTPS (TLS)** e **processo contínuo em background (Supervisor ou systemd)** para o worker de filas.

> **Importante:** Não presuma suporte em hospedagem compartilhada comum (shared hosting), pois o envio assíncrono via `php artisan queue:work` exige worker persistente.

---

## Instalação e Build (Ambiente de Produção / Homologação)

```bash
# 1. Clonar repositório e entrar na pasta
cd crm-aguia-das-promos

# 2. Instalar dependências PHP e Node
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# 3. Configurar ambiente
cp .env.example .env
php artisan key:generate

# 4. Editar .env com banco MySQL, APP_URL=https://seu-dominio.com.br e APP_ENV=production
# (Ver seção de credenciais Meta abaixo; nunca commitar .env)

# 5. Executar migrations e seed inicial seguro (apenas configurações padrão e modelo base)
php artisan migrate --force
php artisan db:seed --force

# 6. Otimizar caches em produção
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Desenvolvimento Local (com dados fictícios de demonstração)

Em `APP_ENV=local`, `php artisan db:seed` executa automaticamente `DevDemoSeeder`, criando usuários e contatos fictícios marcados com `record_source = demo`:
- **Admin demo:** `admin@aguiadaspromos.local` / senha: `password`
- **Operador demo:** `operador@aguiadaspromos.local` / senha: `password`

Um CSV de exemplo para testar a importação está disponível em `docs/contatos-exemplo.csv`.

---

## Worker Persistente e Scheduler

### 1. Supervisor (`/etc/supervisor/conf.d/aguia-crm-worker.conf`)

```ini
[program:aguia-crm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/crm-aguia-das-promos/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=30
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/crm-aguia-das-promos/storage/logs/worker.log
stopwaitsecs=3600
```

Aplicar alterações:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start aguia-crm-worker:*
```

### 2. Scheduler (Cron)

Adicione ao crontab do usuário da aplicação (`crontab -e`) para executar o heartbeat do worker e tarefas agendadas:
```cron
* * * * * cd /var/www/crm-aguia-das-promos && php artisan schedule:run >> /dev/null 2>&1
```

---

## HTTPS Obrigatório

- O webhook da Meta (`https://seu-dominio.com.br/webhooks/whatsapp`) exige **HTTPS válido com certificado TLS** (Let's Encrypt / Certbot ou Cloudflare Full Strict).
- Configure `APP_URL=https://seu-dominio.com.br` no `.env` e garanta que o proxy reverso encaminhe `X-Forwarded-Proto` e o corpo bruto (`raw body`) para validação do cabeçalho `X-Hub-Signature-256`.

---

## Configuração Real da Meta (WhatsApp Business Platform / Cloud API)

Sem credenciais configuradas, o CRM exibe **"API não configurada"** e permite operar imediatamente no **Modo Assistido (`wa.me`)** ou realizar testes locais no **Modo Simulado**.

Para ativar o **Modo Oficial (envio com 1 clique)**:

1. **Meta Business Portfolio:** Crie ou acesse seu Business Portfolio verificado em `business.facebook.com`.
2. **WABA (WhatsApp Business Account):** Crie/vincule a conta do WhatsApp Business no portfólio e anote o **WABA ID** (`WHATSAPP_WABA_ID`).
3. **Número habilitado & Migração/Coexistência:**
   - Verifique na documentação oficial da Meta as regras vigentes de elegibilidade do número, migração ou coexistência antes de alterar o número atual do WhatsApp Business App.
   - **Atenção:** Não há garantia de manutenção automática de histórico de conversas do WhatsApp tradicional ao migrar para a Cloud API.
   - Anote o **Phone Number ID** (`WHATSAPP_PHONE_NUMBER_ID`).
4. **Aplicativo Meta & Permissões:**
   - No Meta for Developers (`developers.facebook.com`), crie um app do tipo **Business** e adicione o produto **WhatsApp**.
   - Anote o **App Secret** (`WHATSAPP_APP_SECRET`) em *App Settings > Basic*.
   - Gere um **System User Token** permanente no Business Settings com permissões `whatsapp_business_messaging` e `whatsapp_business_management` (`WHATSAPP_ACCESS_TOKEN`).
5. **Webhook Público HTTPS:**
   - Em *WhatsApp > Configuration > Webhook*, informe:
     - **Callback URL:** `https://seu-dominio.com.br/webhooks/whatsapp`
     - **Verify Token:** mesmo valor definido em `WHATSAPP_VERIFY_TOKEN` no `.env`.
   - Assine os campos **`messages`** (para status de entrega/leitura e recebimento de `SAIR`/`PARAR`).
6. **Modelo Aprovado:**
   - No WhatsApp Manager, crie o template na categoria **`MARKETING`** (ex.: `convite_grupo_aguia`, idioma `pt_BR`) com o texto sugerido adaptado ao consentimento real e às variáveis `{{1}}`, `{{2}}`, `{{3}}` (ou variáveis nomeadas equivalentes).
   - Aguarde aprovação da Meta e clique em **"Sincronizar status na Meta"** em `/modelos`.
   - **Custos e limites:** Tarifação por conversa/mensagem, limites de envio por tier e aprovação dos templates dependem exclusivamente das regras comerciais e políticas da Meta vigentes na ativação.

---

## Homologação do Envio Real (Status Atual)

- **Testes automatizados em ambiente local:** Todos os fluxos críticos (normalização E.164, importação, bloqueio sem autorização, descadastro, `wa.me`, idempotência, clique duplo, timeout incerto, assinatura inválida e deduplicação de webhooks) foram validados via PHPUnit com HTTP/Queue simulados.
- **Envio real em aparelho físico:** **Pendente de ativação externa** (credenciais de produção da Meta Business Platform, WABA, Phone Number ID, App Secret, webhook público HTTPS e template aprovado pela Meta). Ao ativar as variáveis no `.env` em produção, execute o roteiro:
  1. Cadastrar contato de teste autorizado pela página `/inscrever`.
  2. Enviar 1 convite em `/contatos` (modo `API Oficial`), conferir `provider_message_id` (`wamid...`) em `/convites`.
  3. Confirmar atualização de status (`sent` -> `delivered` -> `read`) via webhook e recebimento no aparelho.
  4. Responder `SAIR` no aparelho e confirmar bloqueio automático (`suppression_entries`) e cancelamento de fila no CRM.
  5. Confirmar entrada no grupo manualmente após clicar no link.

---

## Manual Curto do Operador

1. **Cadastrar ou importar contatos:** Em **Contatos**, cadastre manualmente ou use **Importar CSV / Colar linhas** (`nome;telefone;origem;interesse`). Revise a prévia antes de confirmar. Contatos importados sem evidência ficam como **Sem autorização** e bloqueados para envio.
2. **Coletar autorização:** Divulgue o link ou QR Code da página pública em **Captação**. Quem se inscreve marcando a caixa de autorização entra como **Autorizado**.
3. **Escolher grupo e convidar:** Na tabela **Contatos**, selecione o **Grupo de destino** e clique no botão principal:
   - **Modo Assistido:** Clique em **Abrir WhatsApp**. O WhatsApp abre com telefone e mensagem preenchidos. Após enviar no WhatsApp, clique em **Confirmar envio manual** no CRM.
   - **Modo API Oficial:** Clique em **Enviar convite**. O sistema valida autorização/bloqueio e coloca **Na fila** sem abrir o WhatsApp.
4. **Atualizar pipeline e descadastros:** Quando o contato responder ou entrar no grupo, use **Respondeu** ou **Entrou** (entrada é sempre confirmação manual). Se pedir para sair em linguagem natural, clique em **Não contatar** para aplicar bloqueio imediato e cancelar envios pendentes.

---

## Backup, Restauração, Rollback e Diagnóstico

### Backup e Restauração (MySQL)

```bash
# Backup consistente
mysqldump --single-transaction --routines --triggers -u crm_aguia -p crm_aguia_das_promos > backup-$(date +%F-%H%M).sql

# Restauração
mysql -u crm_aguia -p crm_aguia_das_promos < backup-2026-11-14-0100.sql
```

### Rollback

```bash
# 1. Ativar modo manutenção
php artisan down

# 2. Voltar código para tag/commit anterior
git checkout <commit-anterior>
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Se houver migration recente que precise reverter (avaliar impacto antes!)
php artisan migrate:rollback --step=1

# 4. Reiniciar worker e caches
php artisan config:cache && php artisan route:cache
sudo supervisorctl restart aguia-crm-worker:*
php artisan up
```

### Diagnóstico Rápido

- **Alerta de worker parado:** Verifique `sudo supervisorctl status aguia-crm-worker:*` e `storage/logs/worker.log`.
- **Jobs com falha:** Inspecione em `/convites` (tabela `failed_jobs`) ou execute `php artisan queue:failed`.
- **Status `Resultado incerto` (Timeout):** Indica timeout na chamada HTTP à Meta após possível recebimento pelo provedor. O sistema não reenvia automaticamente para evitar duplicidade; verifique no WhatsApp Manager antes de acionar **Reenviar** com justificativa.
- **Logs sanitizados:** Telefones são mascarados em `audit_logs` e webhooks; tokens da Meta são filtrados antes de gravar `error_message`.

---

## Testes Automatizados

Execute toda a suíte com:
```bash
./vendor/bin/phpunit
```
(Requer `npm run build` executado antes para gerar `public/build/manifest.json`.)
