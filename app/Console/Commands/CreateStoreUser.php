<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateStoreUser extends Command
{
    protected $signature = 'store:create-user';

    protected $description = 'Create a staff account interactively; public registration is disabled';

    public function handle(): int
    {
        $name = text('اسم الموظف', required: true, validate: ['name' => 'required|string|max:120']);
        $email = mb_strtolower(text('البريد الإلكتروني', required: true, validate: function (string $value): ?string {
            $validator = Validator::make(['email' => mb_strtolower($value)], ['email' => 'required|email|max:255|unique:users,email']);

            return $validator->fails() ? 'أدخل بريدًا صحيحًا وغير مسجل مسبقًا.' : null;
        }));
        $role = select('الدور', options: [
            UserRole::Cashier->value => 'كاشير',
            UserRole::Accountant->value => 'محاسب',
            UserRole::Owner->value => 'مالك',
        ]);
        $secret = password('كلمة المرور', required: true, validate: function (string $value): ?string {
            $validator = Validator::make(['password' => $value], ['password' => [Password::min(12)->mixedCase()->numbers()]]);

            return $validator->fails() ? 'استخدم 12 حرفًا على الأقل تشمل حروفًا كبيرة وصغيرة وأرقامًا.' : null;
        });

        User::create(['name' => $name, 'email' => $email, 'role' => $role, 'password' => $secret]);
        $this->info('تم إنشاء حساب الموظف.');

        return self::SUCCESS;
    }
}
