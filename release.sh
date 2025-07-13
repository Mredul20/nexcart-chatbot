#!/bin/bash

# NexCart Chatbot - Quick Release Script
# This script creates a production-ready zip file for WordPress

echo "🚀 NexCart Chatbot - Creating Release Package"
echo "=============================================="

# Get current version from package.json
VERSION=$(node -p "require('./package.json').version")
echo "📋 Current version: $VERSION"

# Create releases directory if it doesn't exist
mkdir -p releases

# Run the build script
echo "🔨 Building plugin package..."
npm run create-zip

# Check if zip was created successfully
ZIP_FILE="releases/nexcart-chatbot-v$VERSION.zip"
if [ -f "$ZIP_FILE" ]; then
    echo "✅ Release package created successfully!"
    echo "📦 File: $ZIP_FILE"
    echo ""
    echo "🎯 Ready for deployment:"
    echo "   1. Upload to WordPress admin → Plugins → Add New → Upload Plugin"
    echo "   2. Or extract to /wp-content/plugins/ directory"
    echo ""
    echo "🔧 Don't forget to:"
    echo "   • Add GROQ_API_KEY to wp-config.php"
    echo "   • Configure Firebase settings if using live chat"
    echo "   • Test both AI and Live Support modes"
else
    echo "❌ Failed to create release package"
    exit 1
fi
