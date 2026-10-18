<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;

class FindingPolicy
{
    public function update(User $user, Finding $finding): bool
    {
        return $finding->user_id === $user->id;
    }
}
