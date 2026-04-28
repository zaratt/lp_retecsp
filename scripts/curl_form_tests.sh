#!/usr/bin/env bash
set -u

BASE="https://www.retecsp.com.br"
HTTP_BASE="http://www.retecsp.com.br"
UA="QA-curl-retecsp/1.0"
KEEP_ARTIFACTS=0
MAIL_TESTS=0
WORKDIR=""

PASS_COUNT=0
FAIL_COUNT=0

usage() {
  cat <<'EOF'
Uso:
  ./scripts/curl_form_tests.sh [opcoes]

Opcoes:
  --base URL          Dominio base HTTPS. Ex: https://www.retecsp.com.br
  --mail-tests        Executa testes que disparam envio real de e-mail
  --keep-artifacts    Mantem arquivos temporarios de resposta
  --help              Mostra esta ajuda

Observacoes:
- Sem --mail-tests, o script roda somente testes seguros (nao gera e-mails reais).
- Com --mail-tests, ha um teste positivo e o teste de rate limit por sessao.
EOF
}

log() {
  printf "\n[%s] %s\n" "INFO" "$1"
}

pass() {
  PASS_COUNT=$((PASS_COUNT + 1))
  printf "[%s] %s\n" "PASS" "$1"
}

fail() {
  FAIL_COUNT=$((FAIL_COUNT + 1))
  printf "[%s] %s\n" "FAIL" "$1"
}

has_cmd() {
  command -v "$1" >/dev/null 2>&1
}

extract_json_token() {
  local file="$1"
  local token=""

  if has_cmd jq; then
    token=$(jq -r '.token // empty' "$file" 2>/dev/null || true)
  fi

  if [[ -z "$token" ]]; then
    token=$(sed -n 's/.*"token"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$file" | head -n1)
  fi

  printf "%s" "$token"
}

http_code() {
  local method="$1"
  local url="$2"
  local body_file="$3"
  shift 3
  curl -sS -A "$UA" -o "$body_file" -w "%{http_code}" -X "$method" "$url" "$@"
}

assert_code_in() {
  local got="$1"
  local label="$2"
  shift 2
  local ok=0
  local expected
  for expected in "$@"; do
    if [[ "$got" == "$expected" ]]; then
      ok=1
      break
    fi
  done

  if [[ $ok -eq 1 ]]; then
    pass "$label (HTTP $got)"
  else
    fail "$label (HTTP $got, esperado: $*)"
  fi
}

prepare_workdir() {
  WORKDIR="/tmp/retec-curl-tests-$$"
  mkdir -p "$WORKDIR"
}

cleanup() {
  if [[ "$KEEP_ARTIFACTS" -eq 0 && -n "$WORKDIR" && -d "$WORKDIR" ]]; then
    rm -rf "$WORKDIR"
  fi
}

get_csrf_token() {
  local cookie_file="$1"
  local out_json="$2"
  local code
  code=$(curl -sS -A "$UA" -c "$cookie_file" -o "$out_json" -w "%{http_code}" "$BASE/csrf_token.php")
  if [[ "$code" != "200" ]]; then
    printf ""
    return 1
  fi
  extract_json_token "$out_json"
}

post_form() {
  local cookie_file="$1"
  local csrf_token="$2"
  local departamento="$3"
  local nome="$4"
  local email="$5"
  local telefone="$6"
  local mensagem="$7"
  local consent_value="$8"
  local out_body="$9"

  local args=(
    -sS
    -A "$UA"
    -b "$cookie_file"
    -H "Accept: application/json"
    -o "$out_body"
    -w "%{http_code}"
    -X POST "$BASE/enviar.php"
    --data-urlencode "departamento=$departamento"
    --data-urlencode "nome=$nome"
    --data-urlencode "email=$email"
    --data-urlencode "telefone=$telefone"
    --data-urlencode "mensagem=$mensagem"
  )

  if [[ -n "$csrf_token" ]]; then
    args+=(--data-urlencode "csrf_token=$csrf_token")
  fi

  if [[ -n "$consent_value" ]]; then
    args+=(--data-urlencode "consentimento=$consent_value")
  fi

  curl "${args[@]}"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base)
      BASE="$2"
      HTTP_BASE="http://${2#https://}"
      shift 2
      ;;
    --mail-tests)
      MAIL_TESTS=1
      shift
      ;;
    --keep-artifacts)
      KEEP_ARTIFACTS=1
      shift
      ;;
    --help)
      usage
      exit 0
      ;;
    *)
      echo "Opcao invalida: $1"
      usage
      exit 1
      ;;
  esac
done

if ! has_cmd curl; then
  echo "Erro: curl nao encontrado no sistema."
  exit 1
fi

prepare_workdir
trap cleanup EXIT

log "Teste 1: pagina de contato via HTTPS"
code=$(http_code GET "$BASE/contato" "$WORKDIR/t1_body.txt")
assert_code_in "$code" "Contato em HTTPS" 200

log "Teste 2: redirecionamento HTTP para HTTPS"
code=$(http_code GET "$HTTP_BASE/contato" "$WORKDIR/t2_body.txt")
assert_code_in "$code" "Redirect HTTP->HTTPS" 301 302 307 308

log "Teste 3: arquivos sensiveis bloqueados"
code=$(http_code GET "$BASE/config.php" "$WORKDIR/t3_config.txt")
assert_code_in "$code" "Bloqueio config.php" 403 404

code=$(http_code GET "$BASE/config.credentials.php" "$WORKDIR/t3_creds.txt")
assert_code_in "$code" "Bloqueio config.credentials.php" 403 404

log "Teste 4: endpoint csrf em HTTP deve bloquear ou redirecionar"
code=$(http_code GET "$HTTP_BASE/csrf_token.php" "$WORKDIR/t4_body.txt")
assert_code_in "$code" "csrf_token.php em HTTP" 301 302 307 308 403

log "Teste 5: caso negativo sem consentimento (nao envia e-mail)"
csrf=$(get_csrf_token "$WORKDIR/cookies_no_consent.txt" "$WORKDIR/csrf_no_consent.json" || true)
if [[ -z "$csrf" ]]; then
  fail "Nao foi possivel obter CSRF para teste sem consentimento"
else
  code=$(post_form "$WORKDIR/cookies_no_consent.txt" "$csrf" "Financeiro" "QA Sem Consent" "qa.semconsent.$RANDOM@example.com" "11999990001" "Teste sem consentimento" "" "$WORKDIR/t5_body.txt")
  assert_code_in "$code" "POST sem consentimento" 400
fi

log "Teste 6: caso negativo sem CSRF (nao envia e-mail)"
code=$(post_form "$WORKDIR/cookies_missing_csrf.txt" "" "Comercial" "QA Sem CSRF" "qa.semcsrf.$RANDOM@example.com" "11999990002" "Teste sem csrf" "on" "$WORKDIR/t6_body.txt")
assert_code_in "$code" "POST sem CSRF" 403

log "Teste 7: caso negativo com CSRF invalido (nao envia e-mail)"
csrf_dummy_file="$WORKDIR/csrf_dummy.json"
code=$(curl -sS -A "$UA" -c "$WORKDIR/cookies_invalid_csrf.txt" -o "$csrf_dummy_file" -w "%{http_code}" "$BASE/csrf_token.php")
if [[ "$code" != "200" ]]; then
  fail "Nao foi possivel preparar sessao para CSRF invalido"
else
  code=$(post_form "$WORKDIR/cookies_invalid_csrf.txt" "token_invalido_123" "SAC" "QA CSRF Invalido" "qa.csrfinvalido.$RANDOM@example.com" "11999990003" "Teste csrf invalido" "on" "$WORKDIR/t7_body.txt")
  assert_code_in "$code" "POST com CSRF invalido" 403
fi

log "Teste 8: rate limit por IP hash com requests invalidas (sem envio real)"
last_code=""
for i in $(seq 1 12); do
  cfile="$WORKDIR/cookies_ip_$i.txt"
  jfile="$WORKDIR/csrf_ip_$i.json"
  token=$(get_csrf_token "$cfile" "$jfile" || true)
  if [[ -z "$token" ]]; then
    fail "Nao foi possivel obter token para iteracao IP $i"
    continue
  fi
  last_code=$(post_form "$cfile" "$token" "Comercial" "QA Rate IP $i" "qa.rateip.$i.$RANDOM@example.com" "1199999$i" "Teste rate ip $i" "" "$WORKDIR/t8_body_$i.txt")
done

if [[ "$last_code" == "429" ]]; then
  pass "Rate limit por IP hash ativado (ultima resposta 429)"
else
  fail "Rate limit por IP hash nao atingido (ultima resposta $last_code)"
fi

if [[ "$MAIL_TESTS" -eq 1 ]]; then
  log "Teste 9: caso positivo (gera 1 e-mail real)"
  csrf=$(get_csrf_token "$WORKDIR/cookies_positive.txt" "$WORKDIR/csrf_positive.json" || true)
  if [[ -z "$csrf" ]]; then
    fail "Nao foi possivel obter CSRF para caso positivo"
  else
    code=$(post_form "$WORKDIR/cookies_positive.txt" "$csrf" "SAC" "QA Positivo" "qa.positivo.$RANDOM@example.com" "11999990004" "Teste positivo via curl" "on" "$WORKDIR/t9_body.txt")
    if [[ "$code" == "200" ]] && grep -q '"success"[[:space:]]*:[[:space:]]*true' "$WORKDIR/t9_body.txt"; then
      pass "POST valido com sucesso"
    else
      fail "POST valido falhou (HTTP $code)"
    fi
  fi

  log "Teste 10: rate limit por sessao (gera ate 3 e-mails reais)"
  rm -f "$WORKDIR/cookies_session_rate.txt"
  session_blocked=0
  for i in 1 2 3 4; do
    tok=$(get_csrf_token "$WORKDIR/cookies_session_rate.txt" "$WORKDIR/csrf_session_$i.json" || true)
    if [[ -z "$tok" ]]; then
      fail "Nao foi possivel obter token da sessao no ciclo $i"
      continue
    fi
    code=$(post_form "$WORKDIR/cookies_session_rate.txt" "$tok" "SAC" "QA Rate Sessao $i" "qa.ratesessao.$i.$RANDOM@example.com" "119999901$i" "Teste rate sessao $i" "on" "$WORKDIR/t10_body_$i.txt")
    if [[ "$i" -lt 4 ]]; then
      if [[ "$code" == "200" ]]; then
        pass "Sessao envio $i aceito"
      else
        fail "Sessao envio $i falhou (HTTP $code)"
      fi
    else
      if [[ "$code" == "429" ]]; then
        session_blocked=1
        pass "Sessao bloqueada no 4o envio (429)"
      else
        fail "Sessao nao bloqueou no 4o envio (HTTP $code)"
      fi
    fi
  done

  if [[ "$session_blocked" -ne 1 ]]; then
    fail "Rate limit por sessao nao comprovado"
  fi
else
  log "MAIL_TESTS desativado: pulando teste positivo e rate limit por sessao"
fi

printf "\nResumo final: PASS=%d | FAIL=%d\n" "$PASS_COUNT" "$FAIL_COUNT"

if [[ "$KEEP_ARTIFACTS" -eq 1 ]]; then
  echo "Artefatos salvos em: $WORKDIR"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  exit 1
fi

exit 0
