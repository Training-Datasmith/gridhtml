# Architecture: gridhtml

## Purpose
A PrestaShop module that displays a configurable product grid on the homepage or category pages. Provides HTML grid output with template-driven rendering.

## Directory Structure
```
gridhtml.php          # Main module class — hooks into PrestaShop lifecycle
index.php             # Security guard (empty — prevents direct directory browsing)
translations/         # i18n translation files
views/                # Smarty templates for front-office rendering
tests/                # PHPStan static analysis configuration
```

## Key Design Decisions
- Follows the PrestaShop module convention: one main PHP class inheriting from `Module`, using hook methods (`hookDisplayHome`, etc.) for integration.
- Templates are rendered via PrestaShop's Smarty engine; no custom template engine is introduced.
- All display logic is separated into `views/templates/` to allow theme overrides.

## Extension Points
- Override templates in the active theme's `modules/gridhtml/` directory to customize the front-office look.
- Add new hooks by implementing additional `hook*` methods in the main class.

## Dependency Flow
```
PrestaShop Core
  └─ gridhtml (Module)
       ├─ Hook callbacks → view data preparation
       └─ views/templates/ → Smarty rendering → HTML output
```
