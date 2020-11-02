<?php
    namespace unique\proxyswitcherunit\data;

    class Transport extends \unique\proxyswitcher\Transport {

        public function setClient( $client ) {

            $this->client = $client;
        }

        public function getUseProxy() {

            return $this->use_proxy;
        }

        public function testLog( string $text, bool $is_error ) {

            $this->log( $text, $is_error );
        }
    }