#!/usr/bin/env bash
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Два изолированных PHP-FPM + nginx, общая MariaDB только в disposable CI.
# EN: Two real independent FPM containers, one HTTP reverse proxy, shared DB.
set -euo pipefail
if [[ "$MODXMCP_DISPOSABLE_CI" != "1" ]] \
   || [[ "$RUNNER_TEMP" != /home/runner/* ]] \
   || [[ "$MODX_CONFIG_CORE" != /home/runner/* ]] \
   || [[ ! -f "$MODX_CONFIG_CORE" ]]; then
  echo "Refusing FPM containers outside disposable GitHub CI" >&2
  exit 3
fi
readonly REPO="$(pwd)"
readonly WORK="$RUNNER_TEMP/mcp-fpm-ci"
readonly SHARED="$WORK/shared"
mkdir -p "$SHARED" "$WORK/build"
# FPM runs as www-data, which must be able to create test-only barriers.
chmod 0777 "$SHARED"
printf 'disposable-github-only\n' > "$SHARED/CI_MARKER"
cat > "$WORK/build/Dockerfile" <<'DOCKERFILE'
FROM php:8.2-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libzip-dev \
 && docker-php-ext-install -j2 pdo_mysql mysqli mbstring zip \
 && rm -rf /var/lib/apt/lists/*
RUN printf '\nclear_env = no\ncatch_workers_output = yes\ndecorate_workers_output = no\n' >> /usr/local/etc/php-fpm.d/www.conf
RUN printf 'log_errors=On\ndisplay_errors=Off\nerror_reporting=E_ALL\n' > /usr/local/etc/php/conf.d/ci-security-errors.ini
DOCKERFILE
# We only use this image in the runner, never publish it.
docker build --quiet -t modxmcp-ci-fpm:temporary "$WORK/build"
python3 - <<'PY'
import os
from pathlib import Path
import shutil
site=Path(os.environ["RUNNER_TEMP"])/"modx-site"
work=Path(os.environ["RUNNER_TEMP"])/"mcp-fpm-ci"
if not (site/"config.core.php").is_file():raise SystemExit("Disposable MODX missing")
for node in ("a","b"):
    dst=work/("site-"+node)
    shutil.copytree(site,dst,symlinks=True)
    for cfg in (dst/"config.core.php",dst/"core/config/config.inc.php"):
        if not cfg.is_file():raise SystemExit("Missing MODX config "+str(cfg))
        text=cfg.read_text(encoding="utf-8")
        if str(site) not in text:
            raise SystemExit("MODX config missing canonical host path "+str(cfg))
        text=text.replace(str(site),"/srv/modx")
        text=text.replace("127.0.0.1","host.docker.internal")
        cfg.write_text(text,encoding="utf-8")
    cache=dst/"core/cache"
    if cache.is_dir():
        for parent,dirs,files in os.walk(cache):
            os.chmod(parent,0o777)
            for name in dirs:os.chmod(Path(parent)/name,0o777)
            for name in files:os.chmod(Path(parent)/name,0o666)
PY

cleanup() {
  local result=$?
  if [[ "$result" -ne 0 ]]; then
    echo "Real FPM two-container check failed; sanitized container diagnostics:" >&2
    for node in mcp-fpm-a mcp-fpm-b mcp-fpm-nginx; do
      docker logs --tail 45 "$node" >&2 || true
    done
  fi
  docker rm -f mcp-fpm-nginx mcp-fpm-a mcp-fpm-b >/dev/null 2>&1 || true
  docker network rm mcp-fpm-test-net >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker network create mcp-fpm-test-net >/dev/null
for node in a b; do
  docker run --detach --rm --name "mcp-fpm-$node" \
    --hostname "isolated-fpm-$node" \
    --network mcp-fpm-test-net \
    --add-host host.docker.internal:host-gateway \
    --env MODXMCP_DISPOSABLE_CI=1 \
    --env MODX_CONFIG_CORE=/srv/modx/config.core.php \
    --volume "$WORK/site-$node:/srv/modx:rw" \
    --volume "$REPO/_build:/srv/test:ro" \
    --volume "$SHARED:/srv/shared:rw" \
    modxmcp-ci-fpm:temporary >/dev/null
  docker exec "mcp-fpm-$node" php -r 'if (!extension_loaded("pdo_mysql"))exit(2);'
done
cat > "$WORK/nginx.conf" <<'NGINX'
server {
    listen 8080;
    server_name localhost;
    client_max_body_size 1m;
    location = /a {
        include fastcgi_params;
        fastcgi_pass mcp-fpm-a:9000;
        fastcgi_param SCRIPT_FILENAME /srv/test/test.ci-fpm-cross-node-endpoint.php;
        fastcgi_param MODXMCP_NODE a;
        fastcgi_read_timeout 60s;
    }
    location = /b {
        include fastcgi_params;
        fastcgi_pass mcp-fpm-b:9000;
        fastcgi_param SCRIPT_FILENAME /srv/test/test.ci-fpm-cross-node-endpoint.php;
        fastcgi_param MODXMCP_NODE b;
        fastcgi_read_timeout 60s;
    }
}
NGINX
docker run --detach --rm --name mcp-fpm-nginx \
  --network mcp-fpm-test-net \
  --publish 127.0.0.1:18931:8080 \
  --volume "$WORK/nginx.conf:/etc/nginx/conf.d/default.conf:ro" \
  nginx:1.27-alpine >/dev/null
export MCP_FPM_SHARED="$SHARED"
python3 _build/test.ci-fpm-two-containers.py
echo "REAL_FPM_CONTAINERS_ISOLATED_TEST_OK"
