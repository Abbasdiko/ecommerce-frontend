<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('agent.view');
    }

    public function view(User $user, Agent $agent): bool
    {
        if ($user->can('agent.view')) {
            return true;
        }

        return $user->id === $agent->user_id;
    }

    public function update(User $user, Agent $agent): bool
    {
        if ($user->can('agent.update')) {
            return true;
        }

        return $user->id === $agent->user_id;
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $user->can('agent.delete');
    }

    public function approve(User $user): bool
    {
        return $user->can('agent.approve');
    }

    public function reject(User $user): bool
    {
        return $user->can('agent.reject');
    }

    public function suspend(User $user): bool
    {
        return $user->can('agent.suspend');
    }
}
