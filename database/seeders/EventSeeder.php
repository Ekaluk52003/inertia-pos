<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clear existing events
        Event::truncate();
        
        // Create sample events
        $events = [
            [
                'name' => 'Annual Conference 2025',
                'from_datetime' => Carbon::create(2025, 9, 15, 9, 0, 0),
                'to_datetime' => Carbon::create(2025, 9, 17, 18, 0, 0),
                'location' => 'Grand Convention Center',
            ],
            [
                'name' => 'Team Building Workshop',
                'from_datetime' => Carbon::create(2025, 8, 20, 10, 0, 0),
                'to_datetime' => Carbon::create(2025, 8, 20, 16, 0, 0),
                'location' => 'Mountain Retreat Center',
            ],
            [
                'name' => 'Product Launch',
                'from_datetime' => Carbon::create(2025, 10, 5, 14, 0, 0),
                'to_datetime' => Carbon::create(2025, 10, 5, 18, 0, 0),
                'location' => 'Downtown Exhibition Hall',
            ],
            [
                'name' => 'Tech Meetup',
                'from_datetime' => Carbon::create(2025, 8, 12, 18, 30, 0),
                'to_datetime' => Carbon::create(2025, 8, 12, 21, 0, 0),
                'location' => 'Innovation Hub',
            ],
            [
                'name' => 'End of Year Celebration',
                'from_datetime' => Carbon::create(2025, 12, 20, 19, 0, 0),
                'to_datetime' => Carbon::create(2025, 12, 21, 1, 0, 0),
                'location' => 'Skyline Hotel Ballroom',
            ],
        ];

        foreach ($events as $eventData) {
            Event::create($eventData);
        }
    }
}
