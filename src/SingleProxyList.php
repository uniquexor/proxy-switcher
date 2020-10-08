<?php
    namespace unique\proxyswitcher;

    class SingleProxyList extends AbstractProxyList {

        public $address;

        public function markTooManyRequests() {

            throw new \Exception( 'Got 429 Too Many Requests.' );
        }

        public function markFailed( \Throwable $error = null ) {

            throw $error ?? new \Exception();
        }

        public function markSuccess() {

            return;
        }

        public function switchTransport() {

            $this->log( 'Can\'t switch transport, sleeping for 60s...' . ".\r\n" );
            sleep( 60 );
            $this->log( 'Continuing...' . "\r\n" );

            return;
        }

        public function getCurrentAddress(): string {

            return 'http://' . ( $this->username && $this->password ? urlencode( $this->username ) . ':' . urlencode( $this->password ) . '@' : '' ) .
                $this->address;
        }
    }