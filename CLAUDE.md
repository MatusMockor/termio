# Bookio - Claude Instructions

## Project Overview
Laravel 12 + React SaaS application for booking management.

## Available Agents
Custom agents in `.claude/agents/`:
- `orchestrator` - Master coordinator for all tasks
- `backend-senior` - Laravel backend implementation
- `frontend-senior` - React/TypeScript frontend
- `php-reviewer` - PHP code review
- `smart-reviewer` - Intelligent code review dispatcher
- `architect` - Technical specifications from PRDs
- `product-manager` - PRD creation

---

# Coding Standards

## 1. Language & Framework

* PHP **8.4**, Laravel **12**
* Use **strict types** and **typed properties**
* Follow **SOLID principles**
* Run `./vendor/bin/pint` after modifying PHP files

## 2. Code Style

* **Avoid `else` statements** - use guard clauses
* For null checks, use `if (!$var)`
* Add comments only for exceptions and specific business logic
* **Always type-hint parameters in callbacks**:

```php
// Good
Collection::make($data)->map(static function (User $user): array {
    return $user->toArray();
});

// Bad
array_map(function ($item) {
    return $item['name'];
}, $items);
```

* **Use `static function` for callbacks** when not accessing `$this`
* **Always use `JSON_THROW_ON_ERROR`** with `json_decode()`:

```php
$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
```

## 3. Architecture

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

### Rules
* Avoid `DB::` facade - use model or repository methods
* Controllers are **thin** - only gather inputs and call Actions
* Use **FormRequest** classes for validation
* Use **DTOs** to transfer data between layers
* Each Repository and Service has an **interface** (without `Interface` suffix)
* Register bindings in `AppServiceProvider`:

```php
$this->app->bind(UserRepositoryContract::class, EloquentUserRepository::class);
```

## 4. Form Requests

* **Always use `$this->validated('field')`** instead of `$this->input('field')`
* Create **getter methods** for all validated fields with explicit return types:

```php
final class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:20',
        ];
    }

    public function getFirstName(): string
    {
        return $this->validated('first_name');
    }

    public function getEmail(): string
    {
        return $this->validated('email');
    }

    public function getPhone(): ?string
    {
        return $this->validated('phone');
    }
}
```

## 5. Actions

* Located in `app/Actions/{Domain}`
* Naming: `[Domain][Object][Verb]Action` (e.g., `OrderCreateAction`)
* Each Action handles **one business operation**
* Main method: `handle()`
* Limit dependencies to 8

```php
final class OrderCreateAction
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly OrderConditionService $conditions,
    ) {}

    public function handle(OrderCreateDTO $dto, int $userId): Order
    {
        $this->conditions->validate($dto);

        return DB::transaction(function () use ($dto, $userId) {
            return $this->orders->create($dto, $userId);
        });
    }
}
```

## 6. Services

* Located in `app/Services/{Domain}`
* Naming: `[Domain][Purpose]Service`
* **Stateless**, no side effects
* Handles calculations, validations, API calls, formatting
* Limit dependencies to 5

## 7. Models

* All models must have **PHPDoc `@property` annotations** for all database columns
* Include proper types: `int`, `string`, `bool`, `float`, `Carbon`, `array`, etc.
* Mark nullable properties with `|null`
* Include relationships as `@property-read`

```php
/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $email
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read Tenant $tenant
 * @property-read Collection<int, Appointment> $appointments
 */
final class Client extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
}
```

## 8. Testing

* Run tests: `./vendor/bin/sail test`
* Each model has a **Factory**
* Use `fake()` instead of hardcoded values
* Use `route()` helper for URLs
* Use `Model::class` in `assertDatabaseHas()`:

```php
$this->assertDatabaseHas(Order::class, ['status' => 'pending']);
```

* Test names must clearly describe what they verify
* Tests should follow real application flow - make requests to endpoints

## 9. Design Patterns

Apply patterns where appropriate:
* **Repository** - data access abstraction
* **Strategy** - interchangeable algorithms
* **Factory** - complex object creation
* **Observer** - event handling (Laravel events/observers)
* **Command** - encapsulating actions (Laravel Jobs/Actions)

Don't force patterns - use them when they genuinely solve a problem.

## 10. Task Completion Checklist

- [ ] Pint ran successfully
- [ ] Tests passed (`sail test`)
- [ ] Code follows coding standards
- [ ] SOLID principles applied
- [ ] Actions & Services follow naming standards
- [ ] DTOs and FormRequests are used
- [ ] No `else`, no `DB::`, no magic numbers
- [ ] Design patterns applied where beneficial
