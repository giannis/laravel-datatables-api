<?php

namespace GPapakitsos\LaravelDatatables\Tests;

use GPapakitsos\LaravelDatatables\DatatablesServiceProvider;
use GPapakitsos\LaravelDatatables\Tests\Models\Country as Country;
use GPapakitsos\LaravelDatatables\Tests\Models\User as User;
use GPapakitsos\LaravelDatatables\Tests\Models\UserLogin as UserLogin;
use Orchestra\Testbench\TestCase;

class FeatureTestCase extends TestCase
{
    public $route_prefix;
    public $country;
    public $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        User::factory()->has(UserLogin::factory()->count(rand(1, 5)))->count(49)->create();
        $this->country = Country::factory()->create(['founded_at' => '1995-06-15']);
        $this->user = User::factory()->has(UserLogin::factory()->count(rand(10, 20)))->create([
            'name' => 'George Papakitsos',
            'email' => 'papakitsos_george@yahoo.gr',
            'country_id' => $this->country->id,
            'settings' => '{ "is_admin": true, "nickname": "papaki" }',
            'created_at' => '1981-04-23 10:00:00',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            DatatablesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $config = $app->get('config');

        $config->set('database.default', 'testbench');
        $config->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->route_prefix = 'datatable';
        $config->set('datatables', [
            'models_namespace' => 'GPapakitsos\LaravelDatatables\Tests\Models\\',
            'routes' => [
                'prefix' => $this->route_prefix,
            ],
            'filters' => [
                'date_format' => 'd/m/Y',
                'date_display_format' => 'd/m/Y',
                'date_delimiter' => '-dateDelimiter-',
                'null_delimiter' => '-nullDelimiter-',
                'date_columns' => ['founded_at'],
            ],
        ]);
    }

    protected function getRequestDataSample()
    {
        return [
            'draw' => 1,
            'columns' => [
                [
                    'data' => 'id',
                    'searchable' => true,
                    'orderable' => false,
                    'search' => [
                        'value' => '',
                    ],
                ],
                [
                    'data' => 'name',
                    'search' => [
                        'value' => '',
                    ],
                ],
                [
                    'data' => 'email',
                    'search' => [
                        'value' => '',
                    ],
                ],
                [
                    'data' => 'created_at',
                    'search' => [
                        'value' => '',
                    ],
                ],
                [
                    'data' => 'updated_at',
                    'search' => [
                        'value' => '',
                    ],
                ],
                [
                    'data' => 'country',
                    'search' => [
                        'value' => '',
                    ],
                ],
                [
                    'data' => 'userLogins',
                    'search' => [
                        'value' => '',
                    ],
                ],
                [
                    'data' => 'settings',
                    'search' => [
                        'value' => '',
                    ],
                ],
                [
                    'data' => 'userNameAndEmail',
                    'search' => [
                        'value' => '',
                    ],
                ],
            ],
            'start' => 0,
            'length' => 20,
            'search' => [
                'value' => '',
            ],
            'order' => [
                [
                    'column' => 1,
                    'dir' => 'asc',
                ],
            ],
        ];
    }
}
