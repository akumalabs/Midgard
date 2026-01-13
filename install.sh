#!/bin/bash

# Midgard Installer Script
# Usage: curl -sSL https://raw.githubusercontent.com/akumalabs/Midgard/main/install.sh | bash

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════╗"
echo "║         Midgard Installer v1.0           ║"
echo "║    Proxmox VE Control Panel              ║"
echo "╚══════════════════════════════════════════╝"
echo -e "${NC}"

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    echo -e "${YELLOW}Warning: Running as root. Creating midgard user is recommended.${NC}"
fi

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo -e "${RED}Cannot detect OS. Exiting.${NC}"
    exit 1
fi

echo -e "${GREEN}Detected OS: $OS $VERSION${NC}"

# Check requirements
check_command() {
    if command -v $1 &> /dev/null; then
        echo -e "${GREEN}✓${NC} $1 found"
        return 0
    else
        echo -e "${RED}✗${NC} $1 not found"
        return 1
    fi
}

echo ""
echo "Checking requirements..."
MISSING=0

check_command php || MISSING=1
check_command composer || MISSING=1
check_command node || MISSING=1
check_command npm || MISSING=1
check_command git || MISSING=1

if [ $MISSING -eq 1 ]; then
    echo ""
    echo -e "${YELLOW}Missing requirements. Install them with:${NC}"
    echo ""
    if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
        echo "  sudo apt update"
        echo "  sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \\"
        echo "      php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-bcmath \\"
        echo "      composer nodejs npm git"
    fi
    echo ""
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Set installation directory
DEFAULT_DIR="/var/www/midgard"
read -p "Installation directory [$DEFAULT_DIR]: " INSTALL_DIR
INSTALL_DIR=${INSTALL_DIR:-$DEFAULT_DIR}

echo ""
echo -e "${BLUE}Installing Midgard to $INSTALL_DIR${NC}"
echo ""

# Clone repository
if [ -d "$INSTALL_DIR" ]; then
    echo -e "${YELLOW}Directory exists. Pulling latest...${NC}"
    cd "$INSTALL_DIR"
    git pull origin main
else
    echo "Cloning repository..."
    sudo mkdir -p "$INSTALL_DIR"
    sudo chown $USER:$USER "$INSTALL_DIR"
    git clone https://github.com/akumalabs/Midgard.git "$INSTALL_DIR"
    cd "$INSTALL_DIR"
fi

# Install PHP dependencies
echo ""
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build
echo ""
echo "Installing Node dependencies..."
npm ci --silent

echo ""
echo "Building frontend..."
npm run build

# Setup environment
if [ ! -f .env ]; then
    echo ""
    echo "Setting up environment..."
    cp .env.example .env
    php artisan key:generate --no-interaction
    
    echo ""
    echo -e "${YELLOW}Configure your database in .env file${NC}"
    read -p "Database host [127.0.0.1]: " DB_HOST
    DB_HOST=${DB_HOST:-127.0.0.1}
    
    read -p "Database name [midgard]: " DB_NAME
    DB_NAME=${DB_NAME:-midgard}
    
    read -p "Database user [midgard]: " DB_USER
    DB_USER=${DB_USER:-midgard}
    
    read -sp "Database password: " DB_PASS
    echo ""
    
    # Update .env
    sed -i "s/DB_HOST=.*/DB_HOST=$DB_HOST/" .env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
fi

# Run migrations
echo ""
echo "Running migrations..."
php artisan migrate --force --seed

# Set permissions
echo ""
echo "Setting permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Optimize
echo ""
echo "Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║       Installation Complete!             ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════╝${NC}"
echo ""
echo -e "Login credentials:"
echo -e "  Admin: ${YELLOW}admin@midgard.local${NC} / ${YELLOW}password${NC}"
echo -e "  User:  ${YELLOW}user@midgard.local${NC} / ${YELLOW}password${NC}"
echo ""
echo -e "${RED}⚠ Change these passwords immediately!${NC}"
echo ""
echo "Next steps:"
echo "  1. Configure Nginx (see DEPLOYMENT.md)"
echo "  2. Setup SSL with Let's Encrypt"
echo "  3. Add your Proxmox nodes"
echo ""
echo -e "Documentation: ${BLUE}https://github.com/akumalabs/Midgard${NC}"
