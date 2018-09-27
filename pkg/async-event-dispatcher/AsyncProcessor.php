<?php

namespace Enqueue\AsyncEventDispatcher;

use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Consumption\Result;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrProcessor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AsyncProcessor implements PsrProcessor, CommandSubscriberInterface
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var AsyncEventDispatcher
     */
    private $dispatcher;

    public function __construct(Registry $registry, EventDispatcherInterface $dispatcher)
    {
        $this->registry = $registry;

        if (false == $dispatcher instanceof AsyncEventDispatcher) {
            throw new \InvalidArgumentException(sprintf(
                'The dispatcher argument must be instance of "%s" but got "%s"',
                AsyncEventDispatcher::class,
                get_class($dispatcher)
            ));
        }

        $this->dispatcher = $dispatcher;
    }

    public function process(PsrMessage $message, PsrContext $context)
    {
        if (false == $eventName = $message->getProperty('event_name')) {
            return Result::reject('The message is missing "event_name" property');
        }
        if (false == $transformerName = $message->getProperty('transformer_name')) {
            return Result::reject('The message is missing "transformer_name" property');
        }

        $event = $this->registry->getTransformer($transformerName)->toEvent($eventName, $message);

        $this->dispatcher->dispatchAsyncListenersOnly($eventName, $event);

        return self::ACK;
    }

    public static function getSubscribedCommand()
    {
        return Commands::DISPATCH_ASYNC_EVENTS;
    }
}
