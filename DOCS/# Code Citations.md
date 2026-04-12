# Code Citations

## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```


## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```


## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```


## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```


## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```


## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```


## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```


## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```


## License: unknown
https://github.com/1010bots/T-3000/blob/467a4800d9355343223f391c918cbd846c121ec3/resources/views/browser-test.blade.php

```
Tenho todos os dados necessários. Aqui está o levantamento completo das boas práticas de performance identificadas no projeto:

---

## Boas práticas de performance para PageSpeed identificadas

### 1. Imagens — Atributos completos (evita CLS)

Toda imagem acima da dobra tem `width` e `height` declarados para o browser reservar o espaço antes de carregar, evitando **Layout Shift** (métrica CLS):

```html
<img src="img/LOGO-Retecsp-Azul.webp" alt="Logo RETEC" width="655" height="185">

<img src="img/Retec-Osasco-big.webp"
     width="1366"
     height="768"
     ...>
```

---

### 2. Imagens — `srcset` + `sizes` (imagem responsiva)

Evita enviar imagem grande para mobile — o browser baixa apenas o tamanho adequado ao viewport:

```html
<img src="img/Retec-Osasco-big.webp"
     srcset="img/Retec-Osasco-Small.webp 768w,
             img/Retec-Osasco-big.webp 1366w"
     sizes="100vw">
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

### 3. Imagens — `fetchpriority`, `loading`, `decoding`

Três atributos combinados para priorizar o que importa e liberar o browser para outras tarefas:

```html
<!-- Hero: crítico → eager + alta prioridade + decodifica em paralelo -->
fetchpriority="high"
loading="eager"
decoding="async"

<!-- Logo: prioridade alta pois está no header -->
<img ... fetchpriority="high">

<!-- Mapa de áreas: abaixo da dobra → lazy -->
<img ... loading="lazy" width="2176" height="1563">
```

---

### 4. Imagens — Formato exclusivamente `.webp`

Todas as imagens do projeto (hero, logo, mapas, backgrounds) estão em `.webp`, que oferece ~30% menos peso que JPEG/PNG com mesma qualidade visual.

---

### 5. Imagem hero com `aspect-ratio` e `will-change` (evita reflow)

Na `index.html` (landing de captura), a imagem de fundo tem propriedades que previnem recálculo de layout:

```css
.hero-bg {
    will-change: transform;   /* avisa o browser para criar camada GPU */
    aspect-ratio: 16/9;       /* reserva espaço antes do carregamento */
    object-fit: cover;
}
```

---

### 6. Fontes locais com `preload` + `font-display: swap`

**Sem dependência de CDN externo para fontes** — os arquivos `.woff2` ficam em `/tiff/` no próprio servidor:

```html
<!-- Preload declara alta prioridade ao browser -->
<link rel="preload" href="tiff/Roboto-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tiff/Roboto-Bold.woff2" as="font" type="font/woff2" crossorigin>
```

```css
/* swap: exibe texto com fonte fallback imediatamente, substitui quando Roboto carrega */
@font-face {
    font-family: 'Roboto';
    font-display: swap;
    src: url('tiff/Roboto-Regular.woff2') format('woff2');
}
```

Isso elimina o **FOIT** (Flash of Invisible Text), que penaliza o LCP.

---

### 7. CSS não-bloqueante com `preload` + `onload` + `noscript`

Stylesheets externos (Font Awesome) não bloqueiam a renderização — carregam de forma assíncrona:

```html
<link rel="preload"
      href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/solid.min.css"
      as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<!-- Fallback para usuários sem JS -->
<noscript>
  <link rel="stylesheet" href="...solid.min.css">
</noscript>
```

---

### 8. CSS crítico inline no `<head>`

O CSS acima da dobra (header, hero, tipografia básica) está embutido diretamente no `<style>` de cada página, eliminando uma requisição de rede para renderizar o conteúdo visível inicial:

```html
<head>
  <style>
    /* CSS crítico: header, hero, fontes, cores → zero latência de rede */
    :root { --primary-blue: #003352; ... }
    header { background: #fff; position: sticky; ... }
    .city-hero { ... }
  </style>
  <!-- CSS restante carrega de forma assíncrona -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="style-areas.css">
</head>
```

---

### 9. Google Tag Manager com carregamento diferido

O GTM **não carrega no `DOMContentLoaded`** — só executa quando o usuário interage ou após 5 segundos. Isso libera o tempo de carregamento inicial:

```js
function loadGTM() {
    if (window._gtmLoaded) return;  // garante execução única
    window._gtmLoaded = true;
    // ... inicializa GTM ...
}
// Dispara somente na primeira interação real do usuário
['scroll', 'click', 'keydown', 'touchstart'].forEach(function(evt) {
    window.addEventListener(evt, loadGTM, { once: true, passive: true });
});
// Fallback: 5 segundos sem interação
setTimeout(loadGTM, 5000);
```

---

### 10. Event listeners com `{ passive: true }`

Scroll listeners marcados como `passive` informam ao browser que o handler não vai chamar `preventDefault()`, permitindo scroll suave sem travamentos no thread principal:

```js
window.addEventListener('scroll', function () { ... }, { passive: true });
window.addEventListener(evt, loadGTM, { once: true, passive: true });
```

---

### 11. JS não-crítico com `requestIdleCallback`

O accordion do FAQ é inicializado somente quando o browser está ocioso, sem disputar CPU com a renderização inicial:

```js
var idle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1); };
idle(function () {
    document.querySelectorAll('.faq-item').forEach(function (item) {
        // inicializa accordion apenas quando não há trabalho urgente
    });
});
```

---

### 12. `preconnect` para domínios externos

Abre a conexão TCP/TLS com o CDN antes de precisar dos recursos, reduzindo a latência da primeira requisição:

```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
```

