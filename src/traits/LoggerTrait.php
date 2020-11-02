<?php
    namespace unique\proxyswitcher\traits;

    /**
     * Trait LoggerTrait
     * Provides some basic logging functionality. Allows to set a logging Closure and switch logging on or off, by specifing {@see $is_verbose} parameter.
     *
     * @package unique\proxyswitcher\traits
     */
    trait LoggerTrait {

        /**
         * A user specified closure to be used for logging. This will receive two parameters: log text and if it is an error.
         * @var \Closure
         */
        protected $logger;

        /**
         * Allows turning on or off logging.
         * @var bool
         */
        public $is_verbose = true;

        /**
         * Sets a Closure to be used for logging.
         * This will receive two parameters:
         * (string) log text, (bool) if the text is an error.
         *
         * @param \Closure $logger
         */
        public function setLogger( ? \Closure $logger ) {

            $this->logger = $logger;
        }

        /**
         * Logs text to a user specified Closure, set by {@see setLogger()}.
         * @param string $text
         * @param bool $is_error
         */
        protected function log( string $text, bool $is_error = false ) {

            if ( is_callable( $this->logger ) && $this->is_verbose ) {

                call_user_func( $this->logger, $text, $is_error );
            }
        }
    }