# RETEC SP — Documentação do Projeto

> Última atualização: 2025  
> Responsável: eliett.designer@gmail.com

---

## 1. Visão Geral

**RETEC SP** é um site de landing page para locação de caçambas na Grande São Paulo.  
O objetivo do site é gerar leads via formulário de contato e botão de WhatsApp.

**Stack:** HTML5 + CSS3 + JavaScript vanilla — sem frameworks, sem build tools, arquivos estáticos servidos diretamente em servidor Linux.

---

## 2. Mapa de Arquivos

| Arquivo | Status | Descrição |
|---|---|---|
| `index.html` | ✅ **No ar (produção)** | Versão original simplificada — apenas hero + formulário → WhatsApp |
| `index2.html` | 🗄️ Referência/arquivo | Versão dev anterior com todas as seções, sem GTM |
| `index3.html` | 🚀 **Nova versão principal** | Versão completa, pronta para substituir index.html |
| `areas-atendimento.html` | ✅ Criado | Página de áreas atendidas com UX de acordeão + chips |
| `areas-atendidas.html` | ✅ Corrigido | Página original de áreas (bug WhatsApp corrigido) |
| `termos-uso.html` | ⏳ Placeholder | Termos de Uso — "em atualização" |
| `privacidade.html` | ⏳ Placeholder | Política de Privacidade — "em atualização" |
| `style.css` | ✅ Compartilhado | Folha de estilos base para todas as páginas |
| `img/` | ✅ | Pasta de imagens |

### Imagens em uso

| Arquivo | Uso |
|---|---|
| `img/hero.webp` | Background do hero em index3.html |
| `img/logo-Retec-360.webp` | Logo desktop (header, rodapé) |
| `img/logo-Retec-180.webp` | Logo mobile (em index.html) |
| `img/retecsp-no-google-maps.webp` | Imagem do Google Maps na seção "Quem Somos" |
| `img/Retecsp-Logo-branco.webp` | Logo versão branca (footer index2) |

---

## 3. Paleta de Cores

Definidas como variáveis CSS no `:root` de `style.css`:

| Variável | Hex | Uso |
|---|---|---|
| `--primary-blue` | `#003352` | Cor principal — header, botões, títulos, footer |
| `--secondary-blue` | `#00263d` | Hover de botões, destaques escuros |
| `--accent-green` | `#2ecc71` | CTA, ícones de benefícios, destaques positivos |
| `--accent-yellow` | `#f1c40f` | Avisos, badges de destaque |
| `--accent-red` | `#e74c3c` | Alertas, itens proibidos (guia de descarte) |
| `--light-gray` | `#f8f9fa` | Fundo de seções alternadas |
| `--dark-gray` | `#2c3e50` | Textos secundários, subtítulos |
| `--white` | `#ffffff` | Fundo padrão, textos sobre fundos escuros |

---

## 4. Tipografia

- **Fonte principal:** `Inter` (Google Fonts)
- **Pesos carregados:** 400 (regular), 600 (semi-bold), 700 (bold), 800 (extra-bold)
- **Carregamento:** `<link rel="preconnect">` para `fonts.googleapis.com` e `fonts.gstatic.com` + `display=swap`
- **Aplicação:** `font-family: 'Inter', sans-serif` em `body` via `style.css`

---

## 5. Estrutura de Seções — index3.html

| Ordem | Seção | ID / Âncora | Descrição |
|---|---|---|---|
| 1 | Header | — | Logo + nav sticky, hamburguer mobile |
| 2 | Hero | `#home` | Headline, subheadline, formulário → WhatsApp |
| 3 | Como Funciona | `#como-funciona` | 4 passos ilustrados com ícones |
| 4 | Tipos de Caçamba | `#tipos` | Cards 4m³ e 26m³, botão individual por tipo |
| 5 | Diferenciais | `#diferenciais` | 6 cards de features/benefícios |
| 6 | Quem Somos | `#quem-somos` | Texto + imagem Google Maps |
| 7 | Áreas de Atendimento | `#areas` | Mapa de texto + link para areas-atendimento.html |
| 8 | Logística | `#logistica` | Informações de entrega e retirada |
| 9 | Guia de Descarte | `#guia` | 2 caixas: verde (permitido) e vermelha (proibido) |
| 10 | FAQ | `#faq` | Perguntas frequentes em acordeão |
| 11 | Contato | `#contato` | Formulário AJAX FormSubmit |
| 12 | Footer | — | Links, endereço, redes sociais, links legais |
| 13 | WhatsApp Float | — | Botão flutuante de WhatsApp |

---

## 6. Analytics e Rastreamento

### Google Tag Manager

- **ID:** `GTM-MRNG2HFG`
- **Presente em:** `index3.html`, `areas-atendimento.html`, `termos-uso.html`, `privacidade.html`
- **Implementação:** Script no `<head>` + fallback `<noscript>` imediatamente após `<body>`

### IDs de Botões para Eventos GTM

| ID do Botão | Elemento | Ação esperada |
|---|---|---|
| `btn_orcamento_home` | Botão do formulário hero | Clique → abre WhatsApp com mensagem |
| `btn_tipo_4m3` | Botão "Solicitar 4m³" | Clique → abre WhatsApp |
| `btn_tipo_26m3` | Botão "Solicitar 26m³" | Clique → abre WhatsApp |
| `btn_contato_enviar` | Botão do formulário de contato | Clique → submete form AJAX |

> Configurar no GTM: disparar tags de evento ao clique nos IDs acima (tipo `Click — Just Links` ou `All Elements`).

---

## 7. Formulários

### 7.1 Formulário Hero (→ WhatsApp)

- **Localização:** Seção hero, `index3.html`
- **Funcionamento:** Coleta nome + serviço desejado, monta URL do WhatsApp com mensagem pré-preenchida e abre `wa.me/551137122416`
- **Validação:** Checkbox LGPD obrigatório (JS verifica antes de redirecionar)
- **Sem envio de e-mail**, apenas redirecionamento para WhatsApp

### 7.2 Formulário de Contato (→ FormSubmit AJAX)

- **Localização:** Seção `#contato`, `index3.html`
- **Endpoint:** `https://formsubmit.co/ajax/eliett.designer@gmail.com`
- **Método:** `POST` com `fetch()` vanilla JS
- **Campos enviados:**
  - `name` — nome completo
  - `email` — e-mail
  - `phone` — telefone
  - `message` — mensagem
  - `_subject` (hidden) — assunto fixo: "Novo contato via site RETEC SP"
  - `_autoresponse` (hidden) — e-mail automático de confirmação ao remetente
  - `_captcha` (hidden) — `false` (desabilitado, remover em produção se houver spam)
  - `_template` (hidden) — `table`
- **Fluxo de sucesso:**  
  1. Desabilita botão + exibe "Enviando..."
  2. Envia via `fetch()` (sem reload de página)
  3. Exibe mensagem de sucesso inline (verde)
  4. Limpa todos os campos do formulário
  5. Após 3 segundos, rola a página para `#home` (topo/hero)
- **Fluxo de erro:** Exibe mensagem de erro inline (vermelho) e reabilita o botão
- **E-mail automático ao remetente:** Sim — confirmação de recebimento enviada pelo FormSubmit para o e-mail informado no campo `email`
- **E-mail destino:** `eliett.designer@gmail.com`

---

## 8. WhatsApp

- **Número correto:** `551137122416` (DDI 55 + DDD 11 + número sem 9 extra)
- **URL base:** `https://wa.me/551137122416`
- **Botão flutuante:** presente em todas as páginas (via `style.css` + elemento `.whatsapp-float`)
- **Histórico:** `areas-atendidas.html` tinha o número errado `5511371224169` em 9 links — corrigido.

---

## 9. SEO e Meta Tags (index3.html)

```html
<!-- Básico -->
<title>RETEC SP | Aluguel de Caçambas em São Paulo, Osasco e Barueri</title>
<meta name="description" content="...">
<link rel="canonical" href="https://www.retecsp.com.br/">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:url" content="https://www.retecsp.com.br/">
<meta property="og:title" content="...">
<meta property="og:description" content="...">
<meta property="og:image" content="https://retecsp.com.br/img/hero.webp">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="RETEC SP">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="...">
<meta name="twitter:description" content="...">
<meta name="twitter:image" content="https://retecsp.com.br/img/hero.webp">
```

### JSON-LD Estruturado

- **LocalBusiness** — nome, telefone, endereço, geo, horário, URL
- **FAQPage** — perguntas e respostas das perguntas frequentes

---

## 10. Ícones

- **Biblioteca:** FontAwesome 6 (Kit)
- **Kit ID:** `ca3c4a97cf`
- **CDN:** `https://kit.fontawesome.com/ca3c4a97cf.js`
- **Carregamento:** `defer` no `<script>`

---

## 11. Arquitetura CSS

- **`style.css`** — estilos compartilhados por todas as páginas: variáveis, reset, header, hero, seções, footer, WhatsApp float, responsividade
- **`<style>` inline** em cada HTML — overrides e estilos específicos da página
- **Container:** `max-width: 1200px`, centralizado com `margin: auto`
- **Scroll offset:** `scroll-padding-top: 90px` para compensar o header sticky ao navegar via âncoras

---

## 12. Áreas de Atendimento

### areas-atendimento.html

- **UX:** Acordeão por macro-região; bairros exibidos como chips/tags
- **Regiões disponíveis:**
  - São Paulo Capital (`#saopaulo`) — aberta por padrão
  - Osasco e Região (`#osasco`)
  - Barueri e Alphaville (`#barueri`)
  - Santana de Parnaíba e Pirapora (`#santana`)
  - Taboão da Serra e Entorno (`#taboao`)
  - Outras Cidades (`#outras`)
- **Abertura automática via hash:** ao acessar `areas-atendimento.html#osasco`, a região Osasco abre com suavidade
- **CTA por região:** botão "Solicitar caçamba em [Região]" com link direto ao WhatsApp

---

## 13. Links Externos Ativos

| Serviço | URL |
|---|---|
| WhatsApp | `https://wa.me/551137122416` |
| FormSubmit | `https://formsubmit.co/ajax/eliett.designer@gmail.com` |
| Google Fonts | `https://fonts.googleapis.com/css2?family=Inter...` |
| FontAwesome Kit | `https://kit.fontawesome.com/ca3c4a97cf.js` |
| GTM Script | `https://www.googletagmanager.com/gtm.js?id=GTM-MRNG2HFG` |

---

## 14. O que Falta Implementar

### Alta prioridade

- [ ] **Ativar index3.html em produção** — renomear para `index.html` ou configurar redirect no servidor
- [ ] **Verificar FormSubmit** — acessar `formsubmit.co` e confirmar o e-mail `eliett.designer@gmail.com` (a primeira submissão exige confirmação por e-mail)
- [ ] **Conteúdo legal real** — substituir placeholder de `termos-uso.html` e `privacidade.html` por texto oficial revisado por advogado

### Média prioridade

- [ ] **Favicon real** — criar `favicon.ico` e `img/favicon-32x32.png` com a identidade da RETEC SP
- [ ] **Imagem OG** — garantir que `img/hero.webp` está acessível publicamente na URL `https://retecsp.com.br/img/hero.webp` para o Open Graph funcionar
- [ ] **GTM — configurar triggers** — criar tags de evento no GTM para os IDs de botão (`btn_orcamento_home`, `btn_tipo_4m3`, `btn_tipo_26m3`, `btn_contato_enviar`)
- [ ] **Redes sociais** — adicionar links reais de Instagram e LinkedIn no footer (atualmente sem href)
- [ ] **Google Maps embed** — substituir imagem estática `retecsp-no-google-maps.webp` por iframe de mapa interativo

### Baixa prioridade / Futuro

- [ ] **Calculadora de Entulho** — feature interativa: usuário informa m³ de entulho estimado e recebe sugestão de tamanho de caçamba + CTA
- [ ] **Páginas de cidade** — landing pages individuais para cada cidade/região atendida (Osasco, Barueri, Santana de Parnaíba etc.) para SEO local
- [ ] **Blog / Conteúdo** — artigos sobre gestão de resíduos, LGPD, licenciamento CETESB para SEO editorial
- [ ] **Integração com CRM/planilha** — além do e-mail, enviar leads para Google Sheets via Zapier/Make ou similar
- [ ] **Schema de Review** — adicionar JSON-LD de avaliações de clientes para rich snippets no Google

---

## 15. Notas de Deploy

1. Testar localmente em `localhost` ou servidor de staging antes de subir `index3.html` para produção
2. Ao ir ao ar com `index3.html`, fazer backup do `index.html` atual antes de sobrescrever
3. O `style.css` é compartilhado — qualquer alteração impacta todas as páginas
4. Arquivos com extensão `:Zone.Identifier` são metadados do Windows (baixados de outro sistema) e podem ser ignorados/deletados no servidor Linux
5. FormSubmit é gratuito para até 1000 envios/mês no plano free; verificar limites conforme volume de leads cresce
