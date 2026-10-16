<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit');
