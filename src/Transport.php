<?php
    namespace unique\proxyswitcher;

    use GuzzleHttp\ClientInterface;
    use GuzzleHttp\Exception\GuzzleException;
    use GuzzleHttp\Promise\PromiseInterface;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;
    use unique\proxyswitcher\events\AfterResponseEvent;
    use unique\proxyswitcher\interfaces\EventHandlingInterface;
    use unique\proxyswitcher\traits\EventTrait;
    use unique\proxyswitcher\traits\LoggerTrait;
    use unique\proxyswitcher\traits\ObjectFactoryTrait;
    use unique\proxyswitcher\events\TooManyRequestsEvent;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\ClientException;
    use GuzzleHttp\Exception\ConnectException;
    use GuzzleHttp\Exception\RequestException;

    /**
     * Uses proxy switching to perform requests. Can easily be configured to use a single proxy or keep switching proxies after a specified amount of request,
     * failures, etc. Can also be interchangeably used with {@see \GuzzleHttp\Client}
     *
     * @package unique\proxyswitcher
     */
    class Transport implements EventHandlingInterface, ClientInterface {

        use EventTrait, ObjectFactoryTrait, LoggerTrait { LoggerTrait::setLogger as traitSetLogger; }

        const EVENT_AFTER_RESPONSE = 'on_after_response';
        const EVENT_TOO_MANY_REQUESTS = 'on_too_many_requests';

        const REQUEST_GET = 'GET';
        const REQUEST_POST = 'POST';

        /**
         * After a random amount (in the range of min/max) of requests a timeout will be made.
         * This can be used to confuse anti-scrape'ing measures.
         * In order to turn this off, set {@see $sleep_time_min} and {@see $sleep_time_max} to zero.
         */
        public $next_timeout_min = 2000;
        public $next_timeout_max = 5000;

        /**
         * The amount of seconds to sleep after a timeout has been reached.
         * The real amount of seconds to sleep will be randomized in this range.
         * Can be set to zero, if no such timeout needs to be made.
         */
        public $sleep_time_min = 2 * 60;
        public $sleep_time_max = 5 * 60;

        /**
         * Minimum and maximum requests between a proxy switch.
         * If both set to null, the proxy will only be switched once it fails.
         */
        public $switch_transport_min = 400;
        public $switch_transport_max = 800;

        /**
         * Specified how many proxies can be tried during a single request, before giving up and throwing an exception.
         * This can be used as a safety measure, so that in stead of endlessly going through all the proxies specified, assume that there is something
         * wrong after this many requests fail. (Like maybe internet connection failure, bad address, etc..)
         * @var int|null 
         */
        public $max_proxies_in_a_row = null;

        /**
         * Default connection timeout for each request.
         * Can be overriden by passing options to {@see request()} method.
         * @var int 
         */
        public $connect_timeout = 1;

        /**
         * Specifies the amount of seconds to wait after each successful request, in order to prevent flooding.
         * @var int
         */
        public $timeout_after_request = 1;

        /**
         * Defines if proxies need to be used.
         * @todo: refactor, currently has little use, it's true when proxy_list is provided, otherwise false.
         * @var bool
         */
        protected $use_proxy = false;

        /**
         * The amount of requests made after last proxy switch.
         * @var int
         */
        private $current_request = 0;

        /**
         * A randomized amount of requests to be made before switching proxies.
         * See {@see $switch_transport_min} and {@see $switch_transport_max} to control this
         * @var int|null
         */
        private $switch_transport_on = null;

        /**
         * The number of requests completed after the last timeout.
         * @var int
         */
        private $current_request_timeout = 0;

        /**
         * A randomized amount of requests to be made before making a timeout.
         * See {@see $next_timeout_min} and {@see $next_timeout_max} to control this
         * @var int
         */
        private $next_timeout_on = 0;

        /**
         * The amount of requests made since last "429 Too many requests"
         * @var int
         */
        protected $requests_from_last_429 = 0;

        /**
         * GuzzleHttp client.
         * @var Client $client
         */
        protected $client;

        /**
         * A cookie string to use for requests.
         * @var string $cookie
         */
        public $cookie = '';

        /**
         * Static instance of Transport class.
         * @see Transport::getInstance()
         * @var Transport
         */
        protected static $instance;

        /**
         * If specified, object will be used to control proxy switching.
         * You can pass array only during contruction of the Transport object, for proxy_list object to be constructed automatically.
         * In this case, array needs to contain a ['class'] key.
         * @var SingleProxyList|ArrayProxyList|array|null
         */
        public $proxy_list;

        /**
         * Transport constructor.
         * @param array $config - Provides configuration for Transport object construction.
         * @throws \Exception
         */
        public function __construct( $config = [] ) {

            $this->client = new \GuzzleHttp\Client();

            if ( isset( $config['proxy_list'] ) ) {

                $this->setProxyList( $this->createObject( $config[ 'proxy_list' ] ) );
                $this->use_proxy = true;
                unset( $config[ 'proxy_list' ] );
            } else {

                $this->use_proxy = false;
            }

            $this->initProperties( $config );

            $this->switch_transport_on = $this->getNewSwitchTransportOn();
            $this->next_timeout_on = $this->getNewNextTimeoutOn();
        }

        /**
         * Returns the number of requests that need to happen, before the next switch of proxy.
         * @return int|null
         */
        protected function getNewSwitchTransportOn() {

            if ( $this->switch_transport_min !== null && $this->switch_transport_max !== null ) {

                return rand( $this->switch_transport_min, $this->switch_transport_max );
            }

            return null;
        }

        /**
         * Returns the number of requests that needs to happen before the next timeout.
         * @return int
         */
        protected function getNewNextTimeoutOn() {

            return rand( $this->next_timeout_min, $this->next_timeout_max );
        }

        /**
         * Sets the provided proxy list.
         * @param SingleProxyList|ArrayProxyList|AbstractProxyList $proxy_list
         */
        public function setProxyList( AbstractProxyList $proxy_list ) {

            $this->proxy_list = $proxy_list;
            $this->use_proxy = true;
        }

        /**
         * Returns the assigned proxy list object.
         * @return AbstractProxyList
         */
        public function getProxyList() {

            return $this->proxy_list;
        }

        /**
         * Checks if the transport needs to be switched or if a timeout needs to be performed and does accordingly.
         * Afterwards, performs the request.
         *
         * @param string $url - URL
         * @param string $method - GET/POST method
         * @param array $options - Options to be provided to {@see \GuzzleHttp\Client::request()}
         * @return ResponseInterface
         * @throws GuzzleException
         */
        protected function doRequest( $url, $method = self::REQUEST_GET, $options = [] ) {

            $opt = $options;

            if ( $this->use_proxy && $this->switch_transport_on !== null && $this->current_request >= $this->switch_transport_on ) {

                $this->proxy_list->switchTransport();
                $this->current_request = 0;
                $this->switch_transport_on = $this->getNewSwitchTransportOn();
            }

            if ( $this->current_request_timeout >= $this->next_timeout_on ) {

                $time = rand( $this->sleep_time_min, $this->sleep_time_max );
                $this->log( 'Timing out for: ' . $time . ' seconds... ' );
                sleep( $time );
                $this->log( 'Done.' . "\r\n" );
                $this->current_request_timeout = 0;
                $this->next_timeout_on = $this->getNewNextTimeoutOn();
            }

            if ( isset( $opt['_connect_timeout'] ) ) {

                $opt['connect_timeout'] = $opt['_connect_timeout'];
                unset( $opt['_connect_timeout'] );
            } elseif ( !isset( $opt['connect_timeout'] ) ) {

                $opt[ 'connect_timeout' ] = $this->connect_timeout;
            }

            $opt['headers']['Cookie'] = $this->cookie;

            if ( $this->use_proxy ) {

                $opt[ 'proxy' ] = $this->proxy_list->getCurrentAddress();
            }

            if ( $method === self::REQUEST_GET ) {

                return $this->client->get(
                    $url,
                    $opt
                );
            } else {

                return $this->client->post(
                    $url,
                    $opt
                );
            }
        }

        /**
         * Makes a request to the url, using the provided request method GET/POST.
         * If a `proxy_list` object was provided during the construction of the object or using {@see setProxyList()} method, proxy switching logic will be
         * applied accordingly.
         *
         * @param string $method - GET or POST
         * @param \Psr\Http\Message\UriInterface|string $url - URL
         * @param array $options - Options to be provided to either {@see Client::get()} or {@see Client::post()}
         * @return ResponseInterface
         * @throws GuzzleException|\Throwable
         */
        public function request( string $method, $url, array $options = [] ): ResponseInterface {

            $count = 0;

            $this->requests_from_last_429++;
            $this->current_request++;
            $this->current_request_timeout++;

            do {

                $exception = null;
                $event = new AfterResponseEvent();

                try {

                    $event->response = $this->doRequest( $url, $method, $options );
                    $this->trigger( self::EVENT_AFTER_RESPONSE, $event );
                } catch ( ConnectException $exception ) {

                    if ( !$this->use_proxy || ( ( $this->max_proxies_in_a_row !== null ) && ( $count >= $this->max_proxies_in_a_row ) ) ) {

                        throw $exception;
                    }

                    if ( strpos( $exception->getMessage(), 'with 0 out of 0 bytes received' ) !== false && !isset( $options['_connect_timeout'] ) ) {

                        // I've noticed that sometimes, after having received this error, you can extend a connection timeout and it will work, so we try that,
                        // before failing
                        $options['_connect_timeout'] = $this->connect_timeout + 5;
                    } else {

                        unset( $options['_connect_timeout'] );
                        $this->log( $exception->getCode() . ': ' . $exception->getMessage() . "\r\n", true );
                        $this->proxy_list->markFailed( $exception );
                        $count++;
                    }
                } catch ( ClientException $exception ) {

                    unset( $options['_connect_timeout'] );
                    if ( $exception->getCode() == 429 ) {

                        $evt = new TooManyRequestsEvent();
                        $evt->transport = $this;
                        $this->trigger( self::EVENT_TOO_MANY_REQUESTS, $evt );

                        $this->log( '429 Too Many Requests (' . $this->requests_from_last_429 . ' since last 429)' . "\r\n", true );
                        $this->requests_from_last_429 = 0;

                        if ( !$evt->getHandled() ) {

                            $this->proxy_list->markTooManyRequests();
                        }
                    } else {

                        throw $exception;
                    }
                } catch ( RequestException $exception ) {

                    unset( $options['_connect_timeout'] );
                    $this->log( $exception->getCode() . ': ' . $exception->getMessage() . "\r\n", true );

                    if ( !$this->use_proxy || ( $this->max_proxies_in_a_row !== null && $count >= $this->max_proxies_in_a_row ) ) {

                        throw $exception;
                    }

                    if ( strpos( $exception->getMessage(), 'Received HTTP code 407 from proxy after CONNECT' ) !== false ) {

                        $this->proxy_list->markFailed( $exception );
                    } elseif ( strpos( $exception->getMessage(), 'Could not resolve proxy' ) !== false ) {

                        $this->proxy_list->markFailed( $exception );
                    } else {

                        throw $exception;
                    }

                    $count++;
                }
            } while ( $event->response === null );

            if ( $this->use_proxy ) {
                
                $this->proxy_list->markSuccess();
            }
            
            if ( $this->timeout_after_request ) {

                sleep( $this->timeout_after_request );
            }

            return $event->response;
        }

        /**
         * Returns a static instance of the Transport class.
         * @param array $config - public attributes of the Transport class object. Will only be set the first time it's called.
         * @return static
         * @throws \Exception
         */
        public static function getInstance( $config = [] ) {

            if ( self::$instance === null ) {

                self::$instance = new static( $config );
            }

            return self::$instance;
        }

        /**
         * Sets a logger function for transport and proxy_list.
         * A logger function will receive a single string parameter with a text that needs logging.
         * @param \Closure $logger
         */
        public function setLogger( \Closure $logger ) {

            $this->proxy_list->setLogger( $logger );
            $this->traitSetLogger( $logger );
        }

        /**
         * @inheritdoc
         */
        public function send( RequestInterface $request, array $options = [] ): ResponseInterface {

            throw new \Exception( 'Not Implemented.' );
        }

        /**
         * @inheritdoc
         */
        public function sendAsync( RequestInterface $request, array $options = [] ): PromiseInterface {

            throw new \Exception( 'Not Implemented.' );
        }

        /**
         * @inheritdoc
         */
        public function requestAsync( string $method, $uri, array $options = [] ): PromiseInterface {

            throw new \Exception( 'Not Implemented.' );
        }

        /**
         * @inheritdoc
         */
        public function getConfig( ?string $option = null ) {

            throw new \Exception( 'Not Implemented.' );
        }
    }