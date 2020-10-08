<?php
    namespace unique\proxyswitcher\events;

    interface EventObjectInterface {

        public function setHandled( bool $value );

        public function getHandled(): bool;
    }