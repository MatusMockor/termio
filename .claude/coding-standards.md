Senior Laravel Developer (20+ years experience) | Expert in Laravel, PHP, PHPUnit, React, Tailwind CSS | Design Patterns Specialist

---

# Laravel Coding Standards (v2.1 – Unified & AI-friendly)

## 1. Coding & Language Standards

* PHP **v8.4**
* Laravel **v12**
* Use **strict types** and **typed properties**
* Follow **SOLID principles**
* **Apply design patterns where appropriate** – I am an expert in design patterns and expect their proper use
* Run `./vendor/bin/pint` after modifying files
* Each Repository and Service has an **interface** (without `Interface` suffix)
* Interface aliases in `AppServiceProvider` end with `Contract`
* Register bindings:

  ```php
  $this->app->bind(UserRepositoryContract::class, EloquentUserRepository::class);
  ```
* **Avoid `else` statements**; use guard clauses
* For null checks, use `if (!$var)`
* Add comments only for exceptions and specific business logic
* **Always type-hint parameters in anonymous functions and callbacks**:

  ```php
  // ✅ Good
  array_map(function (array $item): string {
      return $item['name'];
  }, $items);

  Collection::make($data)->map(function (User $user): array {
      return $user->toArray();
  });

  // ❌ Bad
  array_map(function ($item) {
      return $item['name'];
  }, $items);
  ```
* **Use `static function` for callbacks when possible** (when not accessing `$this`):

  ```php
  // ✅ Good - callback doesn't need $this
  array_map(static function (array $item): string {
      return $item['name'];
  }, $items);

  Collection::make($data)->filter(static function (int $value): bool {
      return $value > 10;
  });

  // ✅ Also good - needs $this
  $users->map(function (User $user): array {
      return $this->transformer->transform($user);
  });

  // ❌ Bad - could be static
  array_map(function (array $item): string {
      return $item['name'];
  }, $items);
  ```
* **Always use `JSON_THROW_ON_ERROR` flag with `json_decode()`**:

  ```php
  // ✅ Good
  $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

  $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

  // ❌ Bad
  $data = json_decode($json, true);

  $data = json_decode($json);
  ```

## 2. Design Patterns & Best Practices

### 2.1 Core Principles

* **I am an expert in design patterns** – leverage this expertise
* Apply appropriate patterns to solve recurring problems elegantly
* Common patterns in use:
  * **Repository Pattern** – data access abstraction
  * **Strategy Pattern** – interchangeable algorithms (e.g., payment methods, notification channels)
  * **Factory Pattern** – complex object creation
  * **Observer Pattern** – event handling (Laravel events/observers)
  * **Decorator Pattern** – extending functionality without inheritance
  * **Chain of Responsibility** – request processing pipelines
  * **Command Pattern** – encapsulating actions (Laravel Jobs/Actions)
  * **Adapter Pattern** – third-party API integration
  * **Builder Pattern** – complex DTO or query construction

### 2.2 When to Apply Patterns

* **Don't force patterns** – use them when they genuinely solve a problem
* **Prefer composition over inheritance**
* **Keep it simple** – pattern should reduce complexity, not add it
* **Document pattern choice** – brief comment explaining why a pattern was used

**Example – Strategy Pattern for Notifications:**

```php
interface NotificationStrategy
{
    public function send(User $user, string $message): void;
}

final class EmailNotificationStrategy implements NotificationStrategy
{
    public function send(User $user, string $message): void
    {
        Mail::to($user)->send(new GenericEmail($message));
    }
}

final class SmsNotificationStrategy implements NotificationStrategy
{
    public function send(User $user, string $message): void
    {
        // SMS implementation
    }
}

final class NotificationService
{
    public function __construct(
        private readonly NotificationStrategy $strategy
    ) {}

    public function notify(User $user, string $message): void
    {
        $this->strategy->send($user, $message);
    }
}
```

## 3. Architecture & Project Structure

### 3.1 Directory Structure

```
app/
├── Actions/
│   ├── Order/
│   │   ├── OrderCreateAction.php
│   │   ├── OrderItemCancelAction.php
│   └── Search/
│       ├── SearchQueryTrackLogAction.php
├── Services/
│   ├── Order/
│   │   ├── OrderConditionService.php
│   └── Supplier/
│       ├── DeliveryScheduleService.php
├── Repositories/
│   ├── UserRepository.php
│   ├── Contracts/
│   │   ├── UserRepositoryContract.php
├── Models/
│   ├── Order.php
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
```

### 3.2 General Rules

* No `.gitkeep` after adding real files
* Avoid `DB::`; always use model or repository methods
* Controllers are **thin** – only gather inputs and call Actions
* Use **FormRequest** classes for validation (`Create`, `Update`, `Delete`)
* Each model has `fillable` and registers observers via:

  ```php
  #[ObservedBy([OrderObserver::class])]
  ```
* Use **DTOs** to transfer data between layers

### 3.3 Form Request Best Practices

* **Always use `$this->validated('field')` instead of `$this->input('field')`** to access request data
* Create **getter methods** for all validated fields with explicit return types
* Getter methods provide type safety and IDE autocomplete
* Use `?string` for nullable fields, `string` for required fields

**Example:**

```php
final class RegisterWithCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'company_phone' => 'nullable|string|max:20',
        ];
    }

    public function getFirstName(): string
    {
        return $this->validated('first_name');
    }

    public function getLastName(): string
    {
        return $this->validated('last_name');
    }

    public function getEmail(): string
    {
        return $this->validated('email');
    }

    public function getCompanyPhone(): ?string
    {
        return $this->validated('company_phone');
    }
}

// ✅ Good - using getter methods
$firstName = $request->getFirstName();
$phone = $request->getCompanyPhone();

// ❌ Bad - using input() or array access
$firstName = $request->input('first_name');
$firstName = $request->validated()['first_name'];
```

## 4. Actions & Services (Business Logic Layer)

### 4.1 Actions

* Located in `app/Actions/{Domain}`
* Naming: `[Domain][Object][Verb]Action` (e.g., `OrderCreateAction`)
* Each Action handles **one business operation**
* Constructor-inject dependencies (repositories, services, sub-actions)
* Main method: `handle()`
* If modifying data → wrap in `DB::transaction()`
* Exceptions bubble up to global handler
* Limit dependencies to 8; split Action if exceeding
* Controller should only:

  * Validate (FormRequest)
  * Create DTO
  * Call `Action->handle()`
  * Return Resource

**Example:**

```php
final class OrderCreateAction
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly OrderConditionService $conditions,
        private readonly OrderIssueNotificationAction $notify
    ) {}

    public function handle(OrderCreateDTO $dto, int $userId): Order
    {
        $this->conditions->validate($dto);

        return DB::transaction(function () use ($dto, $userId) {
            $order = $this->orders->create($dto, $userId);
            $this->notify->handle($order);

            return $order;
        });
    }
}
```

### 4.2 Services

* Located in `app/Services/{Domain}`
* Naming: `[Domain][Purpose]Service` (e.g., `CartItemValidator`)
* **Stateless**, no side effects
* Handles calculations, validations, API calls, formatting
* Limit dependencies to 5; refactor if exceeding
* Must be reusable and unit-testable

**Example:**

```php
final class DeliveryScheduleService
{
    public function calculate(DateTime $orderDate, array $rules): DateTime
    {
        return $this->applyRules($orderDate, $rules);
    }

    private function applyRules(DateTime $orderDate, array $rules): DateTime
    {
        return $orderDate;
    }
}
```

## 5. Testing

* Run tests with Sail:

  ```bash
  ./vendor/bin/sail test
  ```
* Each model has a Factory
* Use `fake()` instead of hardcoded values
* Use `assertDatabaseHas()` with model class:
* In tests, always use the `route()` helper to generate route URLs.
* The test name must clearly describe what the test is trying to verify.
* Don't use hardcoded values in tests — always use the `Faker` library or the `fake()` helper to generate realistic random data.
* Tests should follow the real application flow — make requests to endpoints, trigger actions, and validate responses rather than calling methods directly.

  ```php
  $this->assertDatabaseHas(Order::class, ['status' => 'pending']);
  ```
* Test directory:

```
tests/
├── Feature/
│   ├── Http/
│   └── Console/
├── Unit/
│   ├── Actions/
│   ├── Services/
│   ├── Models/
│   ├── Jobs/
```

## 6. Styling & Frontend

* Use **Tailwind CSS**
* Minimalist, responsive UI
* Recompile assets after frontend changes:

  ```bash
  npm run build
  ```

## 7. Task Completion Checklist ✅

* Pint ran successfully
* Tests passed (`sail test`)
* Actions & Services follow naming & structure standards
* Repositories, Services, and Actions are tested
* DTOs and FormRequests are used
* No `else`, no `DB::`, no magic numbers
* Design patterns applied appropriately where beneficial
* Assets recompiled
