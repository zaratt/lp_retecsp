# Padrões e Boas Práticas — RETEC SP

Documentação dos padrões visuais, estruturais e de performance utilizados na construção do site retecsp.com.br.

---

## 1. Paleta de Cores

Definida com CSS Custom Properties (variáveis) no `:root` dos arquivos `style.css` e `style-main.css`, utilizada de forma consistente em todas as páginas.

| Variável | Valor | Uso Principal |
|---|---|---|
| `--primary-blue` | `#003352` | Header, fundo do rodapé, botões CTA, `border-top` dos cards |
| `--secondary-blue` | `#00263d` | Hover de botões, seção "Sobre", fundo de seções escuras |
| `--accent-green` | `#22ac65` / `#167a42` | Todos os botões WhatsApp e CTAs principais |
| `--accent-yellow` | `#f1c40f` | Guia de descarte — categoria "Atenção" |
| `--accent-red` | `#c0392b` | Guia de descarte — categoria "Proibido" |
| `--light-gray` | `#f8f9fa` | Fundo de seções alternadas, formulários |
| `--dark-gray` | `#2c3e50` | Texto principal do `body` |
| `--white` | `#ffffff` | Fundo do header, fundo de cards |
| Gold (sem variável) | `#d6b030` | Ícones de check/confirmação em SVG no hero e rodapé |

**Exemplo de uso:**
```css
:root {
  --primary-blue: #003352;
  --secondary-blue: #00263d;
  --accent-green: #22ac65;
}
```

---

## 2. Tipografia

### Fonte principal: Roboto
Auto-hospedada em `/tiff/`, sem dependência de Google Fonts CDN.

```css
@font-face {
    font-family: 'Roboto';
    font-weight: 400;
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
@font-face {
    font-family: 'Roboto';
    font-weight: 700;
    font-display: swap;
    src: url('tiff/Roboto-Bold.woff2') format('woff2');
}
```

**Stack completa do `font-family`:**
```css
font-family: 'Roboto', Verdana, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
```

### Pesos utilizados
- `400` — texto corrido, parágrafos, labels
- `600` — links de navegação, textos secundários
- `700` — subtítulos, destaques
- `800` — `h1`, `h2`, CTAs principais

### Tamanhos responsivos com `clamp()`
```css
/* h1 nas páginas de cidade */
font-size: clamp(1.6rem, 3.2vw, 2.5rem);

/* h1 da home principal */
font-size: clamp(1.8rem, 4.5vw, 3rem);

/* Parágrafos do hero */
font-size: clamp(0.95rem, 1.1vw, 1.05rem);

/* Títulos de seção */
font-size: clamp(1.75rem, 2.8vw, 2.2rem);
```

### Fonte de ícones: Font Awesome 6 Free & Brands
- Versão: `6.7.2` via CDN jsDelivr
- Carregada de forma **não-bloqueante** (ver seção de Performance)
- Usada principalmente em `index3.html` e `areas-atendidas.html`
- Nas páginas de cidade (`aluguel_cacamba_*.html`): substituída por **SVG inline** (zero requisição extra)

---

## 3. Estrutura HTML Padrão

Todas as páginas seguem a mesma estrutura:

```html
<html lang="pt-BR">
<head>
  <!-- 1. preload da imagem hero (PRIMEIRO) -->
  <!-- 2. CSS crítico inline no <style> -->
  <!-- 3. Fontes: preload local woff2 -->
  <!-- 4. CSS externo: não-bloqueante -->
  <!-- 5. GTM: carregamento deferido -->
  <!-- 6. JSON-LD Schema.org -->
</head>
<body>
  <!-- GTM noscript -->
  <div class="nav-overlay"></div>   <!-- overlay do menu mobile -->
  <header> ... </header>
  <main> ... </main>
  <a class="whatsapp-float"> ... </a>  <!-- botão flutuante WhatsApp -->
  <footer> ... </footer>
  <script> ... </script>             <!-- JS no final do body -->
</body>
```

**Container padrão** — largura máxima consistente em todas as páginas:
```css
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 5%;
}
```

---

## 4. Header / Menu de Navegação

Idêntico em todas as páginas.

- **Fundo:** branco, `position: sticky; top: 0; z-index: 1001`
- **Sombra:** `box-shadow: 0 2px 10px rgba(0,0,0,0.1)`
- **Logo:** `img/LOGO-Retecsp-Azul.webp`, altura `48px`, com `width="655" height="185"` declarados
- **Links:** `font-weight: 600`, `font-size: 0.88rem`, cor `--dark-gray`, hover → `--primary-blue`
- **Botão CTA:** `background: --primary-blue`, branco, `border-radius: 6px`, `padding: 10px 18px`

**Mobile (≤ 900px):**
- Hambúrguer com 3 `<span>` → painel lateral deslizante (`right: -100% → 0`)
- Overlay escuro `rgba(0,0,0,0.42)` cobre o conteúdo por baixo
- Animação do ícone hamburger → ✕ via CSS transform
- Menu fecha ao clicar em qualquer link ou no overlay

```html
<header>
    <div class="container header-container">
        <a href="index3.html" class="logo-link" aria-label="Ir para a página inicial da RETEC">
            <img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">
        </a>
        <button class="menu-toggle" id="mobile-menu" aria-label="Abrir menu"
                aria-expanded="false" aria-controls="nav-menu">
            <span></span><span></span><span></span>
        </button>
        <nav id="nav-menu" aria-label="Menu principal">
            <ul>
                <li><a href="index3.html">Home</a></li>
                <li><a href="areas-atendimento.html">Áreas Atendidas</a></li>
                <li><a href="#faq">FAQ</a></li>
                <li><a href="#contato" class="btn-nav">Contato</a></li>
            </ul>
        </nav>
    </div>
</header>
```

---

## 5. Hero Section

### Páginas de cidade (`aluguel_cacamba_*.html`)
Layout de 2 colunas: texto à esquerda + card lateral à direita.

- **Fundo:** imagem `.webp` + overlay `linear-gradient(135deg, rgba(0,51,82,.88), rgba(0,38,61,.80))`
- **Badge:** pílula com `border-radius: 999px`, fundo `rgba(255,255,255,0.12)`, borda branca sutil
- **Botão primário (WhatsApp):** `background: #167a42`, SVG do WhatsApp inline, `min-height: 52px`
- **Botão secundário:** fundo semi-transparente, `border: 1px solid rgba(255,255,255,0.22)`
- **Card lateral (`hero-local-card`):** glassmorphism — `backdrop-filter: blur(6px)`, fundo `rgba(255,255,255,0.10)`
- **Ícones de check na lista:** SVG inline, fill dourado `#d6b030`

### Home principal (`index3.html`)
Layout de grid 2 colunas com hero de fundo total (`min-height: 90vh`).

---

## 6. Padrão Visual dos Cards

Aplicado uniformemente em todas as seções (features, steps, tipos, logística):

```css
border-radius: 12px;
border-top: 4px solid var(--primary-blue);  /* borda azul no topo */
padding: 25px–30px;
transition: transform 0.35s ease, box-shadow 0.35s ease;

/* Hover */
transform: translateY(-6px);
box-shadow: 0 12px–15px rgba(0,0,0,0.08);
```

---

## 7. Alternância de Fundo entre Seções

Sequência padrão do topo ao rodapé:

| Seção | Fundo |
|---|---|
| Hero | Azul escuro + imagem |
| Trust Strip / Como Funciona | `--light-gray` (#f8f9fa) |
| Tipos de Caçamba / Áreas | `--white` |
| Features / Logística | `--light-gray` |
| Guia de Descarte | `#eef2f7` |
| FAQ | `--white` |
| CTA Final | `--light-gray` |
| Rodapé | `--primary-blue` |

---

## 8. Ícones

### SVG Inline (páginas de cidade)
- Zero requisição de rede extra
- Fill azul escuro `#1e2b44` para ícones informativos
- Fill dourado `#d6b030` para ícones de confirmação/check
- `width` e `height` sempre declarados no `<svg>`

### Font Awesome 6 (home e páginas de listagem)
- Classes `fas`, `fab` via CDN jsDelivr v6.7.2
- Carregado de forma não-bloqueante

---

## 9. Botão WhatsApp Flutuante

Presente e idêntico em todas as páginas:

```css
.whatsapp-float {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 60px;
    height: 60px;
    background-color: #22ac65;
    border-radius: 50%;
    z-index: 9999;
}
```

- Aparece somente após `scroll > 400px` (adicionado via JS com classe `show`)
- Animação pulsante: `@keyframes pulse-ring` com `scale + opacity`
- Número: `https://wa.me/551137122416`

---

## 10. Rodapé

Idêntico em todas as páginas:

- `background: var(--primary-blue)`, texto branco
- Grid 3 colunas responsivo: `grid-template-columns: repeat(auto-fit, minmax(250px, 1fr))`

| Coluna | Conteúdo |
|---|---|
| 1 — Institucional | Nome RETEC, descrição, licenças CETESB/IBAMA, links Termos e Privacidade |
| 2 — Contato | Telefone WA `(11) 3712-2416`, e-mail, endereço (Av. das Nações Unidas, 99 — Osasco/SP) |
| 3 — Redes Sociais | Instagram e LinkedIn com SVG brands, fill dourado |

- **Footer bottom:** borda superior `rgba(255,255,255,0.1)`, copyright com ano gerado por JS, aviso de site oficial
- Ícones de contato no rodapé: SVG inline, fill `#d6b030`

---

## 11. SEO e Meta Tags

Padrão aplicado em todas as páginas:

```html
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="...">
    <link rel="canonical" href="https://www.retecsp.com.br/pagina.html">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="...">
    <meta property="og:description" content="...">
    <meta property="og:url" content="...">
    <meta property="og:image" content="https://www.retecsp.com.br/img/LOGO-Retecsp-Azul.webp">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="...">
    <meta name="twitter:description" content="...">
    <meta name="twitter:image" content="...">

    <!-- JSON-LD Schema.org -->
    <script type="application/ld+json">{ ... }</script>
</head>
```

**Schemas utilizados:** `LocalBusiness`, `Organization`, `WebSite`, `FAQPage`

---

## 12. Organização dos Arquivos CSS

| Arquivo | Propósito |
|---|---|
| `style.css` | Base global compartilhada entre todas as páginas |
| `style-main.css` | Específico de `index3.html` (landing principal) |
| `style-main.min.css` | Versão minificada de `style-main.css` — usada em produção |
| `style-areas.css` | Específico das páginas `aluguel_cacamba_*.html` |
| `style-index.css` | Específico de `index.html` (landing mais antiga) |

---

---

# Boas Práticas de Performance — PageSpeed / Core Web Vitals

---

## P1. Imagens com `width` e `height` declarados (evita CLS)

O browser reserva o espaço correto antes de baixar a imagem, eliminando saltos de layout (Cumulative Layout Shift).

```html
<!-- Logo -->
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<!-- Hero de cidade -->
<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>

<!-- Mapa de atendimento -->
<img src="img/retecsp-no-google-maps.webp" loading="lazy" width="2176" height="1563">
```

**Regra:** toda imagem deve ter `width` e `height`, independentemente do tamanho real exibido via CSS.

---

## P2. Imagens responsivas com `srcset` + `sizes`

O browser baixa apenas o tamanho adequado ao viewport do usuário, economizando banda em mobile.

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw"
     width="1366"
     height="768">
```

O `<link rel="preload">` do hero também usa `imagesrcset`/`imagesizes` para que o preload já considere o tamanho certo:

```html
<link rel="preload" as="image"
      href="img/Retec-Osasco-Small.webp"
      imagesrcset="img/Retec-Osasco-Small.webp 768w, img/Retec-Osasco-big.webp 1366w"
      imagesizes="100vw"
      fetchpriority="high">
```

---

## P3. `fetchpriority`, `loading` e `decoding` nas imagens

Três atributos de priorização que trabalham juntos:

```html
<!-- Imagem acima da dobra (hero): carregamento imediato e prioritário -->
<img src="img/hero.webp"
     fetchpriority="high"
     loading="eager"
     decoding="async">

<!-- Imagem abaixo da dobra: carregamento adiado -->
<img src="img/mapa.webp"
     loading="lazy"
     width="2176"
     height="1563">
```

| Atributo | Valor | Efeito |
|---|---|---|
| `fetchpriority="high"` | Hero e logo | Browser prioriza este download sobre outros |
| `loading="eager"` | Hero | Não aplica lazy-load mesmo se fora do viewport inicial |
| `loading="lazy"` | Imagens below-the-fold | Baixa apenas quando o usuário rola até próximo da imagem |
| `decoding="async"` | Hero | Decodificação em thread separada, não bloqueia o render principal |

---

## P4. Formato `.webp` em todas as imagens

Todas as imagens do projeto usam `.webp`, que oferece ~30% menos peso que JPEG/PNG com a mesma qualidade visual. Nenhuma imagem `.jpg` ou `.png` é servida para o usuário final.

---

## P5. `aspect-ratio` e `will-change` na imagem de fundo do hero

Evita recálculo de layout (reflow) e cria camada de composição na GPU para animações suaves:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU antecipadamente */
    aspect-ratio: 16/9;       /* reserva o espaço exato antes do carregamento */
    object-fit: cover;
}
```

---

## P6. Fontes auto-hospedadas com `preload` + `font-display: swap`

Sem dependência de CDN externo para fontes — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor. Isso elimina um round-trip de rede (impacta TTFB e LCP).

```html
<!-- HEAD: preload com crossorigin obrigatório para fontes -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* font-display: swap exibe texto com fonte fallback imediatamente,
   substitui pela Roboto quando ela termina de carregar → elimina FOIT */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

**FOIT** (Flash of Invisible Text): quando o texto fica invisível enquanto a fonte não carrega — penaliza LCP e UX.

---

## P7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets não-críticos (Font Awesome) não bloqueiam a renderização da página:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">

<!-- Fallback para usuários sem JavaScript -->
<noscript>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css">
</noscript>
```

O `onload="this.onload=null;this.rel='stylesheet'"` é o padrão recomendado para carregamento assíncrono de CSS sem JavaScript adicional.

---

## P8. CSS crítico inline no `<head>`

O CSS que estiliza o conteúdo visível sem rolar (header, hero, tipografia básica, variáveis) está embutido em `<style>` no `<head>` de cada página. Isso elimina uma requisição de rede bloqueante para renderizar o conteúdo inicial.

```html
<head>
    <style>
        /* CSS crítico: zero latência de rede para renderização inicial */
        :root { --primary-blue: #003352; ... }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Roboto', ...; }
        header { background: #fff; position: sticky; ... }
        .city-hero { background: var(--primary-blue); ... }
    </style>

    <!-- CSS complementar: carrega de forma assíncrona -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-areas.css">
</head>
```

---

## P9. `preload` da imagem hero como primeira tag do `<head>`

O preload é declarado logo após as metatags obrigatórias, antes do `<title>` e de qualquer outro recurso. Quanto mais cedo o browser descobrir o recurso LCP, mais rápido ele carrega.

```html
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Preload hero PRIMEIRO — crítico para LCP -->
    <link rel="preload" as="image" href="img/hero.webp" fetchpriority="high">

    <title>...</title>
    <!-- restante do <head> -->
```

---

## P10. Google Tag Manager com carregamento diferido

O GTM não carrega durante o `DOMContentLoaded` — só executa após a primeira interação real do usuário ou após 5 segundos. Isso libera CPU e rede durante o carregamento inicial.

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    (function(w,d,s,l,i){ /* ... inicializa GTM ... */ })(..., 'GTM-MRNG2HFG');
}

// Dispara na primeira interação real
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});

// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

## P11. Event listeners com `{ passive: true }`

Scroll e touch listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`. Isso permite que o browser processe scroll e animações sem esperar o JS terminar — resultado: scroll mais fluido, especialmente em mobile.

```js
window.addEventListener('scroll', function () {
    // mostra/esconde botão WhatsApp
}, { passive: true });

window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

## P12. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser não tem trabalho urgente, evitando competição com a renderização inicial (impacta INP):

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };

idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        var btn = item.querySelector('.faq-btn');
        btn.addEventListener('click', function () { ... });
    });
});
```

O fallback `setTimeout(cb, 1)` garante compatibilidade com browsers que não suportam `requestIdleCallback`.

---

## P13. `preconnect` para domínios externos

Abre a conexão TCP/TLS com os servidores externos antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<!-- CDN dos ícones Font Awesome -->
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

<!-- Google Fonts (quando usado) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```

**Nota:** `crossorigin` é obrigatório para fontes e recursos que usam CORS.

---

## P14. `min-height` em elementos com conteúdo dinâmico (anti-CLS)

Elementos cujo conteúdo é preenchido por JavaScript recebem altura mínima reservada para evitar saltos de layout enquanto o JS não executou:

```css
/* Impede que o h1 "salte" ao ser preenchido pelo JS */
h1 {
    min-height: 4.2rem;
}

/* Formulário: reserva espaço antes de renderizar */
.form-box {
    min-height: 260px;
}
```

---

## P15. `scroll-padding-top` para links âncora com header fixo

Compensa a altura do header sticky (`90px`) para que ao clicar em um link de âncora o título da seção não fique escondido atrás do header:

```css
html {
    scroll-padding-top: 90px;  /* deve ser igual ou maior que a altura do header */
}
```

---

## P16. Otimizações de renderização de texto

```css
body {
    text-rendering: optimizeLegibility; /* hinting e kerning aprimorados */
    font-synthesis: none;               /* impede o browser de criar itálico/bold sintéticos */
    overflow-x: hidden;                 /* elimina scrollbar horizontal indesejada */
}
```

---

## P17. CSS minificado em produção

`style-main.min.css` é a versão comprimida de `style-main.css`, referenciada diretamente no HTML de produção:

```html
<!-- Produção: versão minificada -->
<link rel="stylesheet" href="style-main.min.css">
```

Ao criar ou editar `style-main.css`, gerar a versão `.min.css` correspondente.

---

## P18. SVG inline para ícones (elimina requisições)

Nas páginas de cidade, todos os ícones são SVGs embutidos diretamente no HTML. Isso elimina qualquer requisição de rede adicional para carregar ícones e evita o FOUT (Flash of Unstyled Text) de fontes de ícone:

```html
<!-- Ícone WhatsApp — nenhuma requisição de rede necessária -->
<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 512 512" fill="white">
    <path d="M380.9 97.1..."/>
</svg>
```

Padrão de dimensões dos SVGs:
- Ícones em botões: `width="20" height="20"`
- Ícones em cards: `width="30" height="30"`
- Ícones em tipos de caçamba: `width="50" height="50"`

---

## P19. `aria-hidden="true"` em imagens decorativas

Imagens de fundo e elementos visuais sem valor semântico são marcados para screen readers ignorarem, sem impacto em acessibilidade real:

```html
<img src="img/Retec-Osasco-big.webp"
     class="hero-bg-img"
     aria-hidden="true"
     alt=""
     loading="eager"
     fetchpriority="high">
```

---

## P20. `-webkit-tap-highlight-color: transparent` (UX mobile)

Remove o flash azul nativo do iOS/Android ao tocar em links e botões, melhorando a percepção de resposta nos dispositivos touch:

```css
.btn-wa-city,
.btn-tipo,
.areas-main-cta {
    -webkit-tap-highlight-color: transparent;
}
```

---

## Resumo — Core Web Vitals × Boas Práticas

| Métrica | O que afeta | Boas Práticas Aplicadas |
|---|---|---|
| **LCP** — Largest Contentful Paint | Tempo para o maior elemento visível aparecer | P1 `width/height`, P2 `srcset`, P3 `fetchpriority`, P6 Fontes locais + `swap`, P8 CSS crítico inline, P9 `preload` hero primeiro, P10 GTM deferido |
| **CLS** — Cumulative Layout Shift | Saltos inesperados de layout | P1 `width/height` nas imagens, P5 `aspect-ratio`, P14 `min-height` em conteúdo dinâmico |
| **INP** — Interaction to Next Paint | Resposta a cliques e toques | P11 `passive` listeners, P12 `requestIdleCallback`, P18 SVG inline (sem requisições), P20 tap-highlight |
| **FCP** — First Contentful Paint | Primeiro pixel de conteúdo renderizado | P7 CSS não-bloqueante, P8 CSS inline, P13 `preconnect`, P6 Fontes locais |
| **TTFB** — Time to First Byte | Tempo até o servidor responder | P6 Auto-hospedagem de fontes (elimina round-trip Google Fonts), P13 `preconnect` |
