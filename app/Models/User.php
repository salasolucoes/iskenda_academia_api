<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Infrastructure\Persistence\Eloquent\Models\User as BaseUser;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'otp_code', 'otp_expires_at', 'email_verified_at', 'is_active', 'avatar_url', 'remember_token'])]
#[Hidden(['password', 'remember_token', 'otp_code'])]
class User extends BaseUser
{
    use HasApiTokens;
}
