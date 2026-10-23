<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('positions.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('positions.manage');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->can('positions.manage');
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->can('positions.manage');
    }
}
