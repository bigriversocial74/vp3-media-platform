#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
port="${VP3_HTTP_TEST_PORT:-18777}"
log="$(mktemp)"
php -S "127.0.0.1:${port}" >"$log" 2>&1 &
pid=$!
cleanup() { kill "$pid" 2>/dev/null || true; rm -f "$log"; }
trap cleanup EXIT
sleep 1
pages=(index.php products.php product.php hosting.php pricing.php features.php demo.php about.php contact.php support.php login.php signup.php forgot-password.php install.php)
for page in "${pages[@]}"; do
  body="$(mktemp)"
  code="$(curl --fail-with-body -sS -o "$body" -w '%{http_code}' "http://127.0.0.1:${port}/${page}")"
  bytes="$(wc -c < "$body")"
  rm -f "$body"
  [[ "$code" == "200" ]] || { echo "FAIL: $page returned $code"; exit 1; }
  (( bytes > 500 )) || { echo "FAIL: $page returned an unexpectedly small body"; exit 1; }
done
headers="$(curl -sSI "http://127.0.0.1:${port}/index.php")"
grep -qi '^X-Content-Type-Options: nosniff' <<<"$headers"
grep -qi '^Content-Security-Policy:' <<<"$headers"
echo "HTTP smoke tests passed."
