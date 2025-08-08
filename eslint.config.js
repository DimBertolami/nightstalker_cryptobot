export default [
  {
    files: ["*.js"],
    languageOptions: {
      ecmaVersion: 2021,
      sourceType: "module"
    },
    rules: {
      "no-unused-vars": "warn",
      "no-undef": "error",
      "no-extra-semi": "error",
      "no-unexpected-multiline": "error",
      "no-syntax-error": "error"
    }
  }
];
