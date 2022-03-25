<?php

namespace Astroselling\FalabellaSdk\Database\Factories;

use Astroselling\FalabellaSdk\Models\FalabellaFeed;
use Illuminate\Database\Eloquent\Factories\Factory;

class FalabellaFeedFactory extends Factory
{
    protected $model = FalabellaFeed::class;

    public function definition()
    {
        $totalRecords = $this->faker->randomNumber();
        $processedRecords = $totalRecords - $this->faker->numberBetween(0, $totalRecords);
        $failedRecords = $processedRecords - $this->faker->numberBetween(0, $processedRecords);

        return [
            'feed_id' => $this->faker->uuid(64),
            'status' => $this->faker->randomElement([
                FalabellaFeed::STATUS_QUEUED,
                FalabellaFeed::STATUS_PROCESSING,
                FalabellaFeed::STATUS_CANCELED,
                FalabellaFeed::STATUS_FINISHED,
            ]),
            'source' => 'api',
            'action' => $this->faker->randomElement([
                'ProductCreate',
                'ProductUpdate',
                'ProductDelete',
            ]),
            'creation_date' => now(),
            'updated_date' => now(),
            'total_records' => $totalRecords,
            'processed_records' => $processedRecords,
            'failed_records' => $failedRecords,
            'errors' => json_encode(
                $this->faker->randomElement(
                    [],
                    [],
                    [
                        [
                            'Message' => 'There was an error'
                        ]
                    ],
                )
            ),
            'warnings' => json_encode(
                $this->faker->randomElement(
                    [],
                    [],
                    [
                        [
                            'Message' => 'There was an error'
                        ]
                    ],
                )
            ),
            'failure_reports' => json_encode(
                $this->faker->randomElement(
                    [],
                    [],
                    [
                        [
                            'Message' => 'There was an error'
                        ]
                    ],
                )
            ),
        ];
    }
}
