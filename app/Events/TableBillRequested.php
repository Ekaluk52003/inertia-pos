<?php

namespace App\Events;

use App\Models\QrCode;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableBillRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public QrCode $qrCode) {}

    public function broadcastOn(): array
    {
        return [new Channel("restaurant.{$this->qrCode->restaurant_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'qr_code_id' => $this->qrCode->id,
            'table_number' => $this->qrCode->table_number,
            'status' => $this->qrCode->status,
        ];
    }
}
