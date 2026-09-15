<?php

declare(strict_types=1);

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends \Spatie\Activitylog\Models\Activity
{
    use SoftDeletes;

    public function causer(): MorphTo
    {
        return parent::causer()->withTrashed();
    }
}
