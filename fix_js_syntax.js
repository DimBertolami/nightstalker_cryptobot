const fs = require('fs');

// Read the file
const filePath = '/opt/lampp/htdocs/NS/assets/js/coins.js';
const content = fs.readFileSync(filePath, 'utf8');

// Fix common JavaScript syntax errors
function fixJavaScriptSyntax(code) {
    let fixed = code;
    
    // Fix line 2953 - buy button click handler
    fixed = fixed.replace(/\}\s*\}\s*\)\s*;?\s*\}\s*\n\s*\/\/ Sell button/g, 
                         '}\n    });\n});\n\n// Sell');
    
    // Fix line 3088 - processCoinData function
    fixed = fixed.replace(/\}\}\(\)/g, '}');
    
    // Write the fixed content back to the file
    fs.writeFileSync(filePath, fixed);
    console.log('Fixed JavaScript syntax errors in ' + filePath);
}

// Fix the file
fixJavaScriptSyntax(content);
