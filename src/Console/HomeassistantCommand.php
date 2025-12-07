<?php


namespace Mapo89\LaravelHomeassistantApi\Console;


use Illuminate\Console\Command;
use Mapo89\LaravelHomeassistantApi\HomeassistantApi;


class HomeassistantCommand extends Command
{
    protected $signature = 'ha:call {domain?} {service?} {--entity=}';
    protected $description = 'Call a Home Assistant service or list states';


    public function handle()
    {
        $ha = HomeassistantApi::make();
        $domain = $this->argument('domain');
        $service = $this->argument('service');
        $entity = $this->option('entity');


        if (!$domain || !$service) {
            if ($entity) {
                $this->info('Listing state for ' .$entity);
                $state = $ha->states()->get($entity);
                $this->line($state['entity_id'] . ' => ' . $state['state']);
                return 0;
            }
            $this->info('Listing all states:');
            $states = $ha->states()->all();
            foreach ($states as $state) {
                $this->line($state['entity_id'] . ' => ' . $state['state']);
            }
            return 0;
        }

        $data = [];
        if ($entity) {
            $data['entity_id'] = $entity;
        }


        $response = $ha->services()->call($domain, $service, $data);
        $this->info('Service called successfully:');
        $this->line(json_encode($response, JSON_PRETTY_PRINT));
        return 0;
    }
}
