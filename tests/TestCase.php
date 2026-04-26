<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Indicates whether the default seeding trait should be used in database tests.
     *
     * @var bool
     */
    protected $seed = false;
}