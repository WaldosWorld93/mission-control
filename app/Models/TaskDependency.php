<?php

namespace App\Models;

use App\Enums\DependencyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'depends_on_task_id',
        'dependency_type',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'dependency_type' => DependencyType::class,
            'created_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}
