<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\LoginDTO;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function getEmail(): string
    {
        return $this->validated('email');
    }

    public function getPassword(): string
    {
        return $this->validated('password');
    }

    public function toDTO(): LoginDTO
    {
        return new LoginDTO(
            email: $this->getEmail(),
            password: $this->getPassword(),
        );
    }
}
