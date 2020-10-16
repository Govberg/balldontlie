<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

use App\Models\Stat;
use App\Models\Player;

class Process extends Command
{
    /**
     * balldontlie API service information
     *
     * @var object
     */
    protected $api;

    /**
     * Collection of Players
     *
     * @var Player collection
     */
    protected $players;

    /**
     * Collection of Stats
     *
     * @var stats collection
     */
    protected $stats;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process baller.io stats';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Load API configuration
        $this->api = json_decode(json_encode(config('baller')));
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->loadCachedPlayers();
        $this->loadCachedStats();
        $this->getTopTenAvgScorers();

        return 0;
    }

    /**
     * Parse stats collection and get top ten average scorers
     * Output response to console
     *
     * @return null
     */
    private function getTopTenAvgScorers()
    {
        // Sort stats by pts, DESC
        $ordered = $this->stats->sortByDesc('pts');

        // Take top 10 stats
        $stats = $ordered->take(10)->values();

        // Format stats data to an array for output
        $players = $stats->map(function($stat) { return [$stat->player->first_name, $stat->player->last_name, $stat->pts]; });

        // Print values to console
        $this->table(['First Name','Last Name','Avg Points'], $players);
    }

    /**
     * Load all stats to memory so I don't have to hit the API every time
     *
     * @return null
     */
    private function loadCachedStats()
    {
        // Get stats from cache or generate
        $this->stats = Cache::rememberForever('stats', function () { 
            $stats = collect();

            // Split players into chunks of 50 as the stats API has limitations
            $chunks = $this->players->chunk(50);

            // Loop through each chunk and get player stats
            foreach($chunks as $index => $chunk) {
                $this->info("Fetching stats: Page " . ($index+1));

                // Prepare API payload
                $options = [
                    'season' => 2018, 
                    'player_ids' => $chunk->pluck('id')->take(50)->toArray()
                ];

                // Fetch API response
                $response = $this->fetch($this->api->service->season_averages, $options);

                // Loop through response and assign stats
                // attach associated player
                // add stat to collection
                foreach($response['data'] as $object) {
                    $stat = new Stat($object);
                    $stat->player = $this->players->where('id', $stat->player_id)->first();
                    $stats->push($stat);
                }
            }

            // return stats collection
            return $stats;
        });
    }

    /**
     * Load all players to memory so I don't have to hit the API every time
     *
     * @return null
     */
    private function loadCachedPlayers()
    {
        // Get players from cache or generate
        $this->players = Cache::rememberForever('players', function () { 
            return $this->getAllPlayers(); 
        });
    }

    /**
     * Loop through players API and create a new Player for each data object
     * and add them to the players collection
     *
     * @return Player collection
     */
    private function getAllPlayers($page = 1, $players = null)
    {
        $this->info("Fetching players: Page " . $page);
        $players = $players ?? collect();

        // Send payload to players API and get response
        $response = $this->fetch($this->api->service->players, ['per_page' => 100, 'page' => $page]);
        
        // Loop through response data
        // create new player object
        // and push onto players collection
        foreach($response['data'] as $object) {
            $player = new Player($object);
            $players->push($player);
        }

        // Check for additional pages of player data
        // get next page of data if necessary
        if($next_page = $response['meta']['next_page'] ?? null) {
            $this->getAllPlayers($next_page, $players);
        }

        // return players collection
        return $players;
    }

    /**
     * Connect to balldontlie API
     *
     * @return json array
     */
    private function fetch($service, $options = null)
    {
        $url = $this->api->url . $service;
        if($options) {
            $url = $url . "?";
            foreach($options as $key => $value) {
                if(is_array($value)) {
                    foreach($value as $option) {
                        $url = $url . $key . "[]=" . $option . "&";
                    }
                } else {
                    $url = $url . $key . "=" . $value . "&";
                }
            }
        }
        return Http::get($url)->json();
    }
}
