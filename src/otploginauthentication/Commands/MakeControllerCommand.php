<?php

namespace bushart\otploginauthentication\otploginauthentication\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Facades\Artisan;

class MakeControllerCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:otp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate new otp authentication resource';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Resource';


    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return;
    }


    /**
     * Execute the console command.
     *
     * @return bool|null
     */
    public function handle()
    {
        $this->handleCommands();
        $this->info($this->type . ' created successfully.');
    }

    protected function handleCommands()
    {
        //Generate controller
        $controller_name = 'AuthOtpController';
        $this->call('otploginauthentication:controller', ['name' => $controller_name]);
        $this->call('otploginauthentication:views', ['name' => 'auth']);

        //publish migration
        Artisan::call('vendor:publish', [
            '--tag' => 'migrations',
        ]);
    }
}
