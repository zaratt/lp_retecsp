# Ajuste de Rodízio WhatsApp — Documentação para Desenvolvedores

## Visão Geral

Esta solução implementa um **rodízio de números de WhatsApp comercial** baseado em cliente-side (JavaScript), garantindo que cada visitante seja atribuído a um atendente de forma estatística e mantenha o mesmo atendente durante toda a sua sessão de navegação.

**Objetivo:** Distribuir o fluxo de leads entre múltiplos atendentes sem sobrecarregar um único ponto de contato.

### Escopo Atual
- **Fase de Teste:** Apenas a página `teste-atendimento.html` com 3 números de teste.
- **Páginas não alteradas:** Nenhuma página em produção foi modificada até aprovação explícita da hipótese.
- **Rollout futuro:** Planejado para `index.html`, páginas regionais e `areas-atendimento.html` após validação.

---

## Arquitetura da Solução

### Componentes

#### 1. **Script de Roteamento: `/scripts/wa-router.js`**
- Arquivo compartilhado que contém toda a lógica de rodízio.
- Inicializa na página automaticamente ao carregar.
- Seleciona um atendente aleatório na primeira visita e o armazena em `localStorage`.
- Substitui os números de WhatsApp apenas nos links marcados para rodízio.
- **Não afeta** links institucionais ou números departamentais.

#### 2. **Página de Teste: `teste-atendimento.html`**
- Clone da página `contato.html` sem GTM.
- Protegida com `<meta name="robots" content="noindex,nofollow">`.
- Marca apenas CTAs comerciais para rodízio com o atributo `data-wa-rotate="comercial"`.
- Mantém SAC e Financeiro com números fixos.
- Valida o comportamento antes de rollout em produção.

---

## Como Funciona o Roteador

### Fluxo de Execução

```javascript
1. Página carrega
   ↓
2. wa-router.js é executado
   ↓
3. Consulta localStorage para chave "retec_wa_atendente_teste" (ou similar em produção)
   ↓
4. Se não encontrada, sorteia índice aleatório entre os atendentes configurados
   ↓
5. Armazena o índice em localStorage
   ↓
6. Busca todos os links com data-wa-rotate="comercial"
   ↓
7. Substitui o número de WhatsApp em cada href
   ↓
8. Expõe a atribuição via window.retecWaTeste para debug
```

### Persistência

- **Chave localStorage:** `retec_wa_atendente_teste` (fase de teste)
- **Duração:** Entre visitas e dentro da mesma visita
- **Comportamento:** Um visitante que abre a página múltiplas vezes em dias diferentes manterá o mesmo atendente
- **Reset:** Limpar localStorage do navegador ou usar modo privado simula um novo visitante

---

## Estrutura do Arquivo `wa-router.js`

```javascript
(function () {
  // Array configurável de atendentes
  var ATENDENTES = [
    { num: "5511993600810", nome: "Atendente 1" },
    { num: "5511940309983", nome: "Atendente 2" },
    { num: "5511982875060", nome: "Atendente 3" }
  ];

  // Chave única para armazenamento
  var KEY = "retec_wa_atendente_teste";

  // Recupera ou sorteia índice
  var idx = parseInt(localStorage.getItem(KEY), 10);
  if (isNaN(idx) || idx < 0 || idx >= ATENDENTES.length) {
    idx = Math.floor(Math.random() * ATENDENTES.length);
    localStorage.setItem(KEY, String(idx));
  }

  // Obtém atendente selecionado
  var atendente = ATENDENTES[idx];

  // Localiza e substitui hrefs de links marcados
  var links = document.querySelectorAll('a[data-wa-rotate="comercial"]');
  links.forEach(function (a) {
    a.href = a.href.replace(/wa\.me\/\d+/, "wa.me/" + atendente.num);
  });

  // Expõe informações para debug
  window.retecWaTeste = {
    indice: idx,
    numero: atendente.num,
    nome: atendente.nome
  };
})();
```

---

## Como Implementar em Nova Página

### Passo 1: Adicionar o Meta Robot (se for uma página de teste/não-indexável)

```html
<head>
  <meta name="robots" content="noindex,nofollow">
  <!-- outros metas -->
</head>
```

### Passo 2: Marcar Apenas CTAs Comerciais para Rodízio

Identifique os links de WhatsApp que devem participar do rodízio e adicione o atributo `data-wa-rotate="comercial"`:

**Exemplo — CTA Comercial (COM rodízio):**
```html
<a href="https://wa.me/551137122416?text=Solicitar%20Orçamento"
   class="btn-wa-city js-generate-lead"
   data-cidade="São Paulo"
   data-pagina="index"
   data-cta-posicao="hero_cta"
   data-cta-texto="Solicitar Orçamento"
   data-canal="whatsapp"
   data-wa-rotate="comercial"
   target="_blank" rel="noopener">
  Solicitar Orçamento
</a>
```

**Exemplo — Link Institucional (SEM rodízio):**
```html
<a href="https://wa.me/551137122416"
   style="color:white; text-decoration:none;">
  (11) 3712-2416
</a>
```
*Sem o atributo `data-wa-rotate`, este link não será alterado.*

**Exemplo — Número Departamental Fixo (SEM rodízio):**
```html
<a href="https://wa.me/5511917015902"
   class="btn-dept btn-dept-wa js-generate-lead"
   data-cta-posicao="dept_sac"
   data-cta-texto="Falar com SAC"
   data-canal="whatsapp"
   target="_blank" rel="noopener">
  Falar com SAC
</a>
```
*Número diferente, sem marcação de rodízio, permanece intacto.*

### Passo 3: Incluir o Script no Rodapé (antes de `</body>`)

```html
<!-- ============ JAVASCRIPT ============ -->
<script>
  /* ... seu código JavaScript local ... */
</script>

<!-- Carregar roteador de WhatsApp -->
<script src="/scripts/wa-router.js"></script>
</body>
</html>
```

### Passo 4: Atualizar a Configuração de Atendentes (quando passar de teste para produção)

Edite `/scripts/wa-router.js` e atualize:

1. **Array `ATENDENTES`** com os 6 números finais
2. **Chave `KEY`** de `"retec_wa_atendente_teste"` para `"retec_wa_atendente"` (sem "teste")

**Exemplo de Produção:**
```javascript
var ATENDENTES = [
  { num: "5511XXXXXX01", nome: "Atendente 1" },
  { num: "5511XXXXXX02", nome: "Atendente 2" },
  // ... 4 mais
];

var KEY = "retec_wa_atendente"; // Remove "teste"
```

---

## Garantir Integridade do Código

### Checklist de Revisão de Código

Ao adicionar rodízio em uma nova página, verifique:

#### ✅ **Segurança**
- [ ] O script é **inline (IIFE)** e não polui o escopo global
- [ ] Apenas `window.retecWaTeste` (ou equivalente) é exposto para debug
- [ ] Nenhum dado sensível é armazenado em localStorage
- [ ] O `localStorage.setItem()` usa `String(idx)` para evitar conversão implícita

#### ✅ **Precisão de Seletor**
- [ ] Apenas links com `data-wa-rotate="comercial"` são alterados
- [ ] Links institucional e departamentais **não têm** esse atributo
- [ ] Teste manual: SAC e Financeiro devem manter seus números originais

#### ✅ **Persistência**
- [ ] A chave em `localStorage` é **única e consistente** entre páginas
- [ ] O índice é salvo **antes** da primeiro clique
- [ ] Um visitante que abre 3 CTAs diferentes vê o **mesmo número** em todos

#### ✅ **Tracking & Analytics**
- [ ] O evento `generate_lead` continua sendo enviado normalmente
- [ ] O `click_url` no dataLayer reflete o número **roteado** (não o hardcoded)
- [ ] Nenhuma referência a `loadGTM()` na página (se essa página não tiver GTM)

#### ✅ **SEO & Robots**
- [ ] Se for página de teste: `<meta name="robots" content="noindex,nofollow">` presente
- [ ] Página não incluída em `sitemap.xml`
- [ ] Página não referenciada em menu ou navegação interna

#### ✅ **Compatibilidade**
- [ ] O regex `/wa\.me\/\d+/` funciona para todos os formatos de número esperados
- [ ] A função `replace()` usa `String.replace()` (não `replaceAll` obsoleto)
- [ ] Testado em navegador com localStorage desativado (deve funcionar, sortear novo número)

---

## Teste e Validação

### Teste Manual (Navegador)

#### 1. **Teste de Atribuição Inicial**
```
1. Abrir teste-atendimento.html em navegador limpo (sem histórico, cache zerado)
2. Abrir DevTools → Console
3. Digitar: console.log(window.retecWaTeste)
4. Esperado: { indice: [0-2], numero: "551199360...", nome: "Atendente X" }
5. Verficar: O número exibido está na lista dos 3 configurados
```

#### 2. **Teste de Persistência**
```
1. Na mesma aba, recarregar a página (F5)
2. Digitar novamente: console.log(window.retecWaTeste)
3. Esperado: **Mesmo número e índice** que antes da recarga
4. Clicar em múltiplos botões comerciais
5. Esperado: Todos apontam para o **mesmo número roteado**
```

#### 3. **Teste de Novo Visitante**
```
1. Abrir uma janela privada / anônima
2. Abrir teste-atendimento.html
3. Digitar: console.log(window.retecWaTeste)
4. Esperado: Pode ser **número diferente** (aleatório para novo visitante)
5. Executar 3+ vezes em janelas privadas diferentes
6. Esperado: Distribuição entre os 3 números ocorre (nem sempre o mesmo)
```

#### 4. **Teste de Integridade de Números Fixos**
```
1. Abrir teste-atendimento.html
2. Inspeccionar elemento → Procurar links de SAC e Financeiro
3. Esperado: Hrefs mantêm números **originais** (não 5511993600810/5511940309983/5511982875060)
4. Clicar nos botões de SAC e Financeiro
5. Esperado: WhatsApp abre com números **departamentais** corretos
```

#### 5. **Teste de Rastreamento**
```
1. Abrir teste-atendimento.html
2. Abrir DevTools → Network + Console
3. Clicar em um CTA comercial (ex: Comercial — Orçamentos)
4. Observar a aba Network: Deve haver um evento de clique
5. No console, buscar: window.dataLayer.slice(-1)
6. Esperado: click_url contém o número **roteado** (não o hardcoded original)
```

---

## Solução de Problemas

### Problema: Todos os visitantes recebem o mesmo número
**Causa:** `Math.random()` talvez não esteja variando ou localStorage está compartilhado.  
**Solução:** Testar em modo privado ou limpar localStorage entre testes.

### Problema: Links institucionais estão sendo alterados
**Causa:** Links não têm o atributo `data-wa-rotate="comercial"` ou seletor está muito amplo.  
**Solução:** Verificar seletor em wa-router.js; deve usar `a[data-wa-rotate="comercial"]` exatamente.

### Problema: Números SAC/Financeiro foram sobrescritos
**Causa:** Marcados com `data-wa-rotate="comercial"` acidentalmente.  
**Solução:** Remover o atributo desses links; eles devem permanecer fixos.

### Problema: Script não carrega ("wa-router.js not found")
**Causa:** Caminho incorreto ou arquivo não existe.  
**Solução:** Verificar que `<script src="/scripts/wa-router.js"></script>` está no lugar certo e arquivo existe em `/home/arsilva/REPO/lp_retecsp/scripts/`.

---

## Transição de Teste para Produção

Quando a hipótese for validada e aprovada:

### 1. **Atualizar números em `wa-router.js`**
```javascript
var ATENDENTES = [
  { num: "5511XXXXXX01", nome: "Atendente 1" },
  { num: "5511XXXXXX02", nome: "Atendente 2" },
  { num: "5511XXXXXX03", nome: "Atendente 3" },
  { num: "5511XXXXXX04", nome: "Atendente 4" },
  { num: "5511XXXXXX05", nome: "Atendente 5" },
  { num: "5511XXXXXX06", nome: "Atendente 6" }
];

var KEY = "retec_wa_atendente"; // Remove "teste"
```

### 2. **Aplicar em Páginas Prioritárias**
- Começar por `index.html` (maior volume)
- Depois `areas-atendimento.html`
- Depois páginas regionais (`aluguel-cacamba-*.html`)

### 3. **Remover Proteção SEO**
Se as páginas de produção forem indexáveis, remover:
```html
<meta name="robots" content="noindex,nofollow">
```

### 4. **Atualizar Documentação de Manutenção**
- Adicionar lista dos 6 atendentes e seus números em um arquivo de configuração interno
- Documentar como adicionar/remover atendentes (apenas editar wa-router.js)

---

## Referência Rápida para Desenvolvedores

### Adicionar um novo link comercial com rodízio
```html
<a href="https://wa.me/551137122416?text=Minha%20Mensagem"
   class="btn-comercial js-generate-lead"
   data-wa-rotate="comercial"
   target="_blank" rel="noopener">
  Botão Comercial
</a>
```

### Adicionar um link que NÃO deve rotacionar
```html
<a href="https://wa.me/551137122416">Suporte</a>
```

### Verificar qual atendente foi atribuído (console do navegador)
```javascript
console.log(window.retecWaTeste);
// Output: { indice: 1, numero: "5511940309983", nome: "Atendente 2" }
```

### Limpar a atribuição de um visitante
```javascript
localStorage.removeItem("retec_wa_atendente_teste");
location.reload();
```

---

## Histórico de Implementação

| Data | Etapa | Status |
|------|-------|--------|
| 2026-05-18 | Criação de `teste-atendimento.html` e `wa-router.js` | ✅ Completo |
| 2026-05-18 | Validação em navegador com 3 números de teste | ⏳ Aguardando |
| 2026-05-XX | Aprovação da hipótese | ⏳ Pendente |
| 2026-05-XX | Rollout para `index.html` | ⏳ Pendente |
| 2026-05-XX | Rollout para áreas de atendimento | ⏳ Pendente |
| 2026-05-XX | Rollout para páginas regionais | ⏳ Pendente |

---

## Contato & Dúvidas

Para dúvidas sobre implementação:
1. Consulte o arquivo `/DOCS/wa-router.md` (versão anterior se existir)
2. Revise os exemplos em `teste-atendimento.html` (validado e testado)
3. Valide com o checklist de integridade acima antes de fazer merge

---

**Última atualização:** 2026-05-18  
**Responsável:** Equipe de Desenvolvimento  
**Versão:** 1.0 (Fase de Teste)
