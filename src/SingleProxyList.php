<?php
    namespace unique\proxyswitcher;

    /**
     * Class SingleProxyList.
     *
     * Implements a single proxy "list". The address of the proxy should be provided by setting the public attribute {@see $address}.
     * Usage:
     * ```php```
     * new SingleProxyList( [
     *    'username' => '',
     *    'password' => '',
     *    'schema' => 'http',
     *    'address' => 'my.proxy.com:80',
     * ] )
     * ```php```
     *
     * @package unique\proxyswitcher
     */
    class SingleProxyList extends AbstractProxyList {

        /**
         * Specifies a schema to be used with the proxy address.
         * @var string
         */
        public $schema = 'http';

        /**
         * The address and the port number of the proxy to be used.
         * @var string
         */
        public $address;

        /**
         * @inheritdoc
         */
        public function markTooManyRequests() {

            throw new \Exception( 'Got 429 Too Many Requests.' );
        }

        /**
         * @inheritdoc
         */
        public function markFailed( \Throwable $error = null ) {

            throw ( $error ?? new \Exception( 'A proxy has failed.' ) );
        }

        /**
         * @inheritdoc
         */
        public function switchTransport() {

            $this->log( 'Can\'t switch transport, sleeping for 60s...' . "\r\n" );
            sleep( 60 );
            $this->log( 'Continuing...' . "\r\n" );

            return;
        }

        /**
         * @inheritdoc
         */
        public function getCurrentAddress( $address_only = false ): string {

            if ( $address_only === true ) {
                
                return $this->address;
            }
            
            return $this->schema . '://' .
                ( $this->username && $this->password ? urlencode( $this->username ) . ':' . urlencode( $this->password ) . '@' : '' ) .
                $this->address;
        }
    }