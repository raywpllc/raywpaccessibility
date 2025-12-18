#!/bin/bash

# RayWP Accessibility Plugin - Release ZIP Creator
# This script creates a clean zip file for WordPress plugin distribution

# Set plugin directory and version info
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="raywp-accessibility"
TEMP_DIR="/tmp/${PLUGIN_NAME}-release"
OUTPUT_DIR="$(dirname "$PLUGIN_DIR")"

# Get version from main plugin file
VERSION=$(grep -o "Version: [0-9.]*" "${PLUGIN_DIR}/raywp-accessibility.php" | cut -d' ' -f2)
ZIP_NAME="${PLUGIN_NAME}-v${VERSION}.zip"

echo "Creating release ZIP for RayWP Accessibility v${VERSION}..."

# Clean up any existing temp directory
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR/$PLUGIN_NAME"

# Copy files, excluding unwanted items
echo "Copying plugin files..."
rsync -av --exclude-from=- "$PLUGIN_DIR/" "$TEMP_DIR/$PLUGIN_NAME/" << 'EOF'
.git/
.gitignore
.DS_Store
.claude/
CLAUDE.md
BUILD-INSTRUCTIONS.md
tasks/
test-form.html
*.log
*.tmp
*~
.playwright-mcp/
raywp-accessibility
node_modules/
*.zip
*.dev
.vscode/
.idea/
create-release-zip.sh
EOF

# Remove any empty directories
find "$TEMP_DIR/$PLUGIN_NAME" -type d -empty -delete

# Create the zip file on Desktop
cd "$TEMP_DIR"
echo "Creating ZIP file: $OUTPUT_DIR/$ZIP_NAME"
zip -r "$OUTPUT_DIR/$ZIP_NAME" "$PLUGIN_NAME/" -x "*.DS_Store" "__MACOSX/*"

# Get file size
FILE_SIZE=$(du -h "$OUTPUT_DIR/$ZIP_NAME" | cut -f1)

echo "✅ Release ZIP created successfully!"
echo "📁 Location: $OUTPUT_DIR/$ZIP_NAME"
echo "📦 Size: $FILE_SIZE"

# Clean up temp directory
rm -rf "$TEMP_DIR"

# Check if under 10MB
SIZE_BYTES=$(stat -f%z "$OUTPUT_DIR/$ZIP_NAME" 2>/dev/null || stat -c%s "$OUTPUT_DIR/$ZIP_NAME")
if [ $SIZE_BYTES -gt 10485760 ]; then
    echo "⚠️  Warning: File size is over 10MB limit"
else
    echo "✅ File size is under 10MB limit"
fi

echo "🚀 Ready for WordPress plugin distribution!"