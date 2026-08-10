<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$email = $argv[1] ?? null;
$name = $argv[2] ?? null;
$password = $argv[3] ?? null;

if (! $email || ! $name || ! $password) {
    echo "Uso: php scratch/create_user.php <email> <nombre> <contraseña>\n";
    exit(1);
}

$existing = User::where('email', $email)->first();
if ($existing) {
    echo "El usuario ya existe: {$existing->name} ({$existing->email}) ID {$existing->id}\n";
    exit(0);
}

$user = User::create([
    'name' => $name,
    'email' => $email,
    'password' => $password,
]);

echo "Usuario creado: {$user->name} ({$user->email}) con ID {$user->id}\n";
