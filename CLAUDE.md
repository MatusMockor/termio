# Bookio - Claude Instructions

## Project Overview
This is a Laravel application with React frontend.

## Coding Standards
**IMPORTANT**: All coding standards are defined in `.claude/coding-standards.md`. Read and follow them strictly.

Key rules:
- PHP 8.4, Laravel 12
- Use strict types and typed properties
- **No else statements** - use guard clauses
- Run `./vendor/bin/pint` after modifying PHP files
- Use Repository pattern with interfaces
- Controllers are thin - delegate to Actions
- Use DTOs for data transfer
- Form Requests must use `$this->validated('field')` with getter methods
- Type-hint all callbacks with `static function` when possible
- Use `JSON_THROW_ON_ERROR` with json_decode()

## Available Agents
Custom agents are available in `.claude/agents/`:
- `orchestrator` - Master coordinator for all tasks
- `backend-senior` - Laravel backend implementation
- `frontend-senior` - React/TypeScript frontend
- `php-reviewer` - PHP code review
- `smart-reviewer` - Intelligent code review dispatcher
- `architect` - Technical specifications from PRDs
- `product-manager` - PRD creation

## Architecture
```
app/
├── Actions/          # Business operations (one per use case)
├── Services/         # Stateless logic (calculations, validations)
├── Repositories/     # Data access abstraction
├── Models/           # Eloquent models
├── Http/
│   ├── Controllers/  # Thin controllers
│   ├── Requests/     # Form validation with getters
│   └── Resources/    # API responses
```

## Testing
- Run tests: `./vendor/bin/sail test`
- Use `fake()` helper, not hardcoded values
- Use `route()` helper for URLs
- Feature tests in `tests/Feature/`
- Unit tests in `tests/Unit/`
