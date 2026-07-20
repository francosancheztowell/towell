<?php

declare(strict_types=1);

namespace App\Models\Integraciones;

use Illuminate\Database\Eloquent\Model;

final class RedboothCredential extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'RedboothCredentials';

    protected $fillable = [
        'usuario_id',
        'access_token',
        'refresh_token',
        'token_type',
        'scope',
        'expires_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
