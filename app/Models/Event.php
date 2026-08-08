<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'venue',
        'status',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    // protected function lowestPrice(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn () => $this->ticketTypes->min('price'),
    //     );
    // }
}
