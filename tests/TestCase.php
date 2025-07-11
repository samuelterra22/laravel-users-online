<?php

namespace SamuelTerra22\Tests;

use SamuelTerra22\UsersOnline\Traits\UsersOnlineTrait;
use Illuminate\Database\Capsule\Manager as DB;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected $faker;

    protected function setUp(): void
    {
        $this->faker = Factory::create();
        parent::setUp();
        $this->setUpDatabase();
        $this->migrateTables();
    }

    protected function setUpDatabase()
    {
        $database = new DB();
        $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $database->bootEloquent();
        $database->setAsGlobal();
    }

    protected function migrateTables()
    {
        DB::schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function makeUser()
    {
        $user = new User();
        $user->name = $this->faker->name;
        $user->email = $this->faker->email;
        $user->password = bcrypt('gabriel');
        $user->save();

        return $user;
    }

    public function getUserModel()
    {
        return new User();
    }
}

class User extends \Illuminate\Foundation\Auth\User
{
    use UsersOnlineTrait;
}
