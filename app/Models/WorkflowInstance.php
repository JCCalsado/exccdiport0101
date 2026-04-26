<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'workflowable_type',
        'workflowable_id',
        'current_step',
        'status',
        'step_history',
        'metadata',
        'initiated_by',
        'completed_at',
    ];

    protected $casts = [
        'step_history' => 'array',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowable()
    {
        return $this->morphTo();
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvals()
    {
        return $this->hasMany(WorkflowApproval::class);
    }

    public function addStepToHistory(string $step, array $data = []): void
    {
        $history = $this->step_history ?? [];
        $history[] = array_merge([
            'step' => $step,
            'timestamp' => now()->toIso8601String(),
        ], $data);
        
        $this->step_history = $history;
        $this->save();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}