# NexCart Chatbot - Version Control & Deployment Guide

## 🚀 Quick Setup

### 1. Initialize Git Repository (Already Done)
```bash
git init
git add .
git commit -m "Initial commit: NexCart Chatbot v1.0.0 with dual-mode AI + Live Support"
```

### 2. Create Production-Ready Zip
```bash
# Windows
npm install
release.bat

# Linux/Mac
npm install
chmod +x release.sh
./release.sh
```

## 📦 Version Control Workflow

### Creating a New Version
1. **Make your changes** to the plugin files
2. **Update version number** in:
   - `package.json` → `"version": "1.0.1"`
   - `nexcart-chatbot.php` → `Version: 1.0.1`
3. **Commit changes**:
   ```bash
   git add .
   git commit -m "feat: Add new feature description"
   ```
4. **Create version tag**:
   ```bash
   git tag v1.0.1
   ```
5. **Generate release package**:
   ```bash
   npm run create-zip
   ```

### Automated Versioning
```bash
# Automatically increment patch version (1.0.0 → 1.0.1)
npm run release

# Or manually specify version
npm version 1.1.0
```

## 🏗️ Build System

### Files Included in Release Zip:
- ✅ `nexcart-chatbot.php` (Main plugin file)
- ✅ `chat-api.php` (Groq AI integration)
- ✅ `assets/` (JavaScript, CSS, images)
- ✅ `README.md` (Documentation)
- ✅ `GROQ-SETUP.md` (Setup guide)
- ✅ `DUAL-MODE-COMPLETE.md` (Feature documentation)

### Files Excluded from Release:
- ❌ `.git/` (Git repository data)
- ❌ `node_modules/` (Development dependencies)
- ❌ `package.json` (NPM configuration)
- ❌ `build-zip.js` (Build script)
- ❌ `*.bat`, `*.sh` (Build scripts)

## 📁 Directory Structure After Build
```
releases/
├── nexcart-chatbot-v1.0.0.zip
├── nexcart-chatbot-v1.0.1.zip
└── nexcart-chatbot-v1.1.0.zip

nexcart-chatbot-v1.0.0.zip contains:
nexcart-chatbot/
├── nexcart-chatbot.php
├── chat-api.php
├── assets/
│   ├── chatbot.js
│   ├── chatbot.css
│   └── firebase-config.js
├── README.md
├── GROQ-SETUP.md
└── DUAL-MODE-COMPLETE.md
```

## 🚀 Deployment to WordPress

### Method 1: WordPress Admin Upload
1. Go to WordPress Admin → Plugins → Add New
2. Click "Upload Plugin"
3. Select your `nexcart-chatbot-v1.0.0.zip` file
4. Click "Install Now"
5. Activate the plugin

### Method 2: Manual Installation
1. Extract zip file to `/wp-content/plugins/`
2. Go to WordPress Admin → Plugins
3. Activate "NexCart Chatbot"

### Method 3: FTP Upload
1. Extract zip to local folder
2. Upload `nexcart-chatbot/` folder to `/wp-content/plugins/`
3. Activate in WordPress admin

## 🔧 Post-Installation Setup
1. **Add API Key** to `wp-config.php`:
   ```php
   define('GROQ_API_KEY', 'your_groq_api_key_here');
   ```
2. **Configure Firebase** (optional, for live chat)
3. **Test dual-mode functionality**
4. **Configure support business hours** if needed

## 🐙 GitHub Integration (Optional)

### Create GitHub Repository
```bash
# Create repo on GitHub, then:
git remote add origin https://github.com/yourusername/nexcart-chatbot.git
git branch -M main
git push -u origin main
git push --tags
```

### GitHub Actions for Auto-Release (Advanced)
Create `.github/workflows/release.yml`:
```yaml
name: Create Release
on:
  push:
    tags:
      - 'v*'
jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm install
      - run: npm run create-zip
      - uses: softprops/action-gh-release@v1
        with:
          files: releases/*.zip
```

## 📋 Release Checklist
- [ ] Update version in `package.json`
- [ ] Update version in `nexcart-chatbot.php`
- [ ] Test all functionality locally
- [ ] Run `npm run create-zip`
- [ ] Test zip installation on staging site
- [ ] Commit and tag version
- [ ] Push to GitHub (if using)
- [ ] Deploy to production

## 🔄 Common Commands
```bash
# Check current version
node -p "require('./package.json').version"

# Create development zip
npm run create-zip

# Quick release (patch version)
npm run release

# Install dependencies
npm install

# Check git status
git status

# View commit history
git log --oneline
```

## 🆘 Troubleshooting

### Build Issues
- Ensure Node.js is installed
- Run `npm install` before building
- Check file permissions on Unix systems

### WordPress Issues
- Verify plugin folder structure
- Check WordPress error logs
- Ensure minimum PHP/WordPress versions

### Git Issues
- Initialize repository: `git init`
- Check remote: `git remote -v`
- Fix commit author: `git config user.name "Your Name"`
