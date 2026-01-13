# Midgard

A modern Proxmox VE control panel built with Laravel 12 and Vue 3.

## Features

- **Node Management** - Add, configure, and monitor Proxmox VE nodes
- **Server Management** - Create, manage, and control virtual machines
- **Power Controls** - Start, stop, restart, shutdown VMs
- **noVNC Console** - Browser-based VM console access
- **Backup System** - Create, restore, and manage VM backups
- **IP Management** - Address pools with bulk assignment
- **Template Management** - Import and manage OS templates
- **User Management** - Admin and client user accounts
- **Activity Logging** - Audit trail for all actions
- **SSH Key Management** - Store and manage SSH keys

## Tech Stack

### Backend
- Laravel 12
- MySQL 8 / MariaDB / SQLite
- Laravel Sanctum (API authentication)
- Redis (optional, for cache/queue)

### Frontend
- Vue 3 (Composition API)
- TypeScript
- Tailwind CSS 4
- Pinia (state management)
- TanStack Query (data fetching)
- VeeValidate + Zod (form validation)

## Server Requirements

### Minimum Hardware
| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 1 core | 2+ cores |
| RAM | 1 GB | 2+ GB |
| Disk | 10 GB | 20+ GB SSD |

### Software Requirements

| Software | Version | Notes |
|----------|---------|-------|
| OS | Ubuntu 22.04 LTS / Debian 12 | Any Linux with systemd |
| PHP | 8.2+ | With required extensions |
| MySQL | 8.0+ | Or MariaDB 10.6+ |
| Redis | 6.0+ | Optional, for cache/queue |
| Nginx | 1.18+ | Or Apache 2.4+ |
| Node.js | 18+ | For building frontend |
| Composer | 2.0+ | PHP dependency manager |

### Required PHP Extensions

```
bcmath, ctype, curl, dom, fileinfo, json, mbstring, 
openssl, pdo, pdo_mysql, redis, tokenizer, xml, zip
```

Install on Ubuntu/Debian:
```bash
sudo apt install php8.2-{bcmath,curl,dom,mbstring,mysql,redis,xml,zip}
```

### Proxmox VE Requirements

| Component | Requirement |
|-----------|-------------|
| Proxmox VE | 7.0+ (8.0+ recommended) |
| API Access | API Token with VM.* privileges |
| Network | Panel must reach Proxmox on port 8006 |

## Installation

### One-Line Install (Ubuntu/Debian)

```bash
curl -sSL https://raw.githubusercontent.com/akumalabs/Midgard/main/install.sh | sudo bash
```

The installer automatically:
- Installs PHP 8.2, MySQL, Redis, Nginx, Node.js
- Creates and configures the database
- Builds the frontend
- Configures Nginx web server
- Sets proper permissions

After installation, you'll see:
```
╔═══════════════════════════════════════════════════════════╗
║          Installation Complete!                           ║
╠═══════════════════════════════════════════════════════════╣
║  URL        │  http://YOUR_SERVER_IP                      ║
║  Username   │  admin@midgard.local                        ║
║  Password   │  password                                   ║
╚═══════════════════════════════════════════════════════════╝
```

### SSL Setup (Optional)

```bash
apt install certbot python3-certbot-nginx -y
certbot --nginx -d yourdomain.com
```

### Manual Install

<details>
<summary>Click to expand manual installation steps</summary>

#### 1. Clone the repository

```bash
git clone https://github.com/akumalabs/Midgard.git
cd Midgard
```

#### 2. Install dependencies

```bash
composer install
npm install
```

#### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure:
- Database connection
- App URL
- Redis (optional)

#### 4. Run migrations

```bash
php artisan migrate --seed
```

This creates:
- Admin user: `admin@midgard.local` / `password`
- Demo user: `user@midgard.local` / `password`

#### 5. Build frontend

```bash
npm run build
```

#### 6. Start the server

```bash
php artisan serve
```

Visit `http://localhost:8000`

</details>

## Development

```bash
# Start Laravel dev server
php artisan serve

# Start Vite dev server with HMR
npm run dev
```

## Production Deployment

### Using Nginx

```nginx
server {
    listen 80;
    server_name midgard.example.com;
    root /var/www/midgard/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Environment Variables

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://midgard.example.com

# Use MySQL in production
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=midgard
DB_USERNAME=midgard
DB_PASSWORD=secure_password

# Redis for cache/queue
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

### Optimization

```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Build production assets
npm run build

# Run queue worker (optional)
php artisan queue:work --daemon
```

## API Documentation

### Authentication

All API endpoints require authentication via Sanctum bearer token.

```bash
# Login
curl -X POST /api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@midgard.local","password":"password"}'

# Use token in requests
curl /api/v1/auth/user \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Endpoints

| Resource | Admin | Client |
|----------|-------|--------|
| Auth | - | login, logout, user |
| Nodes | CRUD, test, sync, stats | - |
| Servers | CRUD, power, status | list, power, status, console |
| Locations | CRUD | - |
| Users | CRUD | - |
| Templates | CRUD, sync | - |
| Address Pools | CRUD | - |
| Backups | - | list, create, restore, delete, lock |
| SSH Keys | - | list, add, delete |
| Activity Logs | list | - |

## Adding a Proxmox Node

1. Create an API token in Proxmox:
   - Datacenter → Permissions → API Tokens
   - Create token for root@pam or a user with VM.* privileges
   - Copy Token ID and Secret

2. Add node in Midgard:
   - Admin → Nodes → Add Node
   - Enter FQDN, port (8006), and token credentials
   - Test connection and sync

## License

MIT
