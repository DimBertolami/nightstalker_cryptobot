const fs = require('fs');
const path = require('path');

// Read the file
const filePath = '/opt/lampp/htdocs/NS/assets/js/coins.js';
const content = fs.readFileSync(filePath, 'utf8');

// Create a backup
const backupPath = path.join(path.dirname(filePath), path.basename(filePath) + '.backup');
fs.writeFileSync(backupPath, content);
console.log(`Created backup at ${backupPath}`);

// Fix specific issues we've identified
function fixJavaScriptSyntax() {
    // Split the file into lines for easier manipulation
    const lines = content.split('\n');
    
    // Fix the buy button click handler (line 2865)
    // The issue is that it's missing a closing parenthesis
    for (let i = 2950; i < 2955; i++) {
        if (lines[i].includes('});')) {
            lines[i] = '    });';
            lines[i+1] = '});';
            break;
        }
    }
    
    // Fix the processCoinData function (line 3088)
    // The issue is that it has an extra closing brace
    for (let i = 3085; i < 3090; i++) {
        if (lines[i].includes('}}')) {
            lines[i] = '}';
            break;
        }
    }
    
    // Join the lines back together
    const fixedContent = lines.join('\n');
    
    // Write the fixed content back to the file
    fs.writeFileSync(filePath, fixedContent);
    console.log('Fixed JavaScript syntax errors in ' + filePath);
}

// Run the fix
fixJavaScriptSyntax();
