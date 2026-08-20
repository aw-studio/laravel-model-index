<?php

namespace AwStudio\ModelIndex\Traits;

use AwStudio\ModelIndex\IndexQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Adds a filterable, sortable, searchable index query to a model.
 *
 * @mixin Model
 */
trait HasIndexQuery
{
    /**
     * Start an index query for this model.
     *
     * Reads the current request unless one is passed explicitly, which is what
     * makes the builder usable outside an HTTP context.
     */
    public static function index(?Request $request = null): IndexQueryBuilder
    {
        // `static::query()` rather than `(new static)->newQuery()`: it does not
        // instantiate the model just to reach its builder, and it does not
        // assume the constructor takes no arguments.
        return new IndexQueryBuilder(static::query(), $request);
    }
}
