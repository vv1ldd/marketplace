<?php

namespace App\Policies;

use App\Models\Order\Order;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User|Seller $user): bool
    {
        if ($user instanceof Seller) {
            return true;
        }

        return $user->can('view_any_orders::order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User|Seller $user, Order $order): bool
    {
        if ($user instanceof Seller) {
            return $user->managedLegalEntities()
                ->whereHas('shops', fn ($q) => $q->where('shops.id', $order->shop_id))
                ->exists();
        }

        return $user->can('view_orders::order');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User|Seller $user): bool
    {
        if ($user instanceof Seller) {
            return false; // Sellers don't create orders manually
        }

        return $user->can('create_orders::order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User|Seller $user, Order $order): bool
    {
        if ($user instanceof Seller) {
            return $user->managedLegalEntities()
                ->whereHas('shops', fn ($q) => $q->where('shops.id', $order->shop_id))
                ->exists();
        }

        return $user->can('update_orders::order');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User|Seller $user, Order $order): bool
    {
        return $user->can('delete_orders::order');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User|Seller $user): bool
    {
        return $user->can('delete_any_orders::order');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User|Seller $user, Order $order): bool
    {
        return $user->can('force_delete_orders::order');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User|Seller $user): bool
    {
        return $user->can('force_delete_any_orders::order');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User|Seller $user, Order $order): bool
    {
        return $user->can('restore_orders::order');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User|Seller $user): bool
    {
        return $user->can('restore_any_orders::order');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User|Seller $user, Order $order): bool
    {
        return $user->can('replicate_orders::order');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User|Seller $user): bool
    {
        return $user->can('reorder_orders::order');
    }
}
