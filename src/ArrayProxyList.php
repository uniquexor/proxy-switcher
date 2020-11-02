<?php
    namespace unique\proxyswitcher;

    /**
     * Class ArrayProxyList.
     *
     * Implements an array list of the proxy servers.
     * Usage:
     * ```php```
     * new ArrayProxyList( [
     *    'username' => '',
     *    'password' => '',
     *    'transports' => [
     *        'my.proxy.com:80',
     *        'my2.proxy.com:80',
     *        ...
     *    ]
     * ] );
     * ```php```
     *
     * The proxies will be used starting from the first in the list. The proxies will be switched after a proxy encounters an error or some sort of
     * request limit imposed by the {@see Transport} object will be reached.
     *
     * @package unique\proxyswitcher
     */
    class ArrayProxyList extends AbstractProxyList {

        /**
         * The number of times to attempt a proxy before marking it as having failed.
         * Proxies will be attempted once with every cycle. It means, that when a proxy fails, the class will move on to the next proxy first
         * and will only retry the proxy after all others have been tried. This hopefully can prevent temporary server unavailability.
         * @var int
         */
        public $max_transport_fails = 3;
        
        /**
         * Counts the times that a particual proxy has failed.
         * Index is the same as {@see Transport::$transports} and value is the number of times failed.
         * @var array
         */
        public $transport_failure_count = [];

        /**
         * @inheritdoc
         */
        public function markFailed( \Throwable $error = null ) {

            if ( !isset( $this->transport_failure_count[ $this->current_transport ] ) ) {

                $this->transport_failure_count[ $this->current_transport ] = 0;
            }

            $this->transport_failure_count[ $this->current_transport ]++;

            if ( $this->transport_failure_count[ $this->current_transport ] >= $this->max_transport_fails ) {

                $this->markInvalid();
            }

            $this->switchTransport();
        }

        /**
         * @inheritdoc
         */
        public function getCurrentAddress(  $address_only = false ): string {

            if ( $address_only === true ) {
                
                return $this->transports[ $this->current_transport ];
            }
            
            return 'http://' . ( $this->username && $this->password ? urlencode( $this->username ) . ':' . urlencode( $this->password ) . '@' : '' ) .
                $this->transports[ $this->current_transport ];
        }

        /**
         * @inheritdoc
         */
        public function markTooManyRequests() {

            $this->markInvalid();
            $this->switchTransport();
        }
    }