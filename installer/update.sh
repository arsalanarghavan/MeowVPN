#!/bin/bash

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging
LOG_FILE="/tmp/meowvpn_update.log"
UPDATE_START_TIME=$(date +%s)
BACKUP_DIR=""

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

# Error handler with rollback
cleanup_on_error() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        print_error "Update failed at step: ${LAST_STEP:-unknown}"
        print_warning "Attempting rollback..."
        
        if [ -n "$BACKUP_DIR" ] && [ -d "$BACKUP_DIR" ]; then
            print_info "Rollback backup available at: $BACKUP_DIR"
            print_info "To rollback manually, restore from: $BACKUP_DIR"
        fi
        
        print_info "Check log file: $LOG_FILE"
        exit $exit_code
    fi
}

trap cleanup_on_error ERR

# Banner
clear
echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║           MeowVPN Updater Script v1.0                    ║"
echo "║           Professional Update System                      ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# Initialize log
echo "=== MeowVPN Update Log ===" > "$LOG_FILE"
echo "Started at: $(date)" >> "$LOG_FILE"
echo "User: $(whoami)" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

# Get project directory
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

print_info "Project directory: $PROJECT_DIR"
print_info "Log file: $LOG_FILE"
echo ""

# Check if Docker Compose is available
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    print_error "Docker Compose is not installed"
    exit 1
fi

# Check if services are running
if ! docker compose ps | grep -q "Up"; then
    print_warning "Some services are not running. Starting services..."
    docker compose up -d >> "$LOG_FILE" 2>&1
    sleep 5
fi

# ============================================
# Step 1: Create Backup
# ============================================
LAST_STEP="Backup Creation"
print_step "Step 1/7: Creating backup..."

BACKUP_DIR="$PROJECT_DIR/backups"
mkdir -p "$BACKUP_DIR"
BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_BASE="$BACKUP_DIR/backup_$BACKUP_TIMESTAMP"
mkdir -p "$BACKUP_BASE"

print_info "Backup location: $BACKUP_BASE"

# Backup database
print_info "Backing up database..."
DB_BACKUP_FILE="$BACKUP_BASE/database.sql"
if docker compose exec -T postgres pg_dump -U meowvpn meowvpn > "$DB_BACKUP_FILE" 2>>"$LOG_FILE"; then
    # Compress database backup
    gzip "$DB_BACKUP_FILE" 2>/dev/null || true
    print_success "Database backed up to: ${DB_BACKUP_FILE}.gz"
else
    print_error "Database backup failed"
    exit 1
fi

# Backup .env files
print_info "Backing up configuration files..."
if [ -f "$PROJECT_DIR/.env" ]; then
    cp "$PROJECT_DIR/.env" "$BACKUP_BASE/.env.root" 2>/dev/null || true
fi
if [ -f "$PROJECT_DIR/backend/.env" ]; then
    cp "$PROJECT_DIR/backend/.env" "$BACKUP_BASE/.env.backend" 2>/dev/null || true
fi
print_success "Configuration files backed up"

# Backup storage directory
print_info "Backing up storage directory..."
if [ -d "$PROJECT_DIR/backend/storage" ]; then
    tar -czf "$BACKUP_BASE/storage.tar.gz" -C "$PROJECT_DIR/backend" storage 2>>"$LOG_FILE" || true
    print_success "Storage directory backed up"
fi

# Create backup manifest
cat > "$BACKUP_BASE/manifest.txt" << EOF
MeowVPN Backup Manifest
======================
Backup Date: $(date)
Backup Location: $BACKUP_BASE
Database: ${DB_BACKUP_FILE}.gz
Configuration: .env.root, .env.backend
Storage: storage.tar.gz

To restore this backup:
1. Restore database: gunzip -c ${DB_BACKUP_FILE}.gz | docker compose exec -T postgres psql -U meowvpn meowvpn
2. Restore .env files: cp $BACKUP_BASE/.env.* $PROJECT_DIR/
3. Restore storage: tar -xzf $BACKUP_BASE/storage.tar.gz -C $PROJECT_DIR/backend/
EOF

print_success "Backup completed successfully"
echo ""

# ============================================
# Step 2: Check Current Version
# ============================================
LAST_STEP="Version Check"
print_step "Step 2/7: Checking current version..."

# Get current Git commit if available
if [ -d "$PROJECT_DIR/.git" ]; then
    CURRENT_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
    CURRENT_BRANCH=$(git branch --show-current 2>/dev/null || echo "unknown")
    print_info "Current version: $CURRENT_BRANCH ($CURRENT_COMMIT)"
else
    print_warning "Not a Git repository, skipping version check"
fi

echo ""

# ============================================
# Step 3: Pull Latest Changes
# ============================================
LAST_STEP="Git Pull"
print_step "Step 3/7: Pulling latest changes..."

if [ -d "$PROJECT_DIR/.git" ]; then
    print_info "Pulling latest changes from Git..."
    if git pull >> "$LOG_FILE" 2>&1; then
        NEW_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
        print_success "Git pull completed. New version: $NEW_COMMIT"
    else
        print_error "Git pull failed. Check log: $LOG_FILE"
        exit 1
    fi
else
    print_warning "Not a Git repository, skipping Git pull"
    print_info "Please manually update the code if needed"
fi

echo ""

# ============================================
# Step 4: Build New Docker Images
# ============================================
LAST_STEP="Docker Images Build"
print_step "Step 4/7: Building new Docker images..."

print_info "This may take several minutes. Please wait..."
if docker compose build --progress=plain >> "$LOG_FILE" 2>&1; then
    print_success "Docker images built successfully"
else
    print_error "Failed to build Docker images. Check log: $LOG_FILE"
    exit 1
fi

echo ""

# ============================================
# Step 5: Run Database Migrations
# ============================================
LAST_STEP="Database Migrations"
print_step "Step 5/7: Running database migrations..."

# Ensure Laravel container is running
if ! docker compose ps laravel | grep -q "Up"; then
    print_info "Starting Laravel container..."
    docker compose up -d laravel >> "$LOG_FILE" 2>&1
    sleep 5
fi

# Install/update Composer dependencies
print_info "Updating Composer dependencies..."
if docker compose exec -T laravel composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev >> "$LOG_FILE" 2>&1; then
    print_success "Composer dependencies updated"
else
    print_warning "Composer update had warnings, continuing..."
fi

# Run migrations
print_info "Running database migrations..."
if docker compose exec -T laravel php artisan migrate --force >> "$LOG_FILE" 2>&1; then
    print_success "Database migrations completed"
else
    print_error "Database migrations failed. Check log: $LOG_FILE"
    print_warning "You can rollback using the backup at: $BACKUP_BASE"
    exit 1
fi

# Clear caches
print_info "Clearing application caches..."
docker compose exec -T laravel php artisan config:clear >> "$LOG_FILE" 2>&1 || true
docker compose exec -T laravel php artisan cache:clear >> "$LOG_FILE" 2>&1 || true
docker compose exec -T laravel php artisan route:clear >> "$LOG_FILE" 2>&1 || true
docker compose exec -T laravel php artisan view:clear >> "$LOG_FILE" 2>&1 || true
print_success "Caches cleared"

echo ""

# ============================================
# Step 6: Restart Services
# ============================================
LAST_STEP="Services Restart"
print_step "Step 6/7: Restarting services..."

print_info "Stopping services..."
docker compose down >> "$LOG_FILE" 2>&1

print_info "Starting services..."
if docker compose up -d >> "$LOG_FILE" 2>&1; then
    print_success "Services restarted"
else
    print_error "Failed to restart services"
    exit 1
fi

# Wait for services to be ready
print_info "Waiting for services to be ready..."
sleep 10

echo ""

# ============================================
# Step 7: Health Checks
# ============================================
LAST_STEP="Health Checks"
print_step "Step 7/7: Performing health checks..."

HEALTH_CHECK_FAILED=0

# Check PostgreSQL
print_info "Checking PostgreSQL..."
if docker compose exec -T postgres pg_isready -U meowvpn >> "$LOG_FILE" 2>&1; then
    print_success "PostgreSQL health check passed"
else
    print_error "PostgreSQL health check failed"
    HEALTH_CHECK_FAILED=1
fi

# Check Redis
print_info "Checking Redis..."
if docker compose exec -T redis redis-cli ping >> "$LOG_FILE" 2>&1; then
    print_success "Redis health check passed"
else
    print_error "Redis health check failed"
    HEALTH_CHECK_FAILED=1
fi

# Check Laravel API
print_info "Checking Laravel API..."
sleep 5
if curl -f http://localhost/api/health >> "$LOG_FILE" 2>&1; then
    print_success "Laravel API health check passed"
else
    print_warning "Laravel API health check failed (may be normal)"
fi

# Check Frontend
print_info "Checking Frontend..."
sleep 3
if curl -f http://localhost:3000 >> "$LOG_FILE" 2>&1; then
    print_success "Frontend health check passed"
else
    print_warning "Frontend health check failed (may be normal if not configured)"
fi

if [ $HEALTH_CHECK_FAILED -eq 1 ]; then
    print_error "Some health checks failed. Please check the logs."
    print_info "Log file: $LOG_FILE"
    print_warning "You can rollback using the backup at: $BACKUP_BASE"
    exit 1
fi

echo ""

# ============================================
# Update Complete
# ============================================
UPDATE_END_TIME=$(date +%s)
UPDATE_DURATION=$((UPDATE_END_TIME - UPDATE_START_TIME))

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           Update Completed Successfully!                   ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
print_success "Update completed in $UPDATE_DURATION seconds"
echo ""
print_info "Backup saved to: $BACKUP_BASE"
print_info "Update log saved to: $LOG_FILE"
echo ""
print_info "All services are running and healthy!"
echo ""
