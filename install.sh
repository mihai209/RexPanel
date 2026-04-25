#!/usr/bin/env bash
set -euo pipefail

# RA-panel / Rex Panel installer
# - Detects Debian/Ubuntu
# - Installs required packages
# - Clones github.com/mihai209/rex-panel
# - Asks interactive setup questions
# - Optionally configures Nginx + SSL
# - Uses /var/www/rex-panel for production-style installs when Nginx is enabled
#
# Usage:
#   sudo bash install.sh
#
# Notes:
# - For SSL, the domain must already point to this server
# - If you choose SQLite, the script creates the database file

REPO_URL="https://github.com/mihai209/RexPanel.git"
DEFAULT_DEV_DIR="$HOME/rex-panel"
DEFAULT_WEB_DIR="/var/www/rex-panel"
APP_DIR=""
APP_NAME="RA-panel"
APP_URL="http://localhost"
DB_CONNECTION="sqlite"
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_DATABASE="laravel"
DB_USERNAME="root"
DB_PASSWORD=""
UI_WS_HOST="localhost"
UI_WS_PORT="8082"
UI_WS_SCHEME="ws"
ENABLE_NGINX="n"
ENABLE_SSL="n"
DOMAIN=""
SSL_EMAIL=""
INSTALL_NODE="y"
INSTALL_PHP_EXT="y"
SETUP_CRON="n"
SETUP_UFW="n"
PHP_VERSION="8.3"
PHP_FPM_SOCK="/run/php/php8.3-fpm.sock"
PROJECT_USER="www-data"
PROJECT_GROUP="www-data"

log() { echo -e "[+] $*"; }
warn() { echo -e "[!] $*"; }
err() { echo -e "[x] $*" >&2; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || { err "Missing required command: $1"; exit 1; }
}

prompt() {
  local var_name="$1"
  local message="$2"
  local default_value="${3:-}"
  local input=""

  if [[ -n "$default_value" ]]; then
    read -r -p "$message [$default_value]: " input
    input="${input:-$default_value}"
  else
    read -r -p "$message: " input
  fi
  printf -v "$var_name" '%s' "$input"
}

prompt_yn() {
  local var_name="$1"
  local message="$2"
  local default_value="${3:-n}"
  local input=""

  if [[ "$default_value" == "y" ]]; then
    read -r -p "$message [Y/n]: " input
    input="${input:-y}"
  else
    read -r -p "$message [y/N]: " input
    input="${input:-n}"
  fi

  input="$(echo "$input" | tr '[:upper:]' '[:lower:]')"
  if [[ "$input" != "y" && "$input" != "n" ]]; then
    err "Please answer y or n."
    exit 1
  fi
  printf -v "$var_name" '%s' "$input"
}

check_root() {
  if [[ "$EUID" -ne 0 ]]; then
    err "Run this script as root or with sudo."
    exit 1
  fi
}

detect_os() {
  if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
  else
    err "Cannot detect OS: /etc/os-release not found."
    exit 1
  fi

  OS_ID="${ID:-unknown}"
  OS_VERSION_ID="${VERSION_ID:-unknown}"
  OS_PRETTY_NAME="${PRETTY_NAME:-unknown}"

  if [[ "$OS_ID" != "debian" && "$OS_ID" != "ubuntu" ]]; then
    err "Unsupported OS: $OS_PRETTY_NAME"
    err "This installer supports Debian and Ubuntu only."
    exit 1
  fi

  if [[ "$OS_ID" == "debian" ]]; then
    case "$OS_VERSION_ID" in
      12|13) : ;;
      *)
        warn "Debian $OS_VERSION_ID detected. Script was written for Debian 12/13, but may still work."
        ;;
    esac
  fi

  log "Detected: $OS_PRETTY_NAME"
}

check_php_version_and_socket() {
  if command -v php8.3 >/dev/null 2>&1; then
    PHP_VERSION="8.3"
    PHP_FPM_SOCK="/run/php/php8.3-fpm.sock"
    return
  fi

  if command -v php8.2 >/dev/null 2>&1; then
    PHP_VERSION="8.2"
    PHP_FPM_SOCK="/run/php/php8.2-fpm.sock"
    return
  fi

  if command -v php >/dev/null 2>&1; then
    local php_v
    php_v="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
    if [[ "$php_v" == 8.3* ]]; then
      PHP_VERSION="8.3"
      PHP_FPM_SOCK="/run/php/php8.3-fpm.sock"
    elif [[ "$php_v" == 8.2* ]]; then
      PHP_VERSION="8.2"
      PHP_FPM_SOCK="/run/php/php8.2-fpm.sock"
    fi
  fi
}

apt_install_base() {
  export DEBIAN_FRONTEND=noninteractive
  log "Updating package lists..."
  apt-get update -y

  log "Installing base packages..."
  apt-get install -y \
    git curl unzip ca-certificates gnupg lsb-release apt-transport-https \
    software-properties-common zip
}

install_php() {
  log "Installing PHP and extensions..."

  if [[ "$OS_ID" == "ubuntu" ]]; then
    apt-get install -y \
      php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-fpm php${PHP_VERSION}-common \
      php${PHP_VERSION}-bcmath php${PHP_VERSION}-curl php${PHP_VERSION}-gd php${PHP_VERSION}-mbstring \
      php${PHP_VERSION}-mysql php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-xml php${PHP_VERSION}-zip \
      php${PHP_VERSION}-opcache php${PHP_VERSION}-redis php${PHP_VERSION}-soap php${PHP_VERSION}-intl \
      php${PHP_VERSION}-readline php${PHP_VERSION}-tokenizer php${PHP_VERSION}-dev \
      composer
  else
    if apt-cache show php${PHP_VERSION}-cli >/dev/null 2>&1; then
      apt-get install -y \
        php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-fpm php${PHP_VERSION}-common \
        php${PHP_VERSION}-bcmath php${PHP_VERSION}-curl php${PHP_VERSION}-gd php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mysql php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-xml php${PHP_VERSION}-zip \
        php${PHP_VERSION}-opcache php${PHP_VERSION}-redis php${PHP_VERSION}-soap php${PHP_VERSION}-intl \
        php${PHP_VERSION}-readline php${PHP_VERSION}-tokenizer php${PHP_VERSION}-dev \
        composer
    else
      warn "PHP ${PHP_VERSION} packages not found. Falling back to default PHP packages."
      apt-get install -y \
        php php-cli php-fpm php-common \
        php-bcmath php-curl php-gd php-mbstring \
        php-mysql php-sqlite3 php-xml php-zip \
        php-opcache php-redis php-soap php-intl \
        composer
    fi
  fi

  check_php_version_and_socket
}

install_node() {
  if [[ "$INSTALL_NODE" != "y" ]]; then
    return
  fi

  log "Installing Node.js..."
  if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
  fi
}

install_nginx_ssl_tools() {
  if [[ "$ENABLE_NGINX" != "y" ]]; then
    return
  fi

  log "Installing Nginx and Certbot..."
  apt-get install -y nginx certbot python3-certbot-nginx
}

install_redis_optional() {
  if [[ "$DB_CONNECTION" == "sqlite" ]]; then
    return
  fi

  if command -v redis-server >/dev/null 2>&1; then
    return
  fi

  log "Installing Redis server..."
  apt-get install -y redis-server php-redis
  systemctl enable --now redis-server || true
}

prepare_app_dir() {
  if [[ "$ENABLE_NGINX" == "y" ]]; then
    APP_DIR="$DEFAULT_WEB_DIR"
  else
    APP_DIR="$DEFAULT_DEV_DIR"
  fi

  if [[ -n "${APP_DIR_OVERRIDE:-}" ]]; then
    APP_DIR="$APP_DIR_OVERRIDE"
  fi
}

clone_repo() {
  if [[ -d "$APP_DIR/.git" ]]; then
    warn "Target directory already exists and looks like a git repo: $APP_DIR"
    read -r -p "Pull latest changes instead of re-cloning? [Y/n]: " pull_choice
    pull_choice="${pull_choice:-y}"
    pull_choice="$(echo "$pull_choice" | tr '[:upper:]' '[:lower:]')"
    if [[ "$pull_choice" == "y" ]]; then
      cd "$APP_DIR"
      git fetch --all --prune
      git reset --hard origin/HEAD || git reset --hard origin/main || true
      return
    fi
  fi

  if [[ -e "$APP_DIR" && ! -d "$APP_DIR/.git" ]]; then
    warn "Target directory exists but is not empty: $APP_DIR"
    read -r -p "Delete it and continue? [y/N]: " delete_choice
    delete_choice="${delete_choice:-n}"
    delete_choice="$(echo "$delete_choice" | tr '[:upper:]' '[:lower:]')"
    if [[ "$delete_choice" != "y" ]]; then
      err "Aborting. Choose an empty directory or let the script create one."
      exit 1
    fi
    rm -rf "$APP_DIR"
  fi

  if [[ "$ENABLE_NGINX" == "y" ]]; then
    mkdir -p "$(dirname "$APP_DIR")"
  fi

  log "Cloning repository into $APP_DIR..."
  git clone "$REPO_URL" "$APP_DIR"
  cd "$APP_DIR"
}

write_env_file() {
  if [[ ! -f .env.example ]]; then
    err ".env.example not found in $APP_DIR"
    exit 1
  fi

  log "Creating .env..."
  cp .env.example .env

  php artisan key:generate --force

  sed -i "s|^APP_NAME=.*|APP_NAME=${APP_NAME}|" .env
  sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
  sed -i "s|^UI_WS_HOST=.*|UI_WS_HOST=${UI_WS_HOST}|" .env
  sed -i "s|^UI_WS_PORT=.*|UI_WS_PORT=${UI_WS_PORT}|" .env
  sed -i "s|^UI_WS_SCHEME=.*|UI_WS_SCHEME=${UI_WS_SCHEME}|" .env

  sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=${DB_CONNECTION}|" .env
  sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST}|" .env
  sed -i "s|^DB_PORT=.*|DB_PORT=${DB_PORT}|" .env
  sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|" .env
  sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|" .env
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env

  if [[ -n "$DOMAIN" ]]; then
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
  fi
}

setup_database() {
  if [[ "$DB_CONNECTION" == "sqlite" ]]; then
    mkdir -p database
    touch database/database.sqlite
    chmod 664 database/database.sqlite || true
  fi
}

run_composer_and_npm() {
  log "Installing PHP dependencies..."
  composer install --no-interaction --no-dev --optimize-autoloader

  log "Installing frontend dependencies..."
  npm install

  log "Building frontend..."
  npm run build
}

run_migrations() {
  log "Running migrations..."
  php artisan migrate --force
}

cache_laravel() {
  log "Caching Laravel config/routes/views..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
}

set_permissions() {
  if [[ "$ENABLE_NGINX" != "y" ]]; then
    return
  fi

  log "Setting ownership and permissions for web deployment..."
  chown -R "$PROJECT_USER:$PROJECT_GROUP" "$APP_DIR"

  find "$APP_DIR" -type f -exec chmod 644 {} \;
  find "$APP_DIR" -type d -exec chmod 755 {} \;

  chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true
  chmod 664 "$APP_DIR/.env" || true
}

setup_nginx() {
  if [[ "$ENABLE_NGINX" != "y" ]]; then
    return
  fi

  if [[ -z "$DOMAIN" ]]; then
    warn "Nginx was enabled but no domain was provided. Skipping vhost creation."
    return
  fi

  local nginx_conf="/etc/nginx/sites-available/rex-panel.conf"
  local nginx_link="/etc/nginx/sites-enabled/rex-panel.conf"

  log "Creating Nginx config..."
  cat > "$nginx_conf" <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

  ln -sf "$nginx_conf" "$nginx_link"
  rm -f /etc/nginx/sites-enabled/default || true

  nginx -t
  systemctl enable --now nginx
  systemctl reload nginx

  if [[ "$ENABLE_SSL" == "y" ]]; then
    log "Requesting SSL certificate with Certbot..."
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$SSL_EMAIL" --redirect
  fi
}

setup_firewall() {
  if [[ "$SETUP_UFW" != "y" ]]; then
    return
  fi

  if ! command -v ufw >/dev/null 2>&1; then
    apt-get install -y ufw
  fi

  log "Configuring UFW..."
  ufw allow OpenSSH || true
  ufw allow "Nginx Full" || true
  ufw --force enable || true
}

setup_cron() {
  if [[ "$SETUP_CRON" != "y" ]]; then
    return
  fi

  log "Adding Laravel scheduler cron..."
  (crontab -l 2>/dev/null; echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1") | crontab -
}

setup_services_hint() {
  cat <<EOF

Done.

Install location:
  $APP_DIR

Web root:
  $APP_DIR/public

Useful next commands:
  cd "$APP_DIR"
  php artisan serve
  npm run dev

If you enabled Nginx, your site should be available at:
  ${APP_URL}
EOF
}

main() {
  check_root
  detect_os

  echo
  log "RA-panel / Rex Panel installer"
  echo

  prompt_yn ENABLE_NGINX "Configure Nginx for this panel?"
  if [[ "$ENABLE_NGINX" == "y" ]]; then
    APP_DIR="$DEFAULT_WEB_DIR"
    prompt DOMAIN "Domain for Nginx/SSL (example: panel.example.com)"
    prompt_yn ENABLE_SSL "Enable SSL with Certbot?"
    if [[ "$ENABLE_SSL" == "y" ]]; then
      prompt SSL_EMAIL "Email for SSL certificate registration"
    fi
  else
    prompt APP_DIR_OVERRIDE "Install directory" "$DEFAULT_DEV_DIR"
    APP_DIR="$APP_DIR_OVERRIDE"
  fi

  prompt APP_NAME "App name" "$APP_NAME"
  prompt APP_URL "App URL" "${ENABLE_NGINX:+https://${DOMAIN}}${ENABLE_NGINX:-$APP_URL}"
  prompt_yn INSTALL_NODE "Install Node.js automatically?" "y"
  prompt_yn INSTALL_PHP_EXT "Install PHP extensions automatically?" "y"
  prompt_yn SETUP_CRON "Set up a basic cron for Laravel scheduler?" "n"
  prompt_yn SETUP_UFW "Set up UFW firewall?" "y"

  echo
  log "Database setup"
  read -r -p "Database type [sqlite/mysql] (default sqlite): " db_choice
  db_choice="${db_choice:-sqlite}"
  db_choice="$(echo "$db_choice" | tr '[:upper:]' '[:lower:]')"

  if [[ "$db_choice" == "mysql" ]]; then
    DB_CONNECTION="mysql"
    prompt DB_HOST "MySQL host" "$DB_HOST"
    prompt DB_PORT "MySQL port" "$DB_PORT"
    prompt DB_DATABASE "MySQL database name" "$DB_DATABASE"
    prompt DB_USERNAME "MySQL username" "$DB_USERNAME"
    prompt DB_PASSWORD "MySQL password" "$DB_PASSWORD"
  else
    DB_CONNECTION="sqlite"
    DB_HOST="127.0.0.1"
    DB_PORT="3306"
    DB_DATABASE="database/database.sqlite"
    DB_USERNAME=""
    DB_PASSWORD=""
  fi

  echo
  log "WebSocket setup"
  prompt UI_WS_HOST "UI websocket host" "$UI_WS_HOST"
  prompt UI_WS_PORT "UI websocket port" "$UI_WS_PORT"
  prompt UI_WS_SCHEME "UI websocket scheme (ws/wss)" "$UI_WS_SCHEME"

  apt_install_base
  if [[ "$INSTALL_PHP_EXT" == "y" ]]; then
    install_php
  else
    check_php_version_and_socket
  fi
  install_node
  install_nginx_ssl_tools
  install_redis_optional

  clone_repo
  write_env_file
  setup_database
  run_composer_and_npm
  run_migrations
  cache_laravel
  set_permissions
  setup_nginx
  setup_firewall
  setup_cron

  setup_services_hint
}

main "$@"
