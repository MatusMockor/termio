<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\RegisterUserDTO;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
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

    public function getBusinessType(): ?string
    {
        return $this->validated('business_type');
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
