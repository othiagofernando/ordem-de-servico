<?php

namespace App\Domains\Learning\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'category', 'emoji', 'status'])]
class Playbook extends Model
{
    public function steps(): HasMany
    {
        return $this->hasMany(PlaybookStep::class)->orderBy('sort_order');
    }
}
