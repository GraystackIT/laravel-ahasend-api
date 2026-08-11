<?php

declare(strict_types=1);

uses(GraystackIT\Ahasend\Tests\TestCase::class)->in('Feature', 'Unit');

// Feature tests touch the package's own tables; unit tests stay pure.
uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');
