#!/bin/bash

# Midgard Control Panel Auto Installer
# Usage: curl -sSL https://raw.githubusercontent.com/akumalabs/Midgard/main/install.sh | sudo bash

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Variables
INSTALL_DIR="/var/www/midgard"
DB_NAME="midgard"
DB_USER="midgard"
DB_PASS=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
ADMIN_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 12)

# Banner
clear
echo -e "${CYAN}"
echo "  __  __ _     _                     _ "
echo " |  \/  (_)   | |                   | |"
echo " | \  / |_  __| | __ _  __ _ _ __ __| |"
echo " | |\/| | |/ _\` |/ _\` |/ _\` | '__/ _\` |"
echo " | |  | | | (_| | (_| | (_| | | | (_| |"
echo " |_|  |_|_|\__,_|\__, |\__,_|_|  \__,_|"
echo "                  __/ |                "
echo "                 |___/   Control Panel"
echo -e "${NC}"
echo ""

# Check root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (sudo)${NC}"
    exit 1
fi

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo -e "${RED}Error: Cannot detect OS${NC}"
    exit 1
fi

echo -e "${GREEN}➜${NC} Detected: $OS $VERSION"

# Only support Ubuntu/Debian
if [[ "$OS" != "ubuntu" && "$OS" != "debian" ]]; then
    echo -e "${RED}Error: Only Ubuntu and Debian are supported${NC}"
    exit 1
fi

echo -e "${GREEN}➜${NC} Starting installation..."
echo ""

# Update system
echo -e "${BLUE}[1/8]${NC} Updating system packages..."
apt update -qq
apt upgrade -y -qq

# Install dependencies
echo -e "${BLUE}[2/8]${NC} Installing PHP 8.2 and extensions..."
apt install -y -qq software-properties-common curl gnupg2

# Add PHP repository if needed
if ! command -v php8.2 &> /dev/null; then
    if [ "$OS" = "ubuntu" ]; then
        add-apt-repository -y ppa:ondrej/php
    else
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
        curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/php.gpg
    fi
    apt update -qq
fi

apt install -y -qq php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
    php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-bcmath \
    php8.2-gd php8.2-intl

# Install other services
echo -e "${BLUE}[3/8]${NC} Installing database, Redis, and Nginx..."

# Debian uses MariaDB, Ubuntu uses MySQL
if [ "$OS" = "debian" ]; then
    apt install -y -qq mariadb-server redis-server nginx git unzip
else
    apt install -y -qq mysql-server redis-server nginx git unzip
fi

# Install Composer
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Install Node.js 18
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
    apt install -y -qq nodejs
fi

# Configure MySQL
echo -e "${BLUE}[4/8]${NC} Configuring database..."
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Clone repository
echo -e "${BLUE}[5/8]${NC} Downloading Midgard..."
if [ -d "$INSTALL_DIR" ]; then
    rm -rf "$INSTALL_DIR"
fi
git clone -q https://github.com/akumalabs/Midgard.git "$INSTALL_DIR"
cd "$INSTALL_DIR"

# Install PHP dependencies
echo -e "${BLUE}[6/8]${NC} Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet

# Install Node dependencies and build
npm ci --silent 2>/dev/null
npm run build --silent 2>/dev/null

# Configure Laravel
echo -e "${BLUE}[7/8]${NC} Configuring application..."
cp .env.example .env
php artisan key:generate --no-interaction --quiet

# Update .env
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
sed -i "s/CACHE_STORE=.*/CACHE_STORE=redis/" .env
sed -i "s/SESSION_DRIVER=.*/SESSION_DRIVER=redis/" .env

# Run migrations
php artisan migrate --force --seed --quiet

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Optimize
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan storage:link --quiet 2>/dev/null || true

# Configure Nginx
echo -e "${BLUE}[8/8]${NC} Configuring web server..."

# Get server IP
SERVER_IP=$(hostname -I | awk '{print $1}')

cat > /etc/nginx/sites-available/midgard << 'NGINX'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/midgard/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

# Enable site
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/midgard /etc/nginx/sites-enabled/

# Restart services
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl restart redis-server
systemctl enable php8.2-fpm nginx redis-server mysql

# Output results
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          Installation Complete!                           ║${NC}"
echo -e "${GREEN}╠═══════════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}  URL        │  http://${SERVER_IP}                          ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  Username   │  admin@midgard.local                         ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  Password   │  password                                    ${GREEN}║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}⚠  Change the admin password immediately after login!${NC}"
echo ""
echo -e "Next steps:"
echo -e "  1. Point your domain to ${SERVER_IP}"
echo -e "  2. Run: ${CYAN}certbot --nginx -d yourdomain.com${NC}"
echo -e "  3. Add your Proxmox nodes in Admin → Nodes"
echo ""
echo -e "Documentation: ${BLUE}https://github.com/akumalabs/Midgard${NC}"
