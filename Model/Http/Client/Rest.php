<?php
/*
 * Copyright © 2022 Studio Raz. All rights reserved.
 * See LICENCE file for license details.
 */

namespace SR\Gateway\Model\Http\Client;

use Laminas\Http\Request as HttpRequest;
use Magento\Framework\Phrase;
use SR\Gateway\Api\Http\Client\ClientInterface;
use SR\Gateway\Api\Http\ConverterInterface;
use SR\Gateway\Api\Http\TransferInterface;
use SR\Gateway\Api\LoggerInterface;
use SR\Gateway\Exception\ClientException;
use SR\Gateway\Model\Http\Adapter\CurlAdapterFactory as ClientAdapterFactory;
use SR\Gateway\Model\Request\ClientConfigBuilder;

class Rest implements ClientInterface
{
    protected LoggerInterface $logger;
    protected ClientAdapterFactory $clientAdapterFactory;
    protected ?ConverterInterface $converter = null;

    /**
     * @param LoggerInterface $logger
     * @param ClientAdapterFactory $clientAdapterFactory
     * @param ConverterInterface|null $converter
     */
    public function __construct(
        LoggerInterface $logger,
        ClientAdapterFactory $clientAdapterFactory,
        ?ConverterInterface $converter = null
    ) {
        $this->logger = $logger;
        $this->clientAdapterFactory = $clientAdapterFactory;
        $this->converter = $converter;
    }

    /**
     * @inheritDoc
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $log = [
            'client' => static::class,
            'client_config' => $transferObject->getClientConfig(),
            'endpoint_url' => $transferObject->getUri(),
            'headers' => $transferObject->getHeaders(),// NOTE: sometime it contains Secure info like Authorization
            'request_method' => $transferObject->getMethod(),
            //'user' => $transferObject->getAuthUsername(),// TODO: uncomment when it is needed
            //'password' => $transferObject->getAuthPassword(),// TODO: uncomment when it is needed
            'request' => $transferObject->getBody(),
        ];
        $response['object'] = [];
        $requestBody = '';

        try {
            $clientAdapter = $this->clientAdapterFactory->create();

            $clientConfig = $transferObject->getClientConfig();
            $options = $clientConfig[ClientConfigBuilder::PARAM_CURL_EXTRA_OPTIONS] ?? [];
            unset($clientConfig[ClientConfigBuilder::PARAM_CURL_EXTRA_OPTIONS]);

            $clientAdapter->setConfig($clientConfig);

            foreach ($options as $optionCode => $optionValue) {
                $clientAdapter->addOption($optionCode, $optionValue);
            }

            if ($transferObject->shouldEncode()) {
                $requestBody = $this->encodeBody($transferObject);
            }

            // NOTE: the TRICK to use PATCH method
            if ($transferObject->getMethod() === HttpRequest::METHOD_PATCH || $transferObject->getMethod() === HttpRequest::METHOD_GET) {
                $clientAdapter->addOption(CURLOPT_CUSTOMREQUEST, $transferObject->getMethod());
                $clientAdapter->addOption(CURLOPT_POSTFIELDS, $requestBody);
            } else if ($transferObject->getMethod() === HttpRequest::METHOD_DELETE) {
                $clientAdapter->addOption(CURLOPT_CUSTOMREQUEST, $transferObject->getMethod());
            }

            $log['request'] = $clientAdapter->write(
                $transferObject->getMethod(),
                $transferObject->getUri(),
                '1.1',
                $this->buildHeaders($transferObject),
                $requestBody
            );

            $httpResponse = $clientAdapter->singleExec();
            if ($errorMessage = $clientAdapter->getError()) {
                throw new ClientException(new Phrase('HTTP Adapter Error :: ' . $errorMessage));
            }

            if (empty($httpResponse->getBody()) && !in_array($httpResponse->getStatusCode(), [200, 201], true)) {
                throw new ClientException(new Phrase('HTTP Adapter Error :: ' . $httpResponse->getStatusCode() . ' : ' . $httpResponse->getReasonPhrase()));
            }

            $response['object'] = $this->converter ? $this->converter->convert($httpResponse->getBody()) : $httpResponse->getBody();
        } catch (\Exception $e) {
            $message = $e->getMessage() ?: 'Sorry, but something went wrong';
            $this->logger->critical($message);
            throw new ClientException(new Phrase($message), $e);
        } finally {
            // NOTE: destruct CURL resource
            $clientAdapter->close();

            $response['last_request'] = $requestBody;
            $response['last_response'] = isset($httpResponse) ? $httpResponse->getBody() : '';

            $log['response'] = $response['last_response'];
            $this->logger->debug($log);
        }

        return $response;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array
     */
    protected function buildHeaders(TransferInterface $transferObject): array
    {
        $headers = [];
        foreach ($transferObject->getHeaders() as $name => $value) {
            $headers[] = sprintf('%s: %s', $name, $value);
        }

        return $headers;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array|string
     */
    protected function encodeBody(TransferInterface $transferObject)
    {
        $rawBody = $transferObject->getBody();

        $headers = $transferObject->getHeaders();
        $contentType = $headers['Content-Type'] ?? null;

        if (empty($contentType)) {
            return $rawBody;
        }

        $exploded = explode(';', $contentType . ';');
        $encoding = '';

        if (mb_strpos($exploded[0] ?? '', '/') !== false) {
            [, $encoding] = explode('/', $exploded[0] ?? '');
        }

        switch ($encoding) {
            case 'x-www-form-urlencoded':
                return http_build_query($rawBody);

            case 'json':
                return \Safe\json_encode($rawBody);
        }

        return $rawBody;
    }
}
