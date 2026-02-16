#!/bin/bash

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Print colored messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Banner
clear
echo -e "${RED}"
echo "╔══════════════════════════════════════════════════════╗"
echo "║     MeowVPN Rollback Script v1.0                   ║"
echo "║     Restore from Backup                            ║"
echo "╚══════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# Get project directory
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$PROJECT_DIR/backups"

if [ ! -d "$BACKUP_DIR" ]; then
    print_error "Backup directory not found: $BACKUP_DIR"
    exit 1
fi

# List available backups
print_info "Available backups:"
echo ""
BACKUPS=($(ls -td "$BACKUP_DIR"/backup_* 2>/dev/null | head -10))
if [ ${#BACKUPS[@]} -eq 0 ]; then
    print_error "No backups found"
    exit 1
fi

for i in "${!BACKUPS[@]}"; do
    BACKUP_NAME=$(basename "${BACKUPS[$i]}")
    BACKUP_DATE=$(stat -c %y "${BACKUPS[$i]}" 2>/dev/null | cut -d' ' -f1,2 | cut -d'.' -f1)
    echo "  $((i+1)). $BACKUP_NAME (${BACKUP_DATE})"
done

echo ""
read -p "Select backup number to restore (1-${#BACKUPS[@]}): " SELECTION

if ! [[ "$SELECTION" =~ ^[0-9]+$ ]] || [ "$SELECTION" -lt 1 ] || [ "$SELECTION" -gt ${#BACKUPS[@]} ]; then
    print_error "Invalid selection"
    exit 1
fi

SELECTED_BACKUP="${BACKUPS[$((SELECTION-1))]}"
print_info "Selected backup: $(basename "$SELECTED_BACKUP")"

# Confirm
echo ""
read -p "Are you sure you want to restore this backup? This will overwrite current data! (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    print_info "Rollback cancelled"
    exit 0
fi

# Stop services
print_info "Stopping services..."
cd "$PROJECT_DIR"
docker compose down >> /dev/null 2>&1 || true

# Restore database
if [ -f "$SELECTED_BACKUP/database.sql.gz" ]; then
    print_info "Restoring database..."
    docker compose up -d postgres >> /dev/null 2>&1
    sleep 5
    gunzip -c "$SELECTED_BACKUP/database.sql.gz" | docker compose exec -T postgres psql -U meowvpn meowvpn
    print_success "Database restored"
fi

# Restore .env files
if [ -f "$SELECTED_BACKUP/.env.root" ]; then
    print_info "Restoring root .env..."
    cp "$SELECTED_BACKUP/.env.root" "$PROJECT_DIR/.env"
    print_success "Root .env restored"
fi

if [ -f "$SELECTED_BACKUP/.env.backend" ]; then
    print_info "Restoring backend .env..."
    cp "$SELECTED_BACKUP/.env.backend" "$PROJECT_DIR/backend/.env"
    print_success "Backend .env restored"
fi

# Restore storage
if [ -f "$SELECTED_BACKUP/storage.tar.gz" ]; then
    print_info "Restoring storage directory..."
    tar -xzf "$SELECTED_BACKUP/storage.tar.gz" -C "$PROJECT_DIR/backend/" 2>/dev/null || true
    print_success "Storage directory restored"
fi

# Restart services
print_info "Restarting services..."
docker compose up -d >> /dev/null 2>&1

echo ""
print_success "Rollback completed successfully!"
print_info "Services have been restarted with restored data"

