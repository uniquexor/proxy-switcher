<?php
    namespace unique\proxyswitcher\traits;

    /**
     * Trait EventObjectTrait.
     * Provides basic functionality for an event object.
     *
     * @package unique\proxyswitcher\traits
     */
    trait EventObjectTrait {

        /**
         * If the event has been handled and should not be processed any further.
         * @var bool
         */
        protected bool $handled = false;

        /**
         * Sets if the event has been handled and should not be processed any further.
         * @param bool $value
         */
        public function setHandled( bool $value ) {

            $this->handled = $value;
        }

        /**
         * Returns if the event has been handled and should not be processed any further.
         * @return bool
         */
        public function getHandled(): bool {

            return $this->handled;
        }
    }