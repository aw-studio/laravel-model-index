<?php

namespace Workbench\App\Models;

use AwStudio\ModelIndex\Traits\HasIndexQuery;
use Workbench\App\Models\User;
use Illuminate\Database\Eloquent\Model;
use AwStudio\ModelIndex\Traits\IndexQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TestModel extends Model
{
    /** @use HasFactory<\Workbench\Database\Factories\TestModelFactory> */
    use HasFactory;
    use HasIndexQuery;

    public function filterable()
    {
        return ['*'];
    }

    public static function newFactory()
    {
        return \Workbench\Database\Factories\TestModelFactory::new();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
