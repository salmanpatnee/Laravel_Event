<?php

namespace App\Models;

use App\OrderStatusEnum;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function activeTickets()
    {
        return $this->tickets()->whereHas('order', fn ($query) => $query->where('status', '!=', OrderStatusEnum::Cancelled));
    }

    protected function remainingQuantity(): Attribute
    {
        return Attribute::get(
            fn () => $this->quantity - ($this->active_tickets_count ?? $this->activeTickets()->count())
        );
    }
}
