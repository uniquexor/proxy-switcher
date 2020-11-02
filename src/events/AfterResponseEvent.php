<?php
    namespace unique\proxyswitcher\events;

    use Psr\Http\Message\ResponseInterface;
    use unique\proxyswitcher\interfaces\EventObjectInterface;
    use unique\proxyswitcher\traits\EventObjectTrait;

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