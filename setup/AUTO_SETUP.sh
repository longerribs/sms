#!/bin/bash

# clayon/setup/AUTO_SETUP.sh
# 
# Automated setup script for Clayon SMS Platform
# Usage: bash AUTO_SETUP.sh

set -e  # Exit on error

echo "=========================================="
echo "  Clayon SMS Platform - Auto Setup"
echo "=========================================="
echo ""

# Check PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP not found. Please install PHP 7.4 or higher."
    exit 1
fi

echo "✅ PHP detected: $(php -v | head -n 1)"
echo ""

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"

echo "Project path: $PROJECT_ROOT"
echo ""

# Check if run-all-setup.php exists
if [ ! -f "$SCRIPT_DIR/run-all-setup.php" ]; then
    echo "❌ run-all-setup.php not found"
    exit 1
fi

echo "Running setup..."
echo "=========================================="
echo ""

# Execute setup
php "$SCRIPT_DIR/run-all-setup.php"

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Save the Admin API Key displayed above"
echo "2. Update TALKSASA_API_KEY in clayon/.env2"
echo "3. Add cron worker: * * * * * php $PROJECT_ROOT/clayon/src/Worker.php"
echo "4. Access dashboard: http://localhost/clayon/pages/login.html"
echo ""
echo "For more info: http://localhost/clayon/QUICK_START.php"
echo ""
