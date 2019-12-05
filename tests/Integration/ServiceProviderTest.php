<?php

namespace Tests\Integration;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

/**
 * Class ServiceProviderTest
 *
 * @package     Tests\Integration
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
class ServiceProviderTest extends TestCase
{
    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Tests config file is existed in config directory after run
     *
     * php artisan vendor:publish --tag=laravel-social-credentials
     *
     * @return void
     */
    public function testItShouldPublishVendorMigrations()
    {
        $sourceFile = dirname(dirname(__DIR__)) . '/resources/stubs/create_table.stub';
        $targetFile = database_path('migrations/2019_12_05_100000_create_social_credentials_table.php');

        $this->assertFileNotExists($targetFile);

        $this->artisan('vendor:publish', [
            // '--provider' => 'Laravel\\SocialCredentials\\ServiceProvider',
            '--tag' => 'laravel-social-credentials',
        ]);

        $this->assertFileExists($targetFile);
        $this->assertEquals(file_get_contents($sourceFile), file_get_contents($targetFile));
    }

    /**
     * Set up before test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
    }

    /**
     * Clear up after test
     */
    protected function tearDown(): void
    {
        $this->files->delete([
            $this->app->databasePath('migrations/2019_12_05_100000_create_social_credentials_table.php'),
        ]);

        parent::tearDown();
    }
}
