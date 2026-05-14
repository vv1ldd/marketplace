<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Seller;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Models\Role;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User|Seller $user): bool
    {
        return $user->can('view_any_users::user');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User|Seller $user): bool
    {
        return $user->can('view_users::user');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User|Seller $user): bool
    {
        return $user->can('create_users::user');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User|Seller $user): bool
    {
        return $user->can('update_users::user');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User|Seller $user): bool
    {
        return $user->can('delete_users::user');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User|Seller $user): bool
    {
        return $user->can('delete_any_users::user');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User|Seller $user): bool
    {
        return $user->can('force_delete_users::user');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User|Seller $user): bool
    {
        return $user->can('force_delete_any_users::user');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User|Seller $user): bool
    {
        return $user->can('restore_users::user');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User|Seller $user): bool
    {
        return $user->can('restore_any_users::user');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User|Seller $user): bool
    {
        return $user->can('replicate_users::user');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User|Seller $user): bool
    {
        return $user->can('reorder_users::user');
    }
}
