<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Item;

class MappingDeviceModel extends Command
{
    protected $signature = 'items:mapping-model';

    protected $description = 'Mapping device model item';


    public function handle()
    {

        $items = Item::all();


        foreach($items as $item){


            $name = strtoupper($item->name);


            if(str_contains($name,'H6C')){

                $item->device_model = 'CS-H6c';

            }

            elseif(str_contains($name,'C6N')){

                $item->device_model = 'CS-C6n';

            }

            elseif(str_contains($name,'H1C')){

                $item->device_model = 'CS-H1c';

            }


            $item->save();


        }


        $this->info('Mapping selesai');

    }
}