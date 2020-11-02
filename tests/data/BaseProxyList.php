<?php
    namespace unique\proxyswitcherunit\data;

    use unique\proxyswitcher\AbstractProxyList;
    use unique\proxyswitcher\traits\LoggerTrait;

    class BaseProxyList extends AbstractProxyList {

        use LoggerTrait;

        public function markTooManyRequests() {

        }

        public function markFailed( \Throwable $error = null ) {

        }

        public function getCurrentAddress( $address_only = false ): string {

            return '';
        }

        public function getTransports() {

            return $this->transports;
        }

        public function testLog( string $text, bool $is_error ) {

            $this->log( $text, $is_error );
        }
    }