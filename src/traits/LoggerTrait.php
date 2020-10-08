<?php
    namespace unique\proxyswitcher\events;

    trait LoggerTrait {

        protected $logger;

        public function setLogger( \Closure $logger ) {

            $this->logger = $logger;
        }

        protected function log( $text ) {

            if ( is_callable( $this->logger ) && $this->debug ) {

                call_user_func( $this->logger, $text );
            }
        }
    }