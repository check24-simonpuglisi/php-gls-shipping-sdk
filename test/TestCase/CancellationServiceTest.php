<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace C24Toys\GLS\Sdk\ParcelProcessing\Service;

use C24Toys\GLS\Sdk\ParcelProcessing\Exception\DetailedServiceException;
use C24Toys\GLS\Sdk\ParcelProcessing\Exception\ServiceException;
use C24Toys\GLS\Sdk\ParcelProcessing\Http\HttpServiceFactory;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Mock\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function file_get_contents;

class CancellationServiceTest extends TestCase
{
    /**
     * @return string[][]|callable[][]
     */
    public function multiParcelDataProvider(): array
    {
        return [
            'mixed' => [
                'request' => ['12345678901', '12345678902', '1234567890'],
                'response' => file_get_contents(__DIR__ . '/../Provider/_files/200_cancellation_response.json'),
                'expected' => ['12345678901', '12345678902'],
            ],
        ];
    }

    /**
     * @return string[][]
     */
    public function singleParcelErrorProvider(): array
    {
        return [
            'failure' => [
                'request' => '1234567890',
                'response' => file_get_contents(__DIR__ . '/../Provider/_files/200_cancellation_error.json'),
                'expected' => DetailedServiceException::class,
            ],
            'success' => [
                'request' => '12345678901',
                'response' => file_get_contents(__DIR__ . '/../Provider/_files/200_cancellation_success.json'),
                'expected' => '',
            ],
        ];
    }

    /**
     * Perform valid request, assert shipment response details.
     *
     * @test
     * @dataProvider multiParcelDataProvider
     *
     * @param string[] $requestedIds
     * @param string $responseBody
     * @param string[] $expected
     * @throws ServiceException
     */
    public function cancelMultiParcel(array $requestedIds, string $responseBody, array $expected): void
    {
        $logger = new NullLogger();
        $httpClient = new Client();
        $responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $httpClient->setDefaultResponse(
            $responseFactory
                ->createResponse(200, 'OK')
                ->withBody($streamFactory->createStream($responseBody))
        );

        $serviceFactory = new HttpServiceFactory($httpClient);
        $service = $serviceFactory->createCancellationService('u5er', 'p4ss', $logger, true);

        $cancelled = $service->cancelParcels($requestedIds);
        self::assertSame($expected, $cancelled);
    }

    /**
     * Perform request, assert ID is returned on success or exception is thrown otherwise.
     *
     * @test
     * @dataProvider singleParcelErrorProvider
     *
     * @param string $requestedId
     * @param string $responseBody
     * @param string $errorType
     * @throws ServiceException
     */
    public function cancelSingleParcel(string $requestedId, string $responseBody, string $errorType): void
    {
        if ($errorType) {
            $this->expectException($errorType);
        }

        $logger = new NullLogger();
        $httpClient = new Client();
        $responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $httpClient->setDefaultResponse(
            $responseFactory
                ->createResponse(200, 'OK')
                ->withBody($streamFactory->createStream($responseBody))
        );

        $serviceFactory = new HttpServiceFactory($httpClient);
        $service = $serviceFactory->createCancellationService('u5er', 'p4ss', $logger, true);

        $cancelledId = $service->cancelParcel($requestedId);
        if (!$errorType) {
            self::assertSame($requestedId, $cancelledId);
        }
    }
}
