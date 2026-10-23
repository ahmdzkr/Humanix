<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    /**
     * HRD (recruitment.manage) melihat semua lamaran. Interviewer (mis. Manager) hanya
     * lamaran yang wawancaranya ditugaskan kepadanya.
     */
    public function view(User $user, JobApplication $application): bool
    {
        if ($user->can('recruitment.manage')) {
            return true;
        }

        return $user->can('recruitment.view')
            && $application->interviews()->where('interviewer_id', $user->id)->exists();
    }
}
