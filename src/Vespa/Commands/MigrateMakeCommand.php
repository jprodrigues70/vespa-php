<?php

namespace Escavador\Vespa\Commands;

use Escavador\Vespa\Migrations\MigrationCreator;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand as IlluminateMigrateMakeCommand;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

class MigrateMakeCommand extends IlluminateMigrateMakeCommand
{

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'vespa:migration {name : The name of the migration}
        {table : The table to migrate}
        {--path= : The location where the migration file should be created}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new basic vespa migration file';

    /**
     * Create a new migration install command instance.
     *
     * @param  \Escavador\Vespa\Migrations\MigrationCreator $creator
     * @param  \Illuminate\Support\Composer  $composer
     * @return void
     */
    public function __construct(MigrationCreator $creator, Composer $composer)
    {
        parent::__construct($creator, $composer);
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $name = Str::snake(trim($this->input->getArgument('name')));

        $table = $this->input->getArgument('table');

        $this->writeMigration($name, $table, false);

        $this->composer->dumpAutoloads();
    }

}