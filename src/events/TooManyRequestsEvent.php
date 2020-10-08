<?php
    namespace unique\proxyswitcher\events;

    use unique\proxyswitcher\Transport;

    class TooManyRequestsEvent implements EventObjectInterface {

        use EventObjectTrait;

        public Transport $transport;
    }