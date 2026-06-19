<?php

namespace App\Domains\Learning\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['playbook_id', 'sort_order', 'title', 'content', 'tip', 'tip_type'])]
class PlaybookStep extends Model
{
    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }
}
