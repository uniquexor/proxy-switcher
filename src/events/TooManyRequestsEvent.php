<?php
    namespace unique\proxyswitcher\events;

    use unique\proxyswitcher\traits\EventObjectTrait;
    use unique\proxyswitcher\Transport;
    use unique\proxyswitcher\interfaces\EventObjectInterface;

    /**
     * Class TooManyRequestsEvent.
     *
     * An event thrown after HTTP 429 Too Many Requests exception.
     *
     * @package unique\proxyswitcher\events
     */
    class TooManyRequestsEvent implements EventObjectInterface {

        use EventObjectTrait;

        /**
         * Transport instance that initiated the event.
         * @var Transport
         */
        public Transport $transport;
    }