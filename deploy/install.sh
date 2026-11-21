#!/usr/bin/env bash
# =============================================================================
#  DramaEarn — one-command installer / updater for aaPanel servers
#
#  Pehli dafa install aur har update ke liye yahi ek command (root se):
#
#    bash <(curl -fsSL https://raw.githubusercontent.com/knsoftic/dreamdrama/main/deploy/install.sh)
#
#  First run : code download, composer, .env, database, admin, nginx, cron
#  Later runs: latest code pull, composer, migrations, cache (kuch nahi poochta)
# =============================================================================
set -Eeuo pipefail

REPO_URL="${REPO_URL:-https://github.com/knsoftic/dreamdrama.git}"
BRANCH="${BRANCH:-main}"
DEFAULT_DOMAIN="${DEFAULT_DOMAIN:-dramaearn.site}"

# ----------------------------------------------------------------------------- helpers
if [ -t 1 ]; then
    B=$'\e[1m'; G=$'\e[32m'; Y=$'\e[33m'; R=$'\e[31m'; C=$'\e[36m'; N=$'\e[0m'
else
    B=''; G=''; Y=''; R=''; C=''; N=''
fi
TTY=/dev/tty
STEP=0
TOTAL=9

step() { STEP=$((STEP + 1)); echo; echo "${B}${C}[$STEP/$TOTAL] $*${N}"; }
ok()   { echo "  ${G}✔${N} $*"; }
info() { echo "  • $*"; }
warn() { echo "  ${Y}!${N} $*"; }
die()  { echo; echo "  ${R}✘ $*${N}"; echo; exit 1; }
trap 'echo; echo "  ${R}✘ Line $LINENO par command fail hui. Upar ka error dekhein, theek kar ke dobara chalayein.${N}"' ERR

# ask VAR "Sawal" "default"   (default khali ho to jawab zaroori hai)
ask() {
    local __var="$1" __q="$2" __def="${3:-}" __ans=""
    while true; do
        if [ -n "$__def" ]; then
            read -r -p "  $__q [$__def]: " __ans < "$TTY" || true
            __ans="${__ans:-$__def}"
        else
            read -r -p "  $__q: " __ans < "$TTY" || true
        fi
        [ -n "$__ans" ] && break
        echo "  ${Y}Yeh zaroori hai.${N}"
    done
    printf -v "$__var" '%s' "$__ans"
}

# ask_optional VAR "Sawal"   (Enter = khali)
ask_optional() {
    local __var="$1" __q="$2" __ans=""
    read -r -p "  $__q: " __ans < "$TTY" || true
    printf -v "$__var" '%s' "$__ans"
}

# ask_secret VAR "Sawal" [confirm]
ask_secret() {
    local __var="$1" __q="$2" __confirm="${3:-}" __a="" __b=""
    while true; do
        read -r -s -p "  $__q: " __a < "$TTY" || true; echo
        if [ -z "$__a" ]; then echo "  ${Y}Yeh zaroori hai.${N}"; continue; fi
        if [ -n "$__confirm" ]; then
            read -r -s -p "  Dobara likhein: " __b < "$TTY" || true; echo
            if [ "$__a" != "$__b" ]; then echo "  ${Y}Dono match nahi hue, dobara likhein.${N}"; continue; fi
        fi
        break
    done
    printf -v "$__var" '%s' "$__a"
}

# confirm "Sawal"   (Enter = haan)
confirm() {
    local __a=""
    read -r -p "  $1 [Y/n]: " __a < "$TTY" || true
    [[ ! "$__a" =~ ^[Nn] ]]
}

pm_install() {
    if command -v apt-get >/dev/null 2>&1; then
        apt-get update -qq >/dev/null 2>&1 || true
        DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "$@" >/dev/null
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y -q "$@" >/dev/null
    elif command -v yum >/dev/null 2>&1; then
        yum install -y -q "$@" >/dev/null
    else
        return 1
    fi
}

# PHP with aaPanel's disabled functions / open_basedir lifted for CLI only.
# (PHPCLI array is filled once PHP is found.)
PHPCLI=()
php_cli() { "${PHPCLI[@]}" "$@"; }
artisan() { "${PHPCLI[@]}" artisan "$@"; }

# set_env KEY VALUE  — .env mein value likhta hai (quotes/escaping khud handle)
set_env() {
    K="$1" V="$2" "${PHPCLI[@]}" -r '
        $f = ".env"; $k = getenv("K"); $v = (string) getenv("V");
        if ($v !== "" && !preg_match("/^[A-Za-z0-9_.\/:@+-]+$/", $v)) {
            $v = strpos($v, "\x27") === false ? "\x27".$v."\x27" : "\"".addcslashes($v, "\\\"\$")."\"";
        }
        $lines = file_exists($f) ? file($f, FILE_IGNORE_NEW_LINES) : [];
        $done = false;
        foreach ($lines as $i => $l) {
            if (preg_match("/^#?\s*".preg_quote($k, "/")."=/", $l)) {
                if (!$done) { $lines[$i] = "$k=$v"; $done = true; } else { unset($lines[$i]); }
            }
        }
        if (!$done) { $lines[] = "$k=$v"; }
        file_put_contents($f, implode("\n", $lines)."\n");'
}

# ----------------------------------------------------------------------------- start
echo
echo "${B}======================================================${N}"
echo "${B}   DramaEarn — aaPanel Installer / Updater${N}"
echo "${B}======================================================${N}"

[ "$(id -u)" -eq 0 ] || die "Root user se chalayein (aaPanel ka Terminal root hota hai) ya command se pehle 'sudo' lagayein."
[ -r "$TTY" ] || die "Yeh script interactive terminal mein chalayein (aaPanel Terminal ya SSH)."

# ----------------------------------------------------------------------------- 1. site
step "Website"
ask DOMAIN "Domain" "$DEFAULT_DOMAIN"
DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN#https://}"; DOMAIN="${DOMAIN%%/*}"
ask APP_DIR "Website folder" "/www/wwwroot/$DOMAIN"
APP_DIR="${APP_DIR%/}"

MODE="install"
if [ -f "$APP_DIR/.env" ] && [ -f "$APP_DIR/artisan" ]; then MODE="update"; fi
if [ "$MODE" = "update" ]; then ok "Pehle se installed hai → UPDATE mode (koi sawal nahi poochega)"; else ok "Naya INSTALL"; fi

NGINX_CONF="/www/server/panel/vhost/nginx/$DOMAIN.conf"
APACHE_CONF="/www/server/panel/vhost/apache/$DOMAIN.conf"
REWRITE_CONF="/www/server/panel/vhost/rewrite/$DOMAIN.conf"
WEB=""
if [ -f "$NGINX_CONF" ] && { pgrep -x nginx >/dev/null 2>&1 || [ ! -f "$APACHE_CONF" ]; }; then
    WEB="nginx"
elif [ -f "$APACHE_CONF" ]; then
    WEB="apache"
fi
if [ -n "$WEB" ]; then ok "aaPanel website mili ($WEB)"; else warn "aaPanel mein '$DOMAIN' ki website config nahi mili — web server ki settings aap ko khud karni hongi (docs dekhein)."; fi

# ----------------------------------------------------------------------------- 2. server check
step "Server check (PHP, extensions, tools)"
for tool in git curl unzip; do
    if ! command -v "$tool" >/dev/null 2>&1; then
        info "$tool install ho raha hai..."
        pm_install "$tool" || die "'$tool' install nahi ho saka. Khud install karein aur dobara chalayein."
    fi
done
ok "git, curl, unzip mojood"

PHPV=""
if [ "$WEB" = "nginx" ]; then PHPV="$(grep -oE 'enable-php-[0-9]+' "$NGINX_CONF" | head -n1 | grep -oE '[0-9]+$' || true)"; fi
if [ "$WEB" = "apache" ]; then PHPV="$(grep -oE 'php-cgi-[0-9]+' "$APACHE_CONF" | head -n1 | grep -oE '[0-9]+$' || true)"; fi
if [ "$PHPV" = "00" ]; then die "Website par PHP 'Static' select hai. aaPanel → Website → $DOMAIN → PHP version → 8.2 ya 8.3 select karein."; fi

PHP=""
if [ -n "$PHPV" ] && [ -x "/www/server/php/$PHPV/bin/php" ]; then PHP="/www/server/php/$PHPV/bin/php"; fi
if [ -z "$PHP" ]; then
    for v in 85 84 83 82; do
        if [ -x "/www/server/php/$v/bin/php" ]; then PHP="/www/server/php/$v/bin/php"; PHPV="${PHPV:-$v}"; break; fi
    done
fi
if [ -z "$PHP" ] && command -v php >/dev/null 2>&1; then PHP="$(command -v php)"; fi
[ -n "$PHP" ] || die "PHP nahi mila. aaPanel → App Store se PHP 8.2 ya 8.3 install karein."
PHPCLI=("$PHP" -d disable_functions= -d open_basedir= -d memory_limit=-1)

PHP_ID="$(php_cli -r 'echo PHP_VERSION_ID;')"
PHP_VER="$(php_cli -r 'echo PHP_VERSION;')"
[ "$PHP_ID" -ge 80200 ] || die "Website ka PHP $PHP_VER hai, kam az kam 8.2 chahiye. aaPanel → Website → $DOMAIN → PHP version → 8.2/8.3 karein (App Store se install bhi karna ho sakta hai)."
ok "PHP $PHP_VER ($PHP)"

MODS="$(php_cli -m | tr '[:upper:]' '[:lower:]')"
missing=()
for ext in pdo_mysql mbstring openssl tokenizer ctype fileinfo dom xml; do
    grep -qx "$ext" <<<"$MODS" || missing+=("$ext")
done
if [ "${#missing[@]}" -gt 0 ]; then
    die "Yeh PHP extensions missing hain: ${missing[*]}
     aaPanel → App Store → PHP-${PHP_VER%.*} → Setting → Install extensions → in ko install karein,
     phir yahi command dobara chalayein."
fi
ok "Zaroori extensions mojood (fileinfo, pdo_mysql, mbstring ...)"
grep -qx "gd" <<<"$MODS" || warn "PHP 'gd' extension nahi hai (optional). Thumbnails phir bhi chalenge."

# ----------------------------------------------------------------------------- 3. code
step "Code GitHub se ($REPO_URL)"
mkdir -p "$APP_DIR"
cd "$APP_DIR"
if ! git config --global --get-all safe.directory 2>/dev/null | grep -qxF "$APP_DIR"; then
    git config --global --add safe.directory "$APP_DIR"
fi
if [ -d .git ]; then
    git remote set-url origin "$REPO_URL"
    git fetch --quiet origin "$BRANCH"
    git reset --quiet --hard "origin/$BRANCH"
else
    git init -q
    git remote add origin "$REPO_URL"
    git fetch --quiet origin "$BRANCH"
    git checkout -q -f -B "$BRANCH" "origin/$BRANCH"
fi
ok "Latest commit: $(git log -1 --format='%h — %s')"

# ----------------------------------------------------------------------------- 4. composer
step "PHP packages (composer)"
if [ ! -f composer.phar ]; then
    curl -fsSL https://getcomposer.org/download/latest-2.x/composer.phar -o composer.phar || die "Composer download nahi hua (internet check karein)."
fi
COMPOSER_ALLOW_SUPERUSER=1 "${PHPCLI[@]}" composer.phar install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
ok "Packages install ho gaye"

# ----------------------------------------------------------------------------- 5. configuration
NEED_ADMIN=0
if [ "$MODE" = "install" ]; then
    step "Configuration (sirf pehli dafa)"
    ask SITE_NAME "Site ka naam" "DramaEarn"
    if confirm "Domain par SSL (https) laga hai / lagayenge?"; then SCHEME="https"; else SCHEME="http"; fi

    echo
    echo "  ${B}Database${N} (jo aaPanel → Databases mein banaya hai)"
    while true; do
        ask DB_DATABASE "Database name"
        ask DB_USERNAME "Database username" "$DB_DATABASE"
        ask_secret DB_PASSWORD "Database password"
        DB_HOST=""; DB_ERR=""
        for h in 127.0.0.1 localhost; do
            out="$(H="$h" D="$DB_DATABASE" U="$DB_USERNAME" W="$DB_PASSWORD" "${PHPCLI[@]}" -r '
                try { new PDO("mysql:host=".getenv("H").";port=3306;dbname=".getenv("D"), getenv("U"), getenv("W")); echo "OK"; }
                catch (Throwable $e) { echo $e->getMessage(); }' 2>&1 || true)"
            if [ "$out" = "OK" ]; then DB_HOST="$h"; break; fi
            DB_ERR="$out"
        done
        if [ -n "$DB_HOST" ]; then ok "Database connect ho gaya"; break; fi
        warn "Database se connect nahi hua: $DB_ERR"
        warn "Naam / username / password aaPanel → Databases se dobara check karein."
    done

    NEED_ADMIN=1
    cp .env.example .env
    set_env APP_NAME "$SITE_NAME"
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "$SCHEME://$DOMAIN"
    set_env APP_TIMEZONE Asia/Karachi
    set_env LOG_LEVEL error
    set_env DB_CONNECTION mysql
    set_env DB_HOST "$DB_HOST"
    set_env DB_PORT 3306
    set_env DB_DATABASE "$DB_DATABASE"
    set_env DB_USERNAME "$DB_USERNAME"
    set_env DB_PASSWORD "$DB_PASSWORD"
    set_env SESSION_DRIVER database
    set_env QUEUE_CONNECTION sync
    set_env CACHE_STORE database
    set_env FILESYSTEM_DISK public
    if [ "$SCHEME" = "https" ]; then set_env SESSION_SECURE_COOKIE true; fi
    sed -i '/^ADMIN_/d; /^# Admin account created/d' .env
    chmod 640 .env
    artisan key:generate --force >/dev/null
    ok ".env ban gayi"
else
    step "Configuration"
    ok "Purani .env hi use hogi (database / admin waghaira wahi rahenge)"
    SITE_NAME=""
fi

# ----------------------------------------------------------------------------- 6. database
step "Database tables"
artisan optimize:clear >/dev/null
artisan migrate --force
artisan db:seed --force >/dev/null
ok "Tables aur plans tayyar"

if [ "$NEED_ADMIN" = "0" ] && ! artisan app:admin --check >/dev/null 2>&1; then
    warn "Koi admin account nahi mila — abhi bana lete hain."
    NEED_ADMIN=1
fi
if [ "$NEED_ADMIN" = "1" ]; then
    echo
    echo "  ${B}Admin account${N}"
    ask ADMIN_NAME "Admin ka naam" "Admin"
    while true; do
        ask ADMIN_EMAIL "Admin email (is se login hoga)"
        [[ "$ADMIN_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] && break
        warn "Sahi email likhein."
    done
    ask_optional ADMIN_PHONE "Admin mobile number (optional — Enter dabayein skip ke liye)"
    while true; do
        ask_secret ADMIN_PASSWORD "Admin password (kam az kam 8 characters)" confirm
        [ "${#ADMIN_PASSWORD}" -ge 8 ] && break
        warn "Password kam az kam 8 characters ka ho."
    done
    SETUP_ADMIN_PASSWORD="$ADMIN_PASSWORD" "${PHPCLI[@]}" artisan app:admin \
        --name="$ADMIN_NAME" --email="$ADMIN_EMAIL" --phone="$ADMIN_PHONE" --site-name="$SITE_NAME"
    unset ADMIN_PASSWORD
fi

# ----------------------------------------------------------------------------- 7. files & permissions
step "Storage link, cache aur permissions"
if [ -e public/storage ] && [ ! -L public/storage ]; then
    mv public/storage "public/storage.bak-$(date +%Y%m%d%H%M%S)"
    warn "public/storage ek folder tha — backup bana kar link banaya"
fi
[ -L public/storage ] || artisan storage:link >/dev/null
artisan optimize >/dev/null
ok "Config, routes, views cache ho gaye"

WEB_USER="www"
if ! id "$WEB_USER" >/dev/null 2>&1; then
    WEB_USER="$(ps -eo user,comm | awk '/php-fpm|nginx|httpd/ && $1 != "root" {print $1; exit}')"
    WEB_USER="${WEB_USER:-www-data}"
fi
chown -R "$WEB_USER:$WEB_USER" "$APP_DIR" 2>/dev/null || true
find storage bootstrap/cache -type d -exec chmod 775 {} + 2>/dev/null || true
find storage bootstrap/cache -type f -exec chmod 664 {} + 2>/dev/null || true
chmod 640 .env
ok "Owner: $WEB_USER"

# ----------------------------------------------------------------------------- 8. web server
step "Web server (aaPanel) settings"
if [ "$WEB" = "nginx" ]; then
    if grep -qE "^\s*root\s+$APP_DIR/public;" "$NGINX_CONF" && grep -q "try_files" "$REWRITE_CONF" 2>/dev/null; then
        ok "Nginx pehle se sahi set hai"
    elif confirm "Nginx khud set karun? (root → /public, Laravel URL rewrite, 256MB upload)"; then
        TS="$(date +%Y%m%d%H%M%S)"
        cp "$NGINX_CONF" "$NGINX_CONF.bak-$TS"
        [ -f "$REWRITE_CONF" ] && cp "$REWRITE_CONF" "$REWRITE_CONF.bak-$TS"
        sed -i -E "s#^(\s*root\s+)$APP_DIR/?;#\1$APP_DIR/public;#" "$NGINX_CONF"
        cat > "$REWRITE_CONF" <<'NGINX'
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
client_max_body_size 256m;
NGINX
        NGINX_BIN="/www/server/nginx/sbin/nginx"
        [ -x "$NGINX_BIN" ] || NGINX_BIN="nginx"
        if "$NGINX_BIN" -t >/dev/null 2>&1; then
            "$NGINX_BIN" -s reload
            ok "Nginx set aur reload ho gaya (backup: *.bak-$TS)"
        else
            cp "$NGINX_CONF.bak-$TS" "$NGINX_CONF"
            if [ -f "$REWRITE_CONF.bak-$TS" ]; then cp "$REWRITE_CONF.bak-$TS" "$REWRITE_CONF"; else rm -f "$REWRITE_CONF"; fi
            warn "Nginx test fail hua, purani config wapas laga di. Docs ke 'Manual steps' follow karein."
        fi
    else
        warn "Skip kiya. aaPanel mein Running directory = /public aur URL rewrite = laravel5 khud set karein."
    fi
elif [ "$WEB" = "apache" ]; then
    if grep -q "DocumentRoot \"$APP_DIR/public\"" "$APACHE_CONF"; then
        ok "Apache pehle se sahi set hai"
    elif confirm "Apache khud set karun? (DocumentRoot → /public)"; then
        TS="$(date +%Y%m%d%H%M%S)"
        cp "$APACHE_CONF" "$APACHE_CONF.bak-$TS"
        sed -i -E "s#DocumentRoot \"$APP_DIR/?\"#DocumentRoot \"$APP_DIR/public\"#" "$APACHE_CONF"
        if /www/server/apache/bin/apachectl -t >/dev/null 2>&1; then
            /etc/init.d/httpd reload >/dev/null 2>&1 || /www/server/apache/bin/apachectl -k graceful
            ok "Apache set aur reload ho gaya"
        else
            cp "$APACHE_CONF.bak-$TS" "$APACHE_CONF"
            warn "Apache test fail hua, purani config wapas. Docs ke 'Manual steps' follow karein."
        fi
    fi
else
    warn "Web server config skip (website nahi mili)."
fi

PHP_INI="/www/server/php/$PHPV/etc/php.ini"
if [ -n "$PHPV" ] && [ -f "$PHP_INI" ]; then
    if grep -qiE '^\s*upload_max_filesize\s*=\s*256M' "$PHP_INI"; then
        ok "PHP upload limit pehle se 256MB"
    elif confirm "PHP upload limit 256MB karun? (video upload ke liye zaroori)"; then
        cp "$PHP_INI" "$PHP_INI.bak-$(date +%Y%m%d%H%M%S)"
        sed -i -E 's/^\s*upload_max_filesize\s*=.*/upload_max_filesize = 256M/; s/^\s*post_max_size\s*=.*/post_max_size = 256M/; s/^\s*max_execution_time\s*=.*/max_execution_time = 300/' "$PHP_INI"
        /etc/init.d/php-fpm-"$PHPV" reload >/dev/null 2>&1 || systemctl reload php-fpm-"$PHPV" >/dev/null 2>&1 || true
        ok "PHP limit set aur reload"
    fi
fi

# ----------------------------------------------------------------------------- 9. cron + check
step "Scheduler (cron) aur final check"
if command -v crontab >/dev/null 2>&1; then
    CRON_LINE="* * * * * cd $APP_DIR && $PHP -d disable_functions= artisan schedule:run >> /dev/null 2>&1"
    if crontab -u "$WEB_USER" -l 2>/dev/null | grep -qF "cd $APP_DIR && "; then
        ok "Cron pehle se laga hai"
    else
        { crontab -u "$WEB_USER" -l 2>/dev/null || true; echo "$CRON_LINE"; } | crontab -u "$WEB_USER" -
        ok "Cron add ho gaya (har minute, user: $WEB_USER)"
    fi
else
    warn "crontab nahi mila — aaPanel → Cron mein yeh 'Shell script' har minute ka add karein:"
    info "cd $APP_DIR && $PHP artisan schedule:run"
fi

CODE="$(curl -s -o /dev/null -m 15 -w '%{http_code}' -H "Host: $DOMAIN" http://127.0.0.1/ || true)"
case "$CODE" in
    200|301|302) ok "Website jawab de rahi hai (HTTP $CODE)" ;;
    *) warn "Local check par HTTP '$CODE' mila. Browser mein khol kar dekhein; 500 aaye to: tail -n 50 $APP_DIR/storage/logs/laravel.log" ;;
esac

URL="$(grep -E '^APP_URL=' .env | cut -d= -f2- | tr -d "\"'")"
echo
echo "${B}${G}======================================================${N}"
if [ "$MODE" = "install" ]; then
    echo "${B}${G}   Installation mukammal! 🎉${N}"
else
    echo "${B}${G}   Update mukammal! ✅${N}"
fi
echo "${B}${G}======================================================${N}"
echo "  Website     : $URL"
echo "  Admin panel : $URL/admin"
[ "$NEED_ADMIN" = "1" ] && echo "  Admin login : $ADMIN_EMAIL"
echo
echo "  Agli dafa update ke liye (GitHub par push ke baad) yahi command chalayein:"
echo "  ${C}bash <(curl -fsSL https://raw.githubusercontent.com/knsoftic/dreamdrama/main/deploy/install.sh)${N}"
echo
