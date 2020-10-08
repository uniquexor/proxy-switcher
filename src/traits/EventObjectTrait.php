<?php
    namespace unique\proxyswitcher\events;

    trait EventObjectTrait {

        protected bool $handled = false;

        public function setHandled( bool $value ) {

            $this->handled = $value;
        }

        public function getHandled(): bool {

            return $this->handled;
        }
    }