const fs = require('fs');
const readline = require('readline');

// Function to check bracket matching
async function checkBrackets(filePath) {
    const stack = [];
    const brackets = {
        '{': '}',
        '(': ')',
        '[': ']'
    };
    
    const fileStream = fs.createReadStream(filePath);
    const rl = readline.createInterface({
        input: fileStream,
        crlfDelay: Infinity
    });
    
    let lineNumber = 0;
    for await (const line of rl) {
        lineNumber++;
        for (let j = 0; j < line.length; j++) {
            const char = line[j];
            
            if (brackets[char]) {
                // Opening bracket
                stack.push({
                    char: char,
                    line: lineNumber,
                    col: j + 1
                });
            } else if (Object.values(brackets).includes(char)) {
                // Closing bracket
                if (stack.length === 0) {
                    console.log(`Error: Unexpected closing bracket '${char}' at line ${lineNumber}, column ${j + 1}`);
                    return false;
                }
                
                const last = stack.pop();
                if (brackets[last.char] !== char) {
                    console.log(`Error: Mismatched bracket at line ${lineNumber}, column ${j + 1}. Expected '${brackets[last.char]}', but found '${char}'`);
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
const filePath = '/opt/lampp/htdocs/NS/assets/js/coins.js';
checkBrackets(filePath);
