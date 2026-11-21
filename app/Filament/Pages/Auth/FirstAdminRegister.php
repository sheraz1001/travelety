<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Register;

/** Registration is open only until the first (owner) account exists. */
class FirstAdminRegister extends Register
{
    public function mount(): void
    {
        abort_if(User::query()->exists(), 404);
        parent::mount();
    }

    public function register(): ?\Filament\Auth\Http\Responses\Contracts\RegistrationResponse
    {
        abort_if(User::query()->exists(), 404);

        return parent::register();
    }
}
