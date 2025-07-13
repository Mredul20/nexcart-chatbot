const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

// Read package.json to get version
const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const version = packageJson.version;

// Create releases directory if it doesn't exist
const releasesDir = path.join(__dirname, 'releases');
if (!fs.existsSync(releasesDir)) {
    fs.mkdirSync(releasesDir);
}

// Create zip filename with version
const zipFilename = `nexcart-chatbot-v${version}.zip`;
const zipPath = path.join(releasesDir, zipFilename);

// Create a file to stream archive data to
const output = fs.createWriteStream(zipPath);
const archive = archiver('zip', {
    zlib: { level: 9 } // Sets the compression level
});

// Listen for all archive data to be written
output.on('close', function() {
    console.log(`✅ Plugin zip created: ${zipFilename}`);
    console.log(`📦 Archive size: ${archive.pointer()} bytes`);
    console.log(`📍 Location: ${zipPath}`);
    console.log(`🚀 Ready for WordPress installation!`);
});

// Listen for warnings (e.g., stat failures and other non-blocking errors)
archive.on('warning', function(err) {
    if (err.code === 'ENOENT') {
        console.warn('Warning:', err);
    } else {
        throw err;
    }
});

// Listen for errors
archive.on('error', function(err) {
    throw err;
});

// Pipe archive data to the file
archive.pipe(output);

// Define files/folders to include in the zip
const filesToInclude = [
    'nexcart-chatbot.php',
    'chat-api.php',
    'assets/',
    'README.md',
    'GROQ-SETUP.md',
    'DUAL-MODE-COMPLETE.md'
];

console.log('🔨 Building NexCart Chatbot Plugin...');
console.log(`📋 Version: ${version}`);

// Add files to the archive
filesToInclude.forEach(file => {
    const fullPath = path.join(__dirname, file);
    
    if (fs.existsSync(fullPath)) {
        const stats = fs.statSync(fullPath);
        
        if (stats.isDirectory()) {
            // Add directory
            archive.directory(fullPath, `nexcart-chatbot/${file}`);
            console.log(`📁 Added directory: ${file}`);
        } else {
            // Add file
            archive.file(fullPath, { name: `nexcart-chatbot/${file}` });
            console.log(`📄 Added file: ${file}`);
        }
    } else {
        console.warn(`⚠️  File not found: ${file}`);
    }
});

// Finalize the archive
archive.finalize();
