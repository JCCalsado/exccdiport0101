<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_instance_id',
        'step_name',
        'approver_id',
        'status',
        'comments',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function workflowInstance()
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function approve(string $comments = null): void
    {
        // Guard: only a pending approval can be approved.
        // The controller checks this too, but enforcing it here ensures
        // no second call path (e.g. Artisan command, test helper) can
        // re-approve an already-processed record and corrupt audit timestamps.
        if ($this->status !== 'pending') {
            throw new \LogicException(
                "Cannot approve a WorkflowApproval that is already '{$this->status}' (id: {$this->id})."
            );
        }

        $this->update([
            'status'      => 'approved',
            'comments'    => $comments,
            'approved_at' => now(),
        ]);
    }

    public function reject(string $comments): void
    {
        // Guard: only a pending approval can be rejected.
        if ($this->status !== 'pending') {
            throw new \LogicException(
                "Cannot reject a WorkflowApproval that is already '{$this->status}' (id: {$this->id})."
            );
        }

        $this->update([
            'status'      => 'rejected',
            'comments'    => $comments,
            'approved_at' => now(),
        ]);
    }
}