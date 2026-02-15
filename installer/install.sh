#!/bin/bash

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging
LOG_FILE="/tmp/meowvpn_install.log"
INSTALL_START_TIME=$(date +%s)

# Print colored messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1" >> "$LOG_FILE"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >> "$LOG_FILE"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $1" >> "$LOG_FILE"
}

print_step() {
    echo -e "${BLUE}▶ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] STEP: $1" >> "$LOG_FILE"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $1" >> "$LOG_FILE"
}

# Function to wait for service with retry logic
wait_for_service() {
    local service_name=$1
    local check_command=$2
    local max_wait=${3:-120}
    local retry_interval=${4:-2}
    local wait_count=0
    
    print_info "Waiting for $service_name to be ready (max ${max_wait}s)..."
    while [ $wait_count -lt $max_wait ]; do
        if eval "$check_command" >> "$LOG_FILE" 2>&1; then
            print_success "$service_name is ready"
            return 0
        fi
        wait_count=$((wait_count + retry_interval))
        sleep $retry_interval
        echo -n "."
    done
    echo ""
    print_error "$service_name failed to start within ${max_wait} seconds"
    return 1
}

# Function to check if port is available
check_port() {
    local port=$1
    if command -v netstat &> /dev/null; then
        if netstat -tuln 2>/dev/null | grep -q ":$port "; then
            return 1
        fi
    elif command -v ss &> /dev/null; then
        if ss -tuln 2>/dev/null | grep -q ":$port "; then
            return 1
        fi
    fi
    return 0
}

# Function to check disk space (minimum 5GB free)
check_disk_space() {
    local min_space_gb=5
    local available_space_gb
    
    if command -v df &> /dev/null; then
        available_space_gb=$(df -BG "$PROJECT_DIR" 2>/dev/null | tail -1 | awk '{print $4}' | sed 's/G//')
        if [ -n "$available_space_gb" ] && [ "$available_space_gb" -lt "$min_space_gb" ]; then
            print_warning "Low disk space: ${available_space_gb}GB available (minimum ${min_space_gb}GB recommended)"
            return 1
        fi
    fi
    return 0
}

# Error handler
cleanup_on_error() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        print_error "Installation failed at step: ${LAST_STEP:-unknown}"
        print_info "Check log file: $LOG_FILE"
        exit $exit_code
    fi
}

trap cleanup_on_error ERR

# If running as root, sudo is not needed
if [ "$EUID" -eq 0 ]; then
    sudo() { "$@"; }
    export -f sudo
else
    # Check sudo access
    if ! sudo -n true 2>/dev/null; then
        print_info "This script requires sudo privileges. You may be prompted for your password."
    fi
fi

# Banner
clear
echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║           MeowVPN Installation Script v1.0                 ║"
echo "║           Professional VPN Management System              ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# Initialize log
echo "=== MeowVPN Installation Log ===" > "$LOG_FILE"
echo "Started at: $(date)" >> "$LOG_FILE"
echo "User: $(whoami)" >> "$LOG_FILE"
echo "OS: $(uname -a)" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

# Get project directory
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

print_info "Project directory: $PROJECT_DIR"
print_info "Log file: $LOG_FILE"
echo ""

# Check if setup is already complete
if [ -f "$PROJECT_DIR/backend/.env" ]; then
    if grep -q "^SETUP_COMPLETE\s*=\s*true" "$PROJECT_DIR/backend/.env" 2>/dev/null; then
        echo ""
        echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
        echo -e "${GREEN}║        Setup Already Completed!                          ║${NC}"
        echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
        echo ""
        print_info "Setup wizard has already been completed."
        print_info "If you need to reinstall, please remove SETUP_COMPLETE=true from backend/.env"
        echo ""
        exit 0
    fi
fi

# ============================================
# Step 1: Check and Install Prerequisites
# ============================================
LAST_STEP="Prerequisites Check"
print_step "Step 1/8: Checking prerequisites..."

# Function to check command
check_command() {
    if command -v "$1" &> /dev/null; then
        return 0
    else
        return 1
    fi
}

# Function to install package (Debian/Ubuntu)
install_package() {
    local package=$1
    print_info "Installing $package..."
    sudo apt-get update -qq
    sudo apt-get install -y "$package" >> "$LOG_FILE" 2>&1
}

# Check and install Docker
if ! check_command docker; then
    print_info "Docker is not installed. Installing Docker..."
    curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
    sudo sh /tmp/get-docker.sh >> "$LOG_FILE" 2>&1
    sudo usermod -aG docker "$USER" 2>/dev/null || true
    rm /tmp/get-docker.sh
    print_success "Docker installed"
    print_info "Please log out and log back in for Docker group changes to take effect, then run this script again."
    exit 0
else
    DOCKER_VERSION=$(docker --version | cut -d' ' -f3 | cut -d',' -f1)
    print_success "Docker is installed (version: $DOCKER_VERSION)"
fi

# Check Docker daemon
if ! docker info &> /dev/null; then
    print_error "Docker daemon is not running. Please start Docker and try again."
    exit 1
fi

# Check and install Docker Compose
if ! check_command docker-compose && ! docker compose version &> /dev/null; then
    print_info "Docker Compose is not installed. Installing Docker Compose..."
    DOCKER_COMPOSE_VERSION=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep tag_name | cut -d'"' -f4)
    sudo curl -L "https://github.com/docker/compose/releases/download/${DOCKER_COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    sudo chmod +x /usr/local/bin/docker-compose
    print_success "Docker Compose installed"
elif docker compose version &> /dev/null; then
    COMPOSE_VERSION=$(docker compose version --short)
    print_success "Docker Compose is installed (version: $COMPOSE_VERSION)"
else
    COMPOSE_VERSION=$(docker-compose --version | cut -d' ' -f4 | cut -d',' -f1)
    print_success "Docker Compose is installed (version: $COMPOSE_VERSION)"
fi

# Check and install Git
if ! check_command git; then
    print_info "Git is not installed. Installing Git..."
    install_package git
    print_success "Git installed"
else
    GIT_VERSION=$(git --version | cut -d' ' -f3)
    print_success "Git is installed (version: $GIT_VERSION)"
fi

# Check and install curl
if ! check_command curl; then
    print_info "curl is not installed. Installing curl..."
    install_package curl
    print_success "curl installed"
else
    print_success "curl is installed"
fi

# Check and install wget
if ! check_command wget; then
    print_info "wget is not installed. Installing wget..."
    install_package wget
    print_success "wget installed"
else
    print_success "wget is installed"
fi

echo ""

# Check disk space
print_info "Checking disk space..."
if ! check_disk_space; then
    print_warning "Continuing despite low disk space..."
fi

# Check if ports 80 and 443 are available (warn if in use)
print_info "Checking if ports 80 and 443 are available..."
if ! check_port 80; then
    print_warning "Port 80 is already in use. SSL certificate installation may fail."
    print_info "Make sure port 80 is available for Let's Encrypt certificate validation."
fi
if ! check_port 443; then
    print_warning "Port 443 is already in use. HTTPS may not work correctly."
fi

echo ""
print_success "All prerequisites are installed!"
echo ""

# ============================================
# Step 2: Create Directory Structure
# ============================================
LAST_STEP="Directory Structure"
print_step "Step 2/8: Creating directory structure..."

# Create necessary directories
DIRS=(
    "backups"
    "docker/certbot/conf"
    "docker/certbot/www"
    "backend/storage/app/public"
    "backend/storage/framework/cache"
    "backend/storage/framework/sessions"
    "backend/storage/framework/views"
    "backend/storage/logs"
    "backend/bootstrap/cache"
)

for dir in "${DIRS[@]}"; do
    if [ ! -d "$PROJECT_DIR/$dir" ]; then
        mkdir -p "$PROJECT_DIR/$dir"
        print_info "Created directory: $dir"
    fi
done

# Set permissions
if [ -d "$PROJECT_DIR/backend/storage" ]; then
    chmod -R 775 "$PROJECT_DIR/backend/storage" 2>/dev/null || true
    chmod -R 775 "$PROJECT_DIR/backend/bootstrap/cache" 2>/dev/null || true
fi

print_success "Directory structure created!"
echo ""

# ============================================
# Step 3: Create .env Files
# ============================================
LAST_STEP="Environment Configuration"
print_step "Step 3/8: Setting up environment configuration..."

# Create root .env if it doesn't exist
if [ ! -f "$PROJECT_DIR/.env" ]; then
    print_info "Creating root .env file..."
    cat > "$PROJECT_DIR/.env" << 'EOF'
# MeowVPN Environment Configuration
APP_NAME=MeowVPN
APP_ENV=production
APP_DEBUG=false

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=meowvpn
DB_USERNAME=meowvpn
DB_PASSWORD=changeme_please

# Redis Configuration
REDIS_HOST=redis
REDIS_PASSWORD=changeme_please
REDIS_PORT=6379

# Application URLs
API_DOMAIN=api.localhost
PANEL_DOMAIN=panel.localhost
SUBSCRIPTION_DOMAIN=sub.localhost

# Telegram Bot
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=

# Payment Gateway (Zibal)
ZIBAL_MERCHANT_ID=
ZIBAL_CALLBACK_URL=

# Docker Compose
COMPOSE_PROJECT_NAME=meowvpn

# Frontend
VITE_API_URL=http://api.localhost
FRONTEND_PORT=3000
EOF
    print_success "Root .env file created"
else
    print_info "Root .env file already exists"
fi

# Create backend .env if it doesn't exist
if [ ! -f "$PROJECT_DIR/backend/.env" ]; then
    print_info "Creating backend .env file..."
    cp "$PROJECT_DIR/backend/.env.example" "$PROJECT_DIR/backend/.env" 2>/dev/null || {
        # Create basic backend .env
        cat > "$PROJECT_DIR/backend/.env" << 'EOF'
APP_NAME=MeowVPN
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_NODE_ROLE=all

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=meowvpn
DB_USERNAME=meowvpn
DB_PASSWORD=changeme_please

REDIS_HOST=redis
REDIS_PASSWORD=changeme_please
REDIS_PORT=6379

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=

ZIBAL_MERCHANT_ID=
ZIBAL_CALLBACK_URL=
EOF
    }
    print_success "Backend .env file created"
else
    print_info "Backend .env file already exists"
fi

# Generate APP_KEY for Laravel if not set
if [ -f "$PROJECT_DIR/backend/.env" ]; then
    if ! grep -q "APP_KEY=base64:" "$PROJECT_DIR/backend/.env" || grep -q "^APP_KEY=$" "$PROJECT_DIR/backend/.env"; then
        print_info "Generating Laravel APP_KEY..."
        cd "$PROJECT_DIR/backend"
        docker run --rm -v "$(pwd)":/app -w /app php:8.2-cli php -r "echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;" >> .env 2>>"$LOG_FILE"
        cd "$PROJECT_DIR"
        print_success "APP_KEY generated"
    else
        print_info "APP_KEY already exists"
    fi
fi

echo ""
print_success "Environment configuration complete!"
echo ""

# ============================================
# Step 4: Build Docker Images
# ============================================
LAST_STEP="Docker Images Build"
print_step "Step 4/8: Building Docker images..."

print_info "This may take several minutes. Please wait..."
if docker compose build --progress=plain >> "$LOG_FILE" 2>&1; then
    print_success "Docker images built successfully"
else
    print_error "Failed to build Docker images. Check log: $LOG_FILE"
    exit 1
fi

echo ""

# ============================================
# Step 5: Start Core Services
# ============================================
LAST_STEP="Core Services Startup"
print_step "Step 5/8: Starting core services (PostgreSQL, Redis)..."

# Start PostgreSQL and Redis
if docker compose up -d postgres redis >> "$LOG_FILE" 2>&1; then
    print_success "Core services started"
else
    print_error "Failed to start core services"
    exit 1
fi

# Wait for PostgreSQL to be ready (increased timeout to 120 seconds)
if ! wait_for_service "PostgreSQL" "docker compose exec -T postgres pg_isready -U meowvpn" 120 2; then
    print_error "PostgreSQL failed to start. Check logs: $LOG_FILE"
    print_info "You can check PostgreSQL logs with: docker compose logs postgres"
    exit 1
fi

# Wait for Redis to be ready (increased timeout to 60 seconds)
if ! wait_for_service "Redis" "docker compose exec -T redis redis-cli ping" 60 2; then
    print_error "Redis failed to start. Check logs: $LOG_FILE"
    print_info "You can check Redis logs with: docker compose logs redis"
    exit 1
fi

echo ""

# ============================================
# Step 6: Run Database Migrations
# ============================================
LAST_STEP="Database Migrations"
print_step "Step 6/8: Running database migrations..."

# Start Laravel container temporarily for migrations
if docker compose up -d laravel >> "$LOG_FILE" 2>&1; then
    print_info "Laravel container started"
else
    print_error "Failed to start Laravel container"
    exit 1
fi

# Wait for Laravel to be ready (increased to 30 seconds with retry)
if ! wait_for_service "Laravel" "docker compose exec -T laravel php artisan --version" 30 3; then
    print_warning "Laravel container may not be fully ready, but continuing..."
fi

# Install Composer dependencies
print_info "Installing Composer dependencies..."
if docker compose exec -T laravel composer install --no-interaction --prefer-dist --optimize-autoloader >> "$LOG_FILE" 2>&1; then
    print_success "Composer dependencies installed"
else
    print_warning "Composer install had warnings, continuing..."
fi

# Run migrations
print_info "Running database migrations..."
if docker compose exec -T laravel php artisan migrate --force >> "$LOG_FILE" 2>&1; then
    print_success "Database migrations completed"
else
    print_error "Database migrations failed. Check log: $LOG_FILE"
    exit 1
fi

echo ""

# ============================================
# Step 7: Start All Services
# ============================================
LAST_STEP="All Services Startup"
print_step "Step 7/8: Starting all services..."

if docker compose up -d >> "$LOG_FILE" 2>&1; then
    print_success "All services started"
else
    print_error "Failed to start all services"
    exit 1
fi

# Wait for services to be ready (increased to 30 seconds)
print_info "Waiting for services to be ready..."
sleep 30

echo ""

# ============================================
# Step 8: Health Checks
# ============================================
LAST_STEP="Health Checks"
print_step "Step 8/8: Performing health checks..."

HEALTH_CHECK_FAILED=0

# Check PostgreSQL
if docker compose exec -T postgres pg_isready -U meowvpn >> "$LOG_FILE" 2>&1; then
    print_success "PostgreSQL health check passed"
else
    print_error "PostgreSQL health check failed"
    HEALTH_CHECK_FAILED=1
fi

# Check Redis
if docker compose exec -T redis redis-cli ping >> "$LOG_FILE" 2>&1; then
    print_success "Redis health check passed"
else
    print_error "Redis health check failed"
    HEALTH_CHECK_FAILED=1
fi

# Check Laravel (if API is accessible)
sleep 5
if curl -f http://localhost/api/health >> "$LOG_FILE" 2>&1; then
    print_success "Laravel API health check passed"
else
    print_warning "Laravel API health check failed (may be normal if setup is not complete)"
fi

if [ $HEALTH_CHECK_FAILED -eq 1 ]; then
    print_error "Some health checks failed. Please check the logs."
    print_info "Log file: $LOG_FILE"
    exit 1
fi

echo ""

# ============================================
# Installation Complete
# ============================================
INSTALL_END_TIME=$(date +%s)
INSTALL_DURATION=$((INSTALL_END_TIME - INSTALL_START_TIME))

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           Installation Completed Successfully!            ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
print_success "Installation completed in $INSTALL_DURATION seconds"
echo ""

print_info "Next steps:"
echo "  1. Configure your domains in .env file:"
echo "     - API_DOMAIN=api.yourdomain.com"
echo "     - PANEL_DOMAIN=panel.yourdomain.com"
echo "     - SUBSCRIPTION_DOMAIN=sub.yourdomain.com"
echo ""
echo "  2. Point your domains to this server's IP address"
echo ""
echo "  3. Access the setup wizard at: http://$(hostname -I | awk '{print $1}')"
echo "     or configure your domain and access: http://panel.yourdomain.com"
echo ""
echo "  4. Complete the setup wizard to:"
echo "     - Configure database and Redis connections"
echo "     - Set up domains and SSL certificates"
echo "     - Create admin account"
echo "     - Configure Telegram bot"
echo ""
echo "Useful commands:"
echo "  - View logs: docker compose logs -f"
echo "  - Stop services: docker compose down"
echo "  - Start services: docker compose up -d"
echo "  - Restart services: docker compose restart"
echo ""
print_info "Installation log saved to: $LOG_FILE"
echo ""
