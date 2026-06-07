<?php

namespace App\Policies\Order;

use App\Models\Order\OrderComment;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderCommentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny($user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($user, OrderComment $orderComment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user): bool
    {
        // Разрешаем создавать комментарии всем авторизованным пользователям
        // (и системным юзерам, и партнерам-селлерам)
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($user, OrderComment $orderComment): bool
    {
        // Sovereign validators can correct operational comment history.
        if (method_exists($user, 'hasOpsSovereignAccess') && $user->hasOpsSovereignAccess()) {
            return true;
        }

        return $user->id === $orderComment->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, OrderComment $orderComment): bool
    {
        // Sovereign validators can correct operational comment history.
        if (method_exists($user, 'hasOpsSovereignAccess') && $user->hasOpsSovereignAccess()) {
            return true;
        }

        return $user->id === $orderComment->user_id;
    }
}
