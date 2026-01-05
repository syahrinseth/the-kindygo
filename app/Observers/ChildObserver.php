<?php

namespace App\Observers;

use App\Models\Child;

class ChildObserver
{
    public function creating(Child $child): void {}
}
