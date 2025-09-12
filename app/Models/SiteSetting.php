<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SiteSetting extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'status'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_setting')
            ->logOnly(['name', 'status'])
            ->setDescriptionForEvent(function (string $eventName) {
                $actorName = Auth::user()?->name ?? 'System';
                $changes = [];

                if ($this->isDirty('status')) {
                    $original = $this->getOriginal('status');
                    $changes[] = "status changed from $original to $this->status";
                }

                $changeText = empty($changes) ? '' : ' — ' . implode(', ', $changes);

                return "$actorName has $eventName site setting $this->name (ID: $this->id) $changeText";
            })
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
