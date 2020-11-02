<?php
    namespace unique\proxyswitcher;

    use GuzzleHttp\Exception\ConnectException;
    use GuzzleHttp\Exception\RequestException;
    use unique\proxyswitcher\traits\LoggerTrait;
    use unique\proxyswitcher\traits\ObjectFactoryTrait;

    /**
     * Class AbstractProxyList.
     *
     * Provides a basic functionality for proxy lists. Can be used to implement a specific logic for proxy switching.
     *
     * @package unique\proxyswitcher
     */
    abstract class AbstractProxyList {

        use LoggerTrait, ObjectFactoryTrait;

        /**
         * A username for a proxy server.
         * @var string
         */
        public ?string $username = null;

        /**
         * A password for a proxy server.
         * @var string
         */
        public ?string $password = null;

        /**
         * A list of proxies.
         * Full implementation depends on the extending class.
         * @var iterable
         */
        protected $transports = [];

        /**
         * Invalid proxies. These proxies will not be attempted again.
         * Stores an index to the {@see AbstractProxyList::$transports}.
         * Structure:
         * [
         *      (int) Index for the {@see AbstractProxyList::$transports} => (boolean) true,
         *      ...
         * ]
         * @var bool[]
         */
        protected $invalid_transports = [];

        /**
         * Current proxy to be used. Stores an index of the {@see AbstractProxyList::$transports}.
         * @var int
         */
        protected $current_transport = 0;

        /**
         * AbstractProxyList constructor.
         * Can be provided with a key-value pair list of public properties.
         *
         * @param array $config
         */
        public function __construct( $config = [] ) {

            if ( isset( $config['transports'] ) ) {

                $this->transports = $config['transports'];
                unset( $config['transports'] );
            }

            $this->initProperties( $config );
        }

        /**
         * Marks the transport as an invalid one, not to be used again.
         */
        public function markInvalid() {

            $this->invalid_transports[ $this->current_transport ] = true;
        }

        /**
         * Checks if an index of {@see AbstractProxyList::$transports} is in the invalid proxy list.
         * @param int $id - {@see AbstractProxyList::$transports} index
         * @return bool
         */
        public function isInvalid( int $id ) {

            return $this->invalid_transports[ $id ] ?? false;
        }

        /**
         * Returns an index of the current proxy being used from the {@see AbstractProxyList::$transports} list.
         * @return int
         */
        public function getCurrentId(): int {

            return $this->current_transport;
        }

        /**
         * Switches to a new proxy.
         */
        public function switchTransport() {

            $c = 0;

            do {

                // Ieško proxio, kuris yra ne invalid.
                if ( !next( $this->transports ) ) {

                    reset( $this->transports );
                }

                $this->current_transport = key( $this->transports );
                $c++;
            } while ( ( $c < count( $this->transports ) ) && $this->isInvalid( $this->current_transport ) );

            if ( $c >= count( $this->transports ) ) {

                throw new \Exception( 'No more transports available.' );
            }

            $this->log( 'Switching transport to: ' . $this->getCurrentAddress( true ) . ".\r\n" );
        }

        /**
         * Marks the current proxy as having completed a request successfully.
         * Can be overriden to do some logic.
         */
        public function markSuccess() {

            return;
        }

        /**
         * Marks the current proxy as having thrown a HTTP 429 code - Too many requests.
         * Should also switch to a new proxy, if available.
         */
        abstract public function markTooManyRequests();

        /**
         * Marks the current proxy as a failed one and switches to a new one.
         * A proxy is considered as a failed one if a {@see ConnectException} is thrown with the following errors:
         * - "Operation timed out after [XXX] milliseconds with 0 out of 0 bytes received..."
         * ...or if a {@see RequestException} is thrown with the following errors:
         * - "Received HTTP code 407 from proxy after CONNECT"
         * - "Could not resolve proxy"
         *
         * @param \Throwable $error - The exception being handled
         */
        abstract public function markFailed( \Throwable $error = null );

        /**
         * Forms the full address to be used as a proxy header for the request or just returns the address with the port number of the proxy, when
         * `$address_only` is true.
         *
         * The full address returned, should be in the form:
         * [SCHEMA]://[USERNAME]:[PASSWORD]@[ADDRESS]:[PORT]
         *
         * If `$address_only === true`, the address returned should be in the form:
         * [ADDRESS]:[PORT]
         *
         * @param bool $address_only - If true, the full proxy header will be returned, otherwise only the address with the port of the proxy.
         * @return string
         */
        abstract public function getCurrentAddress( $address_only = false ): string;
    }