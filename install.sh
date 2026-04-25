#!/usr/bin/env bash
set -euo pipefail

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

check_root() {
  [[ "$EUID" -ne 0 ]] && { err "Run as root"; exit 1; }
}

detect_os() {
  . /etc/os-release
  log "Detected: ${PRETTY_NAME}"
}

apt_install_base() {
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y git curl unzip zip ca-certificates lsb-release
}

prepare_dir() {
  if [[ "$ENABLE_NGINX" == "y" ]]; then
    APP_DIR="$DEFAULT_WEB_DIR"
  else
    APP_DIR="$DEFAULT_DEV_DIR"
  fi
}

clone_repo() {
  rm -rf "$APP_DIR"
  mkdir -p "$APP_DIR"

  git clone "$REPO_URL" "$APP_DIR"
  cd "$APP_DIR"
}

install_php() {
  apt-get install -y php php-cli php-fpm php-mbstring php-xml php-curl php-zip php-sqlite3 composer
}

install_node() {
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
}

# ========================
# 🔥 FIX CRITICAL ORDER
# ========================
install_dependencies_first() {
  log "Installing PHP deps (composer)..."

  if [[ ! -f composer.json ]]; then
    err "composer.json missing"
    exit 1
  fi

  composer install --no-interaction --no-dev --optimize-autoloader

  if [[ ! -f vendor/autoload.php ]]; then
    err "Composer failed (vendor missing)"
    exit 1
  fi
}

write_env() {
  cp .env.example .env

  php artisan key:generate --force

  sed -i "s|^APP_NAME=.*|APP_NAME=${APP_NAME}|" .env
  sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
}

setup_db() {
  if [[ "$DB_CONNECTION" == "sqlite" ]]; then
    mkdir -p database
    touch database/database.sqlite
  fi
}

frontend_build() {
  npm install
  npm run build
}

migrate() {
  php artisan migrate --force
}

cache() {
  php artisan config:cache
  php artisan route:cache
}

main() {
  check_root
  detect_os

  log "RA-panel installer FIXED"

  prepare_dir

  apt_install_base

  [[ "$INSTALL_PHP_EXT" == "y" ]] && install_php
  [[ "$INSTALL_NODE" == "y" ]] && install_node

  clone_repo


  install_dependencies_first   # composer FIRST
  write_env                   # artisan safe now
  setup_db
  frontend_build

  migrate
  cache

  log "DONE ✔ Installed correctly"
}

main "$@"
