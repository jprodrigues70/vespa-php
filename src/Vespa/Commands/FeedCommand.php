<?php

namespace Escavador\Vespa\Commands;

use Carbon\Carbon;
use Escavador\Vespa\Common\EnumModelStatusVespa;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Log;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class FeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'vespa:feed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Feed the vespa with models.';

    protected $bulk;

    protected $time_out;

    protected $logger;


    public function __construct()
    {
        parent::__construct();
        $hosts = explode(',', trim(config('vespa.hosts')));

        $this->vespa_status_column = config('vespa.model_columns.status', 'vespa_status');
        $this->vespa_date_column = config('vespa.model_columns.date', 'vespa_last_indexed_date');
        $this->mapped_models = config('vespa.mapped_models');
        $this->bulk = $this->getBulkDefault();

        //$this->logger = new Logger('vespa-log');
        //$this->logger->pushHandler(new StreamHandler(storage_path('logs/vespa-feeder.log')), Logger::INFO);
        //yaml_parse($yaml);
    }

    /**
     * Execute the console command.
     *
     * @return void
    */
    public function handle()
    {
        $this->message('info', 'Feed was started');
        $start_time = Carbon::now();

        $models = $this->argument('model');
        $bulk = $this->option('bulk');
        $time_out = $this->option('time-out');

        if (!is_array($models))
        {
            $this->error('The [model] argument has to be an array.');
            return;
        }

        if (!is_numeric($bulk))
        {
            $this->error('The [bulk] argument has to be a number.');
            return;
        }

        if ($bulk <= 0)
        {
            $this->error('The [bulk] argument has to be greater than 0.');
            return;
        }

        $this->bulk = $bulk;

        if ($time_out !== null && !is_numeric($time_out))
        {
            $this->error('The [time-out] argument has to be a number.');
            return;
        }

        if ($time_out !== null && !is_numeric($time_out <= 0))
        {
            $this->error('The [time-out] argument has to be a number.');
            return;
        }

        set_time_limit($time_out ?: 0);

        foreach ($models as $item)
        {
            if (!array_key_exists($item, $this->mapped_models))
            {
                $this->error("The model [$item] is not mapped at vespa config file.");
                //go to next model
                continue;
            }

            $temp_model = new $this->mapped_models[$item];

            if (!Schema::hasColumn($temp_model->getTable(), $this->vespa_status_column))
            {
                exit($this->message('error', "The model [$this->vespa_status_column] does not have status information on the vespa."));
            }

            if (!Schema::hasColumn($temp_model->getTable(), $this->vespa_date_column))
            {
                exit($this->message('error', "The model [$this->vespa_date_column] does not have date information on the vespa."));
            }

            //TODO: make this async
            try
            {
                $this->process($item);
                $this->message('info', 'The vespa was fed.');
            }
            catch (\Exception $e)
            {
                $this->message('error', '... fail.' . ' '. $e->getMessage() );
            }

            unset($temp_model);
        }
    }

    protected function getBulkDefault()
    {
        return config('vespa.default.bulk', 1);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array (
            array('model', InputArgument::IS_ARRAY, 'Which models to include', array()),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array (
            array('bulk', 'B', InputOption::VALUE_OPTIONAL, 'description here', $this->getBulkDefault()),
            array('dir', 'D', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The model dir', array()),
            array('ignore', 'I', InputOption::VALUE_OPTIONAL, 'Which models to ignore', ''),
            array('time-out', 'T', InputOption::VALUE_OPTIONAL, 'description here', $this->time_out),
        );
    }

    protected function message($type, $message)
    {
        if ($type == 'error') {
            Log::error($message);
        }

        if (!app()->environment('production')) {
            if ($type == 'error') {
                $this->error($message);
            } else if ($type == 'info') {
                $this->info($message);
            }
        }
    }

    protected function getNotIndexedItems($model_class)
    {
        return $model_class::take($this->bulk)
                            ->where($this->vespa_status_column, EnumModelStatusVespa::NOT_INDEXED)
                            ->get();
    }

    private function process($model)
    {
        $model_class = $this->mapped_models[$model];
        $items = $this->getNotIndexedItems($model_class);

        if ($items !== null && !$items->count())
        {
            $this->message('info', "[$model] already up-to-date.");
            return false;
        }

        $this->message('info', "Feed vespa with [{$items->count()}] [$model].");

        foreach ($items as $item) {
            //Records on vespa
            //TODO


            //Update model's vespa info
            $item[$this->vespa_status_column] = EnumModelStatusVespa::INDEXED;
            $item[$this->vespa_date_column] = Carbon::now();
            $item->save();
        }

        $this->message('info', "[$model] was done.");
        return true;
    }
}