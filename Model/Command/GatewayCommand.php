<?php
/*
 * Copyright © 2022 Studio Raz. All rights reserved.
 * See LICENCE file for license details.
 */

namespace SR\Gateway\Model\Command;

use Magento\Framework\Phrase;
use SR\Gateway\Api\CommandInterface;
use SR\Gateway\Api\ErrorMapper\ErrorMessageMapperInterface;
use SR\Gateway\Api\Http\Client\ClientFactoryInterface;
use SR\Gateway\Api\Http\TransferFactoryInterface;
use SR\Gateway\Api\LoggerInterface;
use SR\Gateway\Api\Request\BuilderInterface;
use SR\Gateway\Api\Response\HandlerInterface;
use SR\Gateway\Api\Validator\ResultInterface;
use SR\Gateway\Api\Validator\ValidatorInterface;
use SR\Gateway\Exception\ClientException;
use SR\Gateway\Exception\CommandException;
use SR\Gateway\Exception\RequestBuilderException;
use SR\Gateway\Exception\ResponseHandlerException;
use SR\Gateway\Exception\TransferBuilderException;
use SR\Gateway\Api\Response\DataModifierInterface;

/**
 * Class GatewayCommand
 * @package SR\Gateway\Model\Command
 */
class GatewayCommand implements CommandInterface
{
    protected BuilderInterface $requestBuilder;
    protected TransferFactoryInterface $transferFactory;
    protected ClientFactoryInterface $clientFactory;
    protected LoggerInterface $logger;
    protected ?HandlerInterface $handler = null;
    protected ?ValidatorInterface $validator = null;
    protected ?ErrorMessageMapperInterface $errorMessageMapper = null;
    private ?DataModifierInterface $dataModifier;

    /**
     * @param BuilderInterface                $requestBuilder
     * @param TransferFactoryInterface        $transferFactory
     * @param ClientFactoryInterface          $clientFactory
     * @param LoggerInterface                 $logger
     * @param HandlerInterface|null           $handler
     * @param ValidatorInterface|null         $validator
     * @param ErrorMessageMapperInterface|null $errorMessageMapper
     * @param DataModifierInterface            $dataModifier
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientFactoryInterface $clientFactory,
        LoggerInterface $logger,
        ?HandlerInterface $handler = null,
        ?ValidatorInterface $validator = null,
        ?ErrorMessageMapperInterface $errorMessageMapper = null,
        ?DataModifierInterface $dataModifier = null
    ) {
        $this->requestBuilder    = $requestBuilder;
        $this->transferFactory   = $transferFactory;
        $this->clientFactory     = $clientFactory;
        $this->logger            = $logger;
        $this->handler           = $handler;
        $this->validator         = $validator;
        $this->errorMessageMapper= $errorMessageMapper;
        $this->dataModifier = $dataModifier;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject): ?ResultInterface
    {
        $result    = null;
        $transferO = null;

        try {
            // 1) build and send request
            $transferO = $this->transferFactory->create(
                $this->requestBuilder->build($commandSubject)
            );
            $client   = $this->clientFactory->create($commandSubject);
            $response = $client->placeRequest($transferO);

            // 2) validate
            if ($this->validator !== null) {
                $validationSubject = array_merge($commandSubject, ['response' => $response]);
                $result = $this->validator->validate($validationSubject);
                if (!$result->isValid()) {
                    $this->processErrors($result);
                }
            }

            // 3) handle
            if ($this->handler !== null) {
                $this->handler->handle($commandSubject, $response);
            }

            if ($this->dataModifier !== null && $result !== null) {
                $this->dataModifier->modify($commandSubject, $result);
            }

        } catch (
        RequestBuilderException |
        TransferBuilderException |
        ResponseHandlerException |
        ClientException $e
        ) {
            $this->logger->debug($e->getMessage());
            throw new CommandException(new Phrase($e->getMessage()), $e);
        }

        return $result;
    }

    /**
     * Tries to map error messages from validation result and logs processed message.
     * Throws an exception with mapped message or default error.
     *
     * @param ResultInterface $result
     * @throws CommandException
     */
    protected function processErrors(ResultInterface $result): void
    {
        $messages = [];
        foreach ($result->getFailsDescription() as $fail) {
            $code    = '';
            $message = null;
            if (is_array($fail)) {
                $code    = (string)($fail['code'] ?? '');
                $message = $fail['message'] ?? '';
                $fail    = implode('::', $fail);
            } else {
                $message = $fail instanceof Phrase ? $fail->getText() : (string)$fail;
            }

            // map code → message if mapper provided
            $mapped = $this->errorMessageMapper
                ? (string)$this->errorMessageMapper->getMessage($code)
                : $code;
            $mapped = $mapped === $code ? $fail : $mapped;

            $messages[] = (new Phrase($mapped ?: $message))->render();
            $this->logger->debug(new Phrase('Gateway Error :: %1', [$message]));
        }

        throw new CommandException(
            !empty($messages)
                ? new Phrase(implode(PHP_EOL, $messages))
                : new Phrase('Request has been declined. Please try again later.')
        );
    }
}
