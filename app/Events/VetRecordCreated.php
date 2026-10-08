<?php

namespace App\Events;

use App\Models\VetRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VetRecordCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly VetRecord $record) {}
}
