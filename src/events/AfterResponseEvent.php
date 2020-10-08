<?php
    namespace unique\proxyswitcher\events;

    use Psr\Http\Message\ResponseInterface;

    class AfterResponseEvent implements EventObjectInterface {

        use EventObjectTrait;

        public ? ResponseInterface $response = null;

        public ? string $error = null;
    }