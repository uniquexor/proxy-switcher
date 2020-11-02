<?php
    namespace unique\proxyswitcher\events;

    use Psr\Http\Message\ResponseInterface;
    use unique\events\interfaces\EventObjectInterface;
    use unique\events\traits\EventObjectTrait;

    /**
     * Class AfterResponseEvent.
     *
     * An event thrown after a successfull request.
     *
     * @package unique\proxyswitcher\events
     */
    class AfterResponseEvent implements EventObjectInterface {

        use EventObjectTrait;

        /**
         * Response of the request.
         * @var ResponseInterface|null
         */
        public ? ResponseInterface $response = null;
    }