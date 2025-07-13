@echo off
REM NexCart Chatbot - Windows Release Script
REM This script creates a production-ready zip file for WordPress

echo 🚀 NexCart Chatbot - Creating Release Package
echo ==============================================

REM Check if Node.js is available
node --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Node.js is required but not found
    echo Please install Node.js from https://nodejs.org/
    pause
    exit /b 1
)

REM Install dependencies if needed
if not exist node_modules (
    echo 📦 Installing dependencies...
    npm install
)

REM Get current version from package.json
for /f "tokens=*" %%i in ('node -p "require('./package.json').version"') do set VERSION=%%i
echo 📋 Current version: %VERSION%

REM Create releases directory if it doesn't exist
if not exist releases mkdir releases

REM Run the build script
echo 🔨 Building plugin package...
npm run create-zip

REM Check if zip was created successfully
set ZIP_FILE=releases\nexcart-chatbot-v%VERSION%.zip
if exist "%ZIP_FILE%" (
    echo ✅ Release package created successfully!
    echo 📦 File: %ZIP_FILE%
    echo.
    echo 🎯 Ready for deployment:
    echo    1. Upload to WordPress admin → Plugins → Add New → Upload Plugin
    echo    2. Or extract to /wp-content/plugins/ directory
    echo.
    echo 🔧 Don't forget to:
    echo    • Add GROQ_API_KEY to wp-config.php
    echo    • Configure Firebase settings if using live chat
    echo    • Test both AI and Live Support modes
) else (
    echo ❌ Failed to create release package
)

pause
