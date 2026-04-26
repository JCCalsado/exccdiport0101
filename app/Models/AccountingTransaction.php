<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'type',
        'amount',
        'currency',
        'status',
        'transactionable_type',
        'transactionable_id',
        'description',
        'transaction_date',
        'due_date',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'due_date' => 'date',
    ];

    public function transactionable()
    {
        return $this->morphTo();
    }

    public function workflowInstances()
    {
        return $this->morphMany(WorkflowInstance::class, 'workflowable');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transaction) {
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = 'TXN-' . strtoupper(uniqid());
            }
        });
    }
}