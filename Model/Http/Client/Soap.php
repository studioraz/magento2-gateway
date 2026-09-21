<?php
/*
 * Copyright © 2022 Studio Raz. All rights reserved.
 * See LICENCE file for license details.
 */

namespace SR\Gateway\Model\Http\Client;

use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Soap\ClientFactory as ClientAdapterFactory;
use SR\Gateway\Api\Http\Client\ClientInterface;
use SR\Gateway\Api\Http\ConverterInterface;
use SR\Gateway\Api\Http\TransferInterface;
use SR\Gateway\Api\LoggerInterface;
use SR\Gateway\Exception\ClientException;
use SR\Gateway\Model\Request\ClientConfigBuilder;

class Soap implements ClientInterface
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
            'headers' => $transferObject->getHeaders(),
            'request_method' => $transferObject->getMethod(),
            'location' => '',
            //'user' => $transferObject->getAuthUsername(),// TODO: uncomment when it is needed
            //'password' => $transferObject->getAuthPassword(),// TODO: uncomment when it is needed
            'request' => $transferObject->getBody()
        ];
        $response['object'] = [];

        try {
            $wsdl = $transferObject->getClientConfig()[ClientConfigBuilder::PARAM_WSDL] ?? null;

            /** @var \SoapClient $clientAdapter */
            $clientAdapter = $this->clientAdapterFactory->create($wsdl, [
                    'trace' => true
                ]
            );

            /**
             * URI of the WSDL file or NULL if working in non-WSDL mode.
             * Force set location in case URI include query string params.
             * NOTE: set the endpoint URL that will be touched by following SOAP requests.
             * Calling this method is optionael. The SoapClient uses the endpoint from the WSDL file by default.
             */
            if ($wsdl === null || $this->hasQueryString($transferObject->getUri())) {
                $log['location'] = $transferObject->getUri();
                $clientAdapter->__setLocation($transferObject->getUri());
            }

            $clientAdapter->__setSoapHeaders($this->buildSoapHeaders($transferObject));

            $result = $clientAdapter->__soapCall(
                $transferObject->getClientConfig()[ClientConfigBuilder::PARAM_SOAP_FUNCTION_NAME],
                [$transferObject->getBody()]
            );

            if ($this->converter !== null) {
                $result = $this->converter->convert($result);
            }

            $response['object'] = $result;
        } catch (\SoapFault $fault) {
            $message = $fault->getCode() . ' ' . $fault->getMessage();
            $this->logger->critical($message);

            // HOOK
            // @see: https://bugs.php.net/bug.php?id=47584
            // SoapFault exception could not be caught. It is always passed forward
            error_clear_last();

            throw new ClientException(new Phrase($message));
        } catch (\Exception $e) {
            $message = $e->getMessage() ?: 'Sorry, but something went wrong';
            $this->logger->critical($message);
            throw new ClientException(new Phrase($message), $e);
        } finally {
            if (isset($clientAdapter)) {
                $response['last_request'] = $clientAdapter->__getLastRequest();
                $response['last_response'] = $clientAdapter->__getLastResponse();

                $log['response'] = $response['last_response'];
                $this->logger->debug('-----------------------------------');
                $this->logger->debug($log);
            }
        }

        return $response;
    }

    /**
     * Check if a URI contains a query string.
     *
     * @param string $uri The URI to check.
     * @return bool True if the URI contains a query string, false otherwise.
     */
    function hasQueryString(string $uri): bool
    {
        $parsedUri = parse_url($uri);
        return isset($parsedUri['query']) && $parsedUri['query'] !== '';
    }


    /**
     * Returns list of applicable SOAP Headers
     *
     * @param TransferInterface $transferObject
     *
     * @return \SoapHeader[]|null
     */
    protected function buildSoapHeaders(TransferInterface $transferObject): ?array
    {
        $headers = null;

        $clientConfig = $transferObject->getClientConfig();
        if (!isset($clientConfig[ClientConfigBuilder::PARAM_SOAP_HEADERS])) {
            return $headers;
        }

        // TODO: implement logic to build Soap Headers when it is needed
        // see: https://www.php.net/manual/en/class.soapheader.php

        return $headers;
    }
}
