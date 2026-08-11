<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in for whatever record a consuming application owns domains with.
 *
 * The package must work with any Eloquent model, so the tests deliberately use
 * a throwaway one rather than something the package knows about.
 */
class Owner extends Model
{
    protected $table = 'test_owners';

    protected $fillable = ['name'];

    public $timestamps = false;
}
