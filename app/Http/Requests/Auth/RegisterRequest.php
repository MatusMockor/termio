<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\RegisterUserDTO;
use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('business_name', '')),
        ]);
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'unique:tenants,slug'],
            'business_type' => ['nullable', Rule::enum(BusinessType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Tento názov firmy je už obsadený.',
        ];
    }

    public function getName(): string
    {
        return $this->validated('name');
    }

    public function getEmail(): string
    {
        return $this->validated('email');
    }

    public function getPassword(): string
    {
        return $this->validated('password');
    }

    public function getBusinessName(): string
    {
        return $this->validated('business_name');
    }

    public function getBusinessType(): ?BusinessType
    {
        $value = $this->validated('business_type');

        return $value !== null ? BusinessType::from($value) : null;
    }

    public function toDTO(): RegisterUserDTO
    {
        return new RegisterUserDTO(
            name: $this->getName(),
            email: $this->getEmail(),
            password: $this->getPassword(),
            businessName: $this->getBusinessName(),
            businessType: $this->getBusinessType(),
        );
    }
}
