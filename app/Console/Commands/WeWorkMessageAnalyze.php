<?php
namespace App\Console\Commands;

use App\Models\MessageModel;
use App\Models\MessageOriginalModel;
use App\Services\WeWorkServices;

class WeWorkMessageAnalyze extends CommonCommand
{

    public $limit = 100;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'WeWorkMessageAnalyze';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'WeWorkMessageAnalyze';


    public function main($params=[]){
        $seq = MessageModel::where([])->max('seq')??0;
        $list = MessageOriginalModel::where('seq','>',$seq)->limit($this->limit)->orderBy('seq','asc')->pluck('msgid')->toArray();
        (new WeWorkServices())->messageAnalyze($list);
    }
}
