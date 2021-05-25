<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class CheckImportantFireIncident extends Job implements ShouldQueue, ShouldBeUnique
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $activeStatus = array(
            1,2,3,4,5,6
        );

        $incidents = Incident::where('active', true)
            ->whereIn('status', $activeStatus)
            ->where('sentCheckImportant', false)
            ->where('isFire',true)
            ->get();

        foreach($incidents as $incident){
            $totalAssets = $incident->aerial + $incident->terrain;

            if($totalAssets > env('IMPORTANT_INCIDENT_TOTAL_ASSETS')) {
                $timestampLast =time();
                $timestampLast = strtotime('-3 hours', $timestampLast);

                if($incident->dateTime->timestamp < $timestampLast){
                    $hashTag = HashTagTool::getHashTag($incident->concelho);

                    $status = "ℹ🔥 Segundo os critérios da ANEPC o incêndio em {$incident->location} é considerado importante 🔥ℹ";
                    NotificationTool::send($status, $incident->location, $incident->id);

                    $status = "ℹ🔥 Segundo os critérios da @ProteccaoCivil o incêndio em {$incident->location} é considerado importante. https://fogos.pt/fogo/{$incident->id} {$hashTag} #FogosPT 🔥ℹ";
                    $lastTweetId = TwitterTool::tweet($status,$incident->lastTweetId);
                    TelegramTool::publish($status);

                    $facebookStatus =  "ℹ🔥 Segundo os critérios da ANEPC o incêndio em {$incident->location} é considerado importante. https://fogos.pt/fogo/{$incident->id} {$hashTag} #FogosPT 🔥ℹ";
                    FacebookTool::publish($facebookStatus);

                    $incident->lastTweetId = $lastTweetId;
                    $incident->sentCheckImportant=true;
                    $incident->save();
                }
            }
        }
    }
}
