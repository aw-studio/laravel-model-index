<?php

namespace AwStudio\ModelIndex\Traits;

use AwStudio\ModelIndex\IndexQueryBuilder;

trait HasIndexQuery
{
    public static function index($request = null): IndexQueryBuilder
    {
        $instance = new static; // Get the model instance

        return new IndexQueryBuilder($instance->newQuery(), $request);
    }
}
