# Linting and Code Fixers Guide

This guide provides detailed instructions for running linting and code fixing tools in the Mail System by Katsarov Design plugin. These tools help maintain code quality and ensure consistency with WordPress coding standards.

## 📋 Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [PHP Linting & Fixing](#php-linting--fixing)
- [SCSS/CSS Building](#scsscss-building)
- [TypeScript/JavaScript Building](#typescriptjavascript-building)
- [Quick Reference](#quick-reference)
- [Troubleshooting](#troubleshooting)
- [CI/CD Integration](#cicd-integration)

## 🎯 Overview

The project uses several code quality tools to maintain high standards:

| Tool | Purpose | Language |
|------|---------|----------|
| **PHPCS** | Linting (WordPress Coding Standards) | PHP |
| **PHPCBF** | Auto-fixing code style violations | PHP |
| **Sass** | SCSS compilation to CSS | SCSS/CSS |
| **Vite + TypeScript** | Build visual editor | TypeScript/React |

### When to Run These Tools

- **Before committing**: Always run PHPCS and fix violations
- **During development**: Use watch mode for SCSS
- **Before submitting PR**: Run all checks to ensure CI passes
- **After pulling changes**: Rebuild assets if needed

## 📦 Prerequisites

### For End Users
- PHP 7.4+
- WordPress 5.0+
- No additional tools required (plugin works out of the box)

### For Developers

#### Composer (for PHP tools)
```bash
# Check if Composer is installed
composer --version

# Install Composer if needed (Linux/macOS)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### Node.js & npm (for SCSS/JS tools)
```bash
# Check if Node.js is installed
node --version
npm --version

# Install Node.js if needed (use Node 16+ recommended)
# Visit: https://nodejs.org/
```

#### Installing Dependencies

```bash
# Install PHP development dependencies
composer install

# Install Node.js dependencies (root level - for SCSS)
npm install

# Install Node.js dependencies (visual editor)
cd admin/editor
npm install
cd ../..
```

## 🐘 PHP Linting & Fixing

### PHPCS (PHP CodeSniffer)

PHPCS checks your PHP code against WordPress Coding Standards.

#### Running PHPCS

```bash
# Check all PHP files for violations
composer phpcs

# Alternative: Direct PHPUnit command
./vendor/bin/phpcs --standard=WordPress --extensions=php .
```

#### Understanding PHPCS Output

When violations are found, you'll see output like:

```
FILE: /path/to/file.php
----------------------------------------------------------------------
FOUND 5 ERRORS AND 2 WARNINGS AFFECTING 4 LINES
----------------------------------------------------------------------
 23 | ERROR   | Expected "if ( ! isset(...) )" but found "if (!isset(...))"
 45 | WARNING | Processing form data without nonce verification
----------------------------------------------------------------------
```

**Error Types:**
- **ERROR**: Must be fixed before merging
- **WARNING**: Should be reviewed and fixed if applicable

#### Common PHPCS Violations

| Violation | Wrong | Correct |
|-----------|-------|---------|
| **Yoda conditions** | `if ( $var === 'value' )` | `if ( 'value' === $var )` |
| **Spacing in conditionals** | `if(!isset($var))` | `if ( ! isset( $var ) )` |
| **Array syntax** | `array()` | `[]` (short syntax preferred) |
| **Pre-increment** | `$i++` | `++$i` |
| **Nonce verification** | Direct `$_POST` access | Check nonce first |

### PHPCBF (PHP Code Beautifier and Fixer)

PHPCBF automatically fixes many PHPCS violations.

#### Running PHPCBF

```bash
# Auto-fix all fixable violations
composer phpcbf

# Alternative: Direct command
./vendor/bin/phpcbf --standard=WordPress --extensions=php .
```

#### What PHPCBF Can Fix

✅ **Automatically fixable:**
- Spacing issues (around operators, parentheses)
- Indentation
- Line endings
- Array syntax
- Pre/post increment operators
- Some documentation formatting

❌ **Requires manual fixing:**
- Yoda conditions (logic changes)
- Security issues (nonce verification, sanitization)
- Complex code structure issues
- Missing documentation

#### Workflow

```bash
# 1. Run PHPCBF to auto-fix what's possible
composer phpcbf

# 2. Check remaining violations
composer phpcs

# 3. Manually fix remaining issues

# 4. Verify all issues are resolved
composer phpcs
```

### WordPress Coding Standards Details

The project follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):

**Key Rules:**
1. **Security First**: Always sanitize input, escape output, verify nonces
2. **Yoda Conditions**: Place constants/literals on the left side of comparisons
3. **Spacing**: Spaces inside parentheses and around operators
4. **Documentation**: PHPDoc blocks for all classes, methods, and functions
5. **Naming**: 
   - Classes: `MSKD_Class_Name`
   - Functions: `mskd_function_name()`
   - Constants: `MSKD_CONSTANT_NAME`

### PHPCompatibility (PHP Version Checking)

The project includes PHPCompatibility for checking PHP 7.4+ compatibility.

```bash
# Check PHP compatibility (not in composer scripts, run manually if needed)
./vendor/bin/phpcs -p . --standard=PHPCompatibility --runtime-set testVersion 7.4-
```

## 🎨 SCSS/CSS Building

The project uses Sass for CSS preprocessing with a modular architecture.

### Available Commands

```bash
# Development - Watch mode with source maps
npm run sass:dev          # Watch admin SCSS
npm run sass:public:dev   # Watch public SCSS
npm run watch             # Watch both admin and public

# Production - Compressed output
npm run sass:build        # Build admin CSS
npm run sass:public       # Build public CSS
npm run build             # Build both admin and public
```

### SCSS Architecture

```
admin/scss/
├── main.scss              # Entry point
├── abstracts/
│   ├── _variables.scss    # Colors, spacing, typography
│   └── _mixins.scss       # Reusable mixins
├── base/
│   └── _base.scss         # Base styles
└── components/
    ├── _dashboard.scss    # Component styles
    ├── _forms.scss
    └── ...
```

### Development Workflow

```bash
# 1. Start watch mode
npm run watch

# 2. Edit SCSS files in admin/scss/ or public/scss/

# 3. Sass automatically compiles on save
#    Output: admin/css/admin-style.css
#            public/css/public-style.css

# 4. Refresh browser to see changes
```

### SCSS Guidelines

Follow the guidelines in [`docs/scss-guidelines.md`](scss-guidelines.md):
- Use variables for colors, spacing, and typography
- Follow BEM-like naming with `mskd-` prefix
- Use mixins for repeated patterns
- Mobile-first responsive design
- Keep specificity low (max 3 levels of nesting)

### SCSS Linting

**Current Status**: No automated SCSS linter is configured.

**Manual Guidelines**: Follow [`docs/scss-guidelines.md`](scss-guidelines.md)

**Future Enhancement**: Consider adding [Stylelint](https://stylelint.io/) for automated SCSS linting:

```json
// Potential future addition to package.json
"devDependencies": {
  "stylelint": "^15.0.0",
  "stylelint-config-standard-scss": "^11.0.0"
}
```

## ⚛️ TypeScript/JavaScript Building

The visual email editor is built with React and TypeScript using Vite.

### Building the Visual Editor

```bash
# Build the visual editor
npm run build:editor

# Or manually
cd admin/editor
npm install
npm run build
cd ../..
```

This compiles the TypeScript/React code in `admin/editor/src/` and outputs to `admin/js/editor/`.

### Development Mode

```bash
cd admin/editor
npm run dev
```

This starts Vite's development server with hot module replacement.

### TypeScript Configuration

The visual editor uses TypeScript with strict mode enabled. Configuration files:
- [`admin/editor/tsconfig.json`](../admin/editor/tsconfig.json) - TypeScript config
- [`admin/editor/vite.config.ts`](../admin/editor/vite.config.ts) - Vite build config

### JavaScript/TypeScript Linting

**Current Status**: No ESLint is configured for the visual editor.

**Future Enhancement**: Consider adding ESLint for TypeScript/React:

```json
// Potential future addition to admin/editor/package.json
"devDependencies": {
  "@typescript-eslint/eslint-plugin": "^6.0.0",
  "@typescript-eslint/parser": "^6.0.0",
  "eslint": "^8.0.0",
  "eslint-plugin-react": "^7.33.0",
  "eslint-plugin-react-hooks": "^4.6.0"
}
```

## 📚 Quick Reference

### Common Commands Table

| Task | Command | Description |
|------|---------|-------------|
| **PHP Linting** | `composer phpcs` | Check PHP coding standards |
| **PHP Auto-fix** | `composer phpcbf` | Auto-fix PHP violations |
| **Run Tests** | `composer test` | Run PHPUnit tests |
| **Run Unit Tests** | `composer test:unit` | Run only unit tests |
| **Build SCSS (all)** | `npm run build` | Build admin + public CSS |
| **Build Admin SCSS** | `npm run sass:build` | Build admin CSS only |
| **Build Public SCSS** | `npm run sass:public` | Build public CSS only |
| **Watch SCSS (all)** | `npm run watch` | Watch admin + public SCSS |
| **Watch Admin SCSS** | `npm run sass:dev` | Watch admin SCSS only |
| **Build Editor** | `npm run build:editor` | Build visual editor |
| **Build Everything** | `npm run build:all` | Build SCSS + visual editor |

### Pre-Commit Checklist

Before committing code, ensure:

```bash
# 1. Auto-fix PHP issues
composer phpcbf

# 2. Check for remaining PHP issues
composer phpcs

# 3. Run tests
composer test

# 4. Build assets (if SCSS/JS changed)
npm run build:all

# 5. Stage and commit
git add .
git commit -m "feat: your commit message"
```

## 🔧 Troubleshooting

### PHPCS Issues

#### "phpcs: command not found"

**Problem**: Composer dependencies not installed.

**Solution**:
```bash
composer install
```

#### "Failed to load standard 'WordPress'"

**Problem**: WordPress Coding Standards not installed correctly.

**Solution**:
```bash
# Remove vendor directory and reinstall
rm -rf vendor/
composer install
```

#### "Cannot find autoloader"

**Problem**: Composer autoloader not generated.

**Solution**:
```bash
composer dump-autoload
```

### Sass Issues

#### "sass: command not found"

**Problem**: Node.js dependencies not installed.

**Solution**:
```bash
npm install
```

#### "Error: File to import not found"

**Problem**: SCSS import path incorrect or file missing.

**Solution**:
- Check the import path in your SCSS file
- Ensure the file exists
- Use relative paths with `@use` or `@forward`

#### Sass Watch Not Detecting Changes

**Problem**: File watcher not working properly.

**Solution**:
```bash
# Stop the watch process (Ctrl+C)
# Clear npm cache
npm cache clean --force
# Reinstall
npm install
# Try again
npm run watch
```

### Build Issues

#### "Out of memory" During Build

**Problem**: Node.js build process running out of memory.

**Solution**:
```bash
# Increase Node.js memory limit
NODE_OPTIONS="--max-old-space-size=4096" npm run build:editor
```

## 🔄 CI/CD Integration

### GitHub Actions (Future Enhancement)

While not currently implemented, here's a recommended CI workflow:

```yaml
# .github/workflows/ci.yml
name: CI

on:
  pull_request:
    branches: [ main ]
  push:
    branches: [ main ]

jobs:
  phpcs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Run PHPCS
        run: composer phpcs

  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test

  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install dependencies
        run: npm install
      - name: Build SCSS
        run: npm run build
      - name: Build editor
        run: npm run build:editor
```

### Pre-commit Hooks (Future Enhancement)

Consider adding Git pre-commit hooks to automatically run checks:

```bash
# .git/hooks/pre-commit (make executable)
#!/bin/bash

echo "Running PHPCS..."
composer phpcs
if [ $? -ne 0 ]; then
    echo "PHPCS failed. Run 'composer phpcbf' to auto-fix."
    exit 1
fi

echo "Running tests..."
composer test
if [ $? -ne 0 ]; then
    echo "Tests failed."
    exit 1
fi

echo "All checks passed!"
```

## 📖 Additional Resources

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHP CodeSniffer Documentation](https://github.com/squizlabs/PHP_CodeSniffer/wiki)
- [Sass Documentation](https://sass-lang.com/documentation)
- [Vite Documentation](https://vitejs.dev/)
- [TypeScript Documentation](https://www.typescriptlang.org/docs/)

## 🤝 Related Documentation

- [`README.md`](../README.md) - Project overview and setup
- [`CONTRIBUTING.md`](../.github/CONTRIBUTING.md) - Contribution guidelines
- [`docs/scss-guidelines.md`](scss-guidelines.md) - SCSS coding standards
- [`tests/README.md`](../tests/README.md) - Testing guide

---

**Need help?** Open an issue on GitHub or check the [Contributing Guidelines](../.github/CONTRIBUTING.md).
