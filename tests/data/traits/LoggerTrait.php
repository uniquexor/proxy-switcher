<?php
    namespace unique\proxyswitcherunit\data\traits;

    class LoggerTrait {

        use \unique\proxyswitcher\traits\LoggerTrait {
            log as logTrait;
        }

        public function log( string $text, bool $is_error = false ) {

            $this->logTrait( $text, $is_error );
        }
    }