<?php

namespace App\Requests;

class RequestMonitor
{
    public function __construct(
        public int $id, public ?string $status, public ?float $progress
    ){}

}
