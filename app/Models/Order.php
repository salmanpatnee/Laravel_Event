<?php

namespace App\Models;

use App\OrderStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'status',
        'total_amount',
        'reminder_sent_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'status' => OrderStatusEnum::class,
        'reminder_sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }

    protected function isOrderCancelled(): Attribute
    {
        return Attribute::get(
            fn () => $this->status === OrderStatusEnum::Cancelled
        );
    }
}
