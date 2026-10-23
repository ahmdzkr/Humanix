<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewScore extends Model
{
    protected $fillable = ['interview_id', 'indicator', 'score', 'comment'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }
}
