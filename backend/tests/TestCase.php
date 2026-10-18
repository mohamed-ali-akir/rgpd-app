<?php

namespace Tests;

use App\Services\Web\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeHostResolver;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Aucun test ne doit toucher le réseau : DNS simulé, et toute requête HTTP non simulée échoue.
        $this->app->instance(HostResolver::class, new FakeHostResolver);
        Http::preventStrayRequests();
    }
}
