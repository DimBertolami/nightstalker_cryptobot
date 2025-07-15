const fs = require('fs');

// Read the file
const filePath = '/opt/lampp/htdocs/NS/assets/js/coins.js';
const content = fs.readFileSync(filePath, 'utf8');

// Function to check bracket matching
function checkBrackets(code) {
    const stack = [];
    const brackets = {
        '{': '}',
        '(': ')',
        '[': ']'
    };
    
    const lines = code.split('\n');
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        
        for (let j = 0; j < line.length; j++) {
            const char = line[j];
            
            if (brackets[char]) {
                // Opening bracket
                stack.push({
                    char: char,
                    line: i + 1,
                    col: j + 1
                });
            } else if (Object.values(brackets).includes(char)) {
                // Closing bracket
                if (stack.length === 0) {
                    console.log(`Error: Unexpected closing bracket '${char}' at line ${i + 1}, column ${j + 1}`);
                    return false;
                }
                
                const last = stack.pop();
                if (brackets[last.char] !== char) {
                    console.log(`Error: Mismatched bracket at line ${i + 1}, column ${j + 1}. Expected '${brackets[last.char]}', but found '${char}'`);
                    return false;
                }
            }
        }
    }
    
    if (stack.length > 0) {
        const last = stack.pop();
        console.log(`Error: Unclosed bracket '${last.char}' at line ${last.line}, column ${last.col}`);
        return false;
    }
    
    console.log('All brackets are properly matched!');
    return true;
}

// Check the file
checkBrackets(content);
