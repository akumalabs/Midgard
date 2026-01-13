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

## Requirements

- PHP 8.2+
- Node.js 18+
- Composer
- MySQL 8+ / MariaDB 10.6+ / SQLite 3
- Redis (optional)

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/yourorg/midgard.git
cd midgard
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure:
- Database connection
- App URL
- Redis (optional)

### 4. Run migrations

```bash
php artisan migrate --seed
```

This creates:
- Admin user: `admin@midgard.local` / `password`
- Demo user: `user@midgard.local` / `password`

### 5. Build frontend

```bash
npm run build
```

### 6. Start the server

```bash
php artisan serve
```

Visit `http://localhost:8000`

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
