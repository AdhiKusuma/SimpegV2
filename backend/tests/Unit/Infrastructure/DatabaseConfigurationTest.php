<?php

namespace Tests\Unit\Infrastructure;

use Tests\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    public function test_application_uses_only_the_mysql_connection(): void
    {
        $connections = config('database.connections');

        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mysql', config('database.connections.mysql.driver'));
        $this->assertSame(['mysql'], array_keys($connections));
    }

    public function test_testing_database_is_the_local_simpeg_database(): void
    {
        $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
        $this->assertSame('3306', (string) config('database.connections.mysql.port'));
        $this->assertSame('masq2971_simpeg_dummy', config('database.connections.mysql.database'));
    }
}
