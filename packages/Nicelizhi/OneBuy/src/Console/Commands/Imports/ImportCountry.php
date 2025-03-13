<?php
namespace Nicelizhi\OneBuy\Console\Commands\Imports;

use Illuminate\Console\Command;

class ImportCountry extends Command
{
    protected $signature = 'onebuy:import-country';
    protected $description = 'Import country data';

    public function handle()
    {
        $this->info('Import country data');
        $files = [];

        $files = [
            'au.json',
            'gb.json',
            'se.json',
            'gr.json',
        ];

        foreach ($files as $file) {
            $this->info('Importing country data from ' . $file);
            $this->import($file);
        }
        
    }

    public function import($file) {

        if (empty($file)) {
            $this->error('Please specify the file path');
            return;
        }

        $file = trim($file);
        if (empty($file)) {
            $this->error('Please specify the file path');
            return;
        }
        $country_code = substr($file, 0, 2);

        $file = '/template-common/checkout1/state/'.$file;

        $this->info('Importing country data from ' . $file);

        // read the file from the public path
        $path = public_path($file);
        if (!file_exists($path)) {
            $this->error('File not found');
            return;
        }

        // get the country data from the db countries table
        $country = \DB::table('countries')->where('code', $country_code)->first();
        
        if (empty($country)) {
            $this->error('Country not found');
            return;
        }

        // read the file contents and check the countries state data in db
        $states = json_decode(file_get_contents($path), true);
        if (empty($states)) {
            $this->error('No state data found');
            return;
        }
        foreach ($states as $state) {
            var_dump($state);
            $state_code = $state['StateCode'];
            $state_name = $state['StateName'];
            $CountryCode = $state['CountryCode'];
            $state_data = \DB::table('country_states')->where('country_id', $country->id)->where('country_code',$CountryCode)->where('code', $state_code)->first();
            var_dump($state_data);
            if (empty($state_data)) {
                $this->info('Inserting state data: ' . $state_name);
                \DB::table('country_states')->insert([
                    'country_id' => $country->id,
                    'country_code' => $CountryCode,
                    'code' => $state_code,
                    'default_name' => $state_name
                ]);
            } else {
                $this->info('State data already exists: ' . $state_name);
            }
        }
    }
}