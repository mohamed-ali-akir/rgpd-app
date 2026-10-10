<?php

namespace App\Policies;

use App\Models\ErasureRequest;
use App\Models\User;

/**
 * Un utilisateur ne voit et ne modifie QUE ses propres demandes.
 * Laravel trouve cette classe tout seul grâce à la convention de nommage
 * (App\Models\ErasureRequest → App\Policies\ErasureRequestPolicy).
 */
class ErasureRequestPolicy
{
    public function view(User $user, ErasureRequest $request): bool
    {
        return $request->user_id === $user->id;
    }

    public function update(User $user, ErasureRequest $request): bool
    {
        return $request->user_id === $user->id;
    }

    public function delete(User $user, ErasureRequest $request): bool
    {
        return $request->user_id === $user->id;
    }
}
