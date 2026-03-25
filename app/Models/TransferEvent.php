<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferEvent extends Model
{
    /** @use HasFactory<\Database\Factories\TransferEventFactory> */
    use HasFactory;

    protected $table = 'transfer_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';


    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'station_id',
        'amount',
        'status',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }
}

