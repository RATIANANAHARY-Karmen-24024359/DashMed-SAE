<?php

declare(strict_types=1);

namespace Tests\Services;

use modules\services\DownsamplingService;
use PHPUnit\Framework\TestCase;

final class DownsamplingServiceTest extends TestCase
{
    private DownsamplingService $service;

    protected function setUp(): void
    {
        $this->service = new DownsamplingService();
    }

    /** @return array<int, array{time_iso: string, value: string, flag: int}> */
    private function generateDummyData(int $count): array
    {
        $data = [];
        $startTime = time();
        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'time_iso' => date('c', $startTime + $i * 60),
                'value'    => (string) (sin($i / 10) * 50 + 50),
                'flag'     => 0,
            ];
        }
        return $data;
    }

    /** @param array<int, array{time_iso: string, value: string, flag: int}> $data */
    private function generateStream(array $data): \Generator
    {
        foreach ($data as $row) {
            yield $row;
        }
    }

    public function testArrayDownsamplingPreservesEndpointsAndThreshold(): void
    {
        $data = $this->generateDummyData(1000);
        $threshold = 100;

        $sampled = $this->service->downsampleLTTB($data, $threshold);

        self::assertCount($threshold, $sampled);
        self::assertSame($data[0], $sampled[0]);
        self::assertSame(end($data), end($sampled));
    }

    public function testStreamDownsamplingPreservesEndpointsAndThreshold(): void
    {
        $dataCount = 1000;
        $data = $this->generateDummyData($dataCount);
        $stream = $this->generateStream($data);
        $threshold = 100;

        $sampled = $this->service->downsampleLTTBStream($stream, $dataCount, $threshold);

        self::assertCount($threshold, $sampled);
        self::assertSame($data[0], $sampled[0]);
        self::assertSame(end($data), end($sampled));
    }

    public function testEdgeCases(): void
    {
        $data = $this->generateDummyData(5);

        // threshold >= count -> should return original
        $sampled = $this->service->downsampleLTTB($data, 10);
        self::assertSame($data, $sampled);

        // threshold < 3 -> still should be safe (implementation dependent, but must not error)
        $sampled2 = $this->service->downsampleLTTB($data, 2);
        self::assertNotEmpty($sampled2);
    }
}
