#!/bin/bash
#
# Creates a release zip for the Mail System by Katsarov Design plugin.
# Usage: ./bin/create-release.sh <version>
# Example: ./bin/create-release.sh 1.0.0
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the plugin directory (parent of bin/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_NAME="mail-system"

# Check if version argument is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Version argument is required.${NC}"
    echo "Usage: $0 <version>"
    echo "Example: $0 1.0.0"
    exit 1
fi

VERSION="$1"

# Validate version format (semver)
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}Error: Invalid version format. Please use semantic versioning (e.g., 1.0.0)${NC}"
    exit 1
fi

# Output zip file path (in parent directory of plugin)
OUTPUT_DIR="$(dirname "$PLUGIN_DIR")"
ZIP_FILE="${OUTPUT_DIR}/${PLUGIN_NAME}-v${VERSION}.zip"

echo -e "${YELLOW}Creating release v${VERSION} for ${PLUGIN_NAME}...${NC}"

# Build CSS from SCSS (required since CSS is gitignored)
echo -e "${YELLOW}Building CSS from SCSS...${NC}"
cd "$PLUGIN_DIR"
if [ -f "package.json" ]; then
    if command -v npm &> /dev/null; then
        npm run build
        echo -e "${GREEN}✓ CSS built successfully${NC}"
    else
        echo -e "${RED}Error: npm is required to build CSS. Please install Node.js.${NC}"
        exit 1
    fi
else
    echo -e "${RED}Error: package.json not found.${NC}"
    exit 1
fi

# Build Visual Email Editor (if editor directory exists)
if [ -d "$PLUGIN_DIR/admin/editor" ] && [ -f "$PLUGIN_DIR/admin/editor/package.json" ]; then
    echo -e "${YELLOW}Building Visual Email Editor...${NC}"
    cd "$PLUGIN_DIR/admin/editor"
    npm install --silent
    npm run build
    echo -e "${GREEN}✓ Visual Editor built successfully${NC}"
    
    # Clean up node_modules after build
    echo -e "${YELLOW}Cleaning up editor node_modules...${NC}"
    rm -rf "$PLUGIN_DIR/admin/editor/node_modules"
    echo -e "${GREEN}✓ Editor node_modules cleaned${NC}"
    cd "$PLUGIN_DIR"
fi

# Check if zip file already exists
if [ -f "$ZIP_FILE" ]; then
    echo -e "${YELLOW}Warning: ${ZIP_FILE} already exists. Overwriting...${NC}"
    rm "$ZIP_FILE"
fi

# Create the zip file, excluding dev files
cd "$OUTPUT_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_NAME" \
    -x "${PLUGIN_NAME}/composer.json" \
    -x "${PLUGIN_NAME}/composer.lock" \
    -x "${PLUGIN_NAME}/vendor/*" \
    -x "${PLUGIN_NAME}/node_modules/*" \
    -x "${PLUGIN_NAME}/package.json" \
    -x "${PLUGIN_NAME}/package-lock.json" \
    -x "${PLUGIN_NAME}/tests/*" \
    -x "${PLUGIN_NAME}/.git/*" \
    -x "${PLUGIN_NAME}/.github/*" \
    -x "${PLUGIN_NAME}/.gitignore" \
    -x "${PLUGIN_NAME}/docs/*" \
    -x "${PLUGIN_NAME}/bin/*" \
    -x "${PLUGIN_NAME}/phpcs.xml" \
    -x "${PLUGIN_NAME}/phpcs.xml.dist" \
    -x "${PLUGIN_NAME}/.phpcs.xml" \
    -x "${PLUGIN_NAME}/.phpcs.xml.dist" \
    -x "${PLUGIN_NAME}/admin/scss/*" \
    -x "${PLUGIN_NAME}/public/scss/*" \
    -x "${PLUGIN_NAME}/admin/editor/node_modules/*" \
    -x "${PLUGIN_NAME}/admin/editor/src/*" \
    -x "${PLUGIN_NAME}/admin/editor/package.json" \
    -x "${PLUGIN_NAME}/admin/editor/package-lock.json" \
    -x "${PLUGIN_NAME}/admin/editor/tsconfig.json" \
    -x "${PLUGIN_NAME}/admin/editor/tsconfig.node.json" \
    -x "${PLUGIN_NAME}/admin/editor/vite.config.ts" \
    > /dev/null

# Get file size
FILE_SIZE=$(ls -lh "$ZIP_FILE" | awk '{print $5}')

echo -e "${GREEN}✓ Release created successfully!${NC}"
echo -e "  File: ${ZIP_FILE}"
echo -e "  Size: ${FILE_SIZE}"
