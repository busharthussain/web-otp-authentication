<?php

namespace bushart\otploginauthentication;
use Illuminate\Support\ServiceProvider;

class OtpAuthenticationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        $this->commands([
            otploginauthentication\Commands\MakeControllerCommand::class,
            otploginauthentication\Commands\ControllerCommand::class,
            otploginauthentication\Commands\ViewCommand::class,
        ]);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/migration/022_11_24_110854_add_new_fields_users.php' => database_path('migrations/' . date('Y_m_d_His', time()) . '_add_new_fields_users.php'),
            // you can add any number of migrations here
        ], 'migrations');

        require_once __DIR__.'/otploginauthentication/Traits/OtpAuthentication.php';

    }
}
