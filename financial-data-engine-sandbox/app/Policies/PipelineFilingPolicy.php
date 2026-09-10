<?php

namespace App\Policies;

use App\Models\Filing;
use App\Models\FilingArtifact;
use App\Models\User;

final class PipelineFilingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function view(User $user, Filing $filing): bool
    {
        return $user->exists && $filing->exists;
    }

    public function viewDetail(User $user, Filing $filing): bool
    {
        return $this->view($user, $filing);
    }

    public function viewHistory(User $user, Filing $filing): bool
    {
        return $this->view($user, $filing);
    }

    public function viewArtifact(User $user, Filing $filing, FilingArtifact $artifact): bool
    {
        return $this->view($user, $filing) && $artifact->filing_id === $filing->filing_id;
    }

    public function retry(User $user, Filing $filing): bool
    {
        $allowed = array_map('strval', (array) config('financial-pipeline.ops.retry_actor_ids', []));

        return $this->view($user, $filing) && in_array((string) $user->getAuthIdentifier(), $allowed, true);
    }

    public function reprocess(User $user, Filing $filing): bool
    {
        $allowed = array_map('strval', (array) config('financial-pipeline.ops.reprocess_actor_ids', []));

        return $this->view($user, $filing) && in_array((string) $user->getAuthIdentifier(), $allowed, true);
    }
}
