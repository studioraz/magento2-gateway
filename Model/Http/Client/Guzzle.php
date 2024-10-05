<?php
/*
 * Copyright © 2024 Studio Raz. All rights reserved.
 * See LICENCE file for license details.
 */

declare(strict_types=1);

namespace SR\Gateway\Model\Http\Client;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\ClientFactory as GuzzleClientFactory;
use GuzzleHttp\Client;
use SR\Gateway\Api\Http\TransferInterface;
use SR\Gateway\Api\Http\ConverterInterface;
use SR\Gateway\Exception\ClientException;
use SR\Gateway\Api\Http\Client\ClientInterface;
use SR\Gateway\Api\LoggerInterface;


class Guzzle extends Client implements ClientInterface
{
    protected LoggerInterface $logger;
    protected GuzzleClientFactory $clientFactory;
    protected ?ConverterInterface $converter = null;

    /**
     * @param LoggerInterface $logger
     * @param GuzzleClientFactory $clientFactory
     * @param ConverterInterface|null $converter
     */
    public function __construct(
        LoggerInterface $logger,
        GuzzleClientFactory $clientFactory,
        ConverterInterface $converter = null
    ) {
        $this->logger           = $logger;
        $this->clientFactory    = $clientFactory;
        $this->converter        = $converter;
    }


    /**
     * @param TransferInterface $transferObject
     * @return array
     * @throws ClientException
     */
    public function placeRequest(TransferInterface $transferObject) : array
    {
        $response['object'] = [];
        $log = [
            'request'       => json_encode($transferObject->getBody(), JSON_UNESCAPED_SLASHES),
            'request_uri'   => $transferObject->getUri()
        ];

        try {
            $httpResponse       = $this->doRequest($transferObject);
            $response['object'] = $this->converter->convert($httpResponse->getBody()->getContents());
        } catch (\Exception $e) {
            $log['response'] = __($e->getMessage());
            throw new ClientException(__($e->getMessage()));
        } finally {
            $response['last_request']   = $transferObject->getBody();
            $log['response']            = $response['last_response'] = isset($httpResponse) ? $httpResponse->getBody() : '';
            $log['response_object']     = $response['object'];
            $this->logger->debug($log);
        }

        return $response;
    }

    private function doRequest(TransferInterface $transferObject): ResponseInterface
    {
        try {
            $client = $this->clientFactory->create();
            $response = $client->request(
                $transferObject->getMethod(),
                $transferObject->getUri(),
                $this->getOptions($transferObject)
            );
        } catch (GuzzleException $exception) {
            throw new ClientException(__($exception->getMessage()));
        }

        return $response;
    }

    private function getOptions($transferObject): array
    {
        $options = [];
        if (isset($transferObject->getHeaders()['Authorization'])) {
            $options['headers']['Authorization'] = $transferObject->getHeaders()['Authorization'];
        }

        if ($transferObject->getBody()) {
            $options['json'] = $transferObject->getBody();
        }

        return $options;
    }

}
