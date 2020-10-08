<?php
    namespace unique\proxyswitcher;

    use unique\proxyswitcher\events\AfterResponseEvent;
    use unique\proxyswitcher\events\EventHandlingInterface;
    use unique\proxyswitcher\events\EventTrait;
    use unique\proxyswitcher\events\LoggerTrait;
    use unique\proxyswitcher\events\ObjectFactoryTrait;
    use unique\proxyswitcher\events\TooManyRequestsEvent;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\ClientException;
    use GuzzleHttp\Exception\ConnectException;
    use GuzzleHttp\Exception\RequestException;

    /**
     * Užklausų vykdymo wrapper'is, kuris pasirūpina, kad užklausos būtų vykdomos per proxy serverius ir kad jie būtų reguliariai keičiami.
     *
     * @package app\components
     */
    class Transport implements EventHandlingInterface {

        use EventTrait, ObjectFactoryTrait, LoggerTrait;

        const EVENT_AFTER_RESPONSE = 'on_after_response';
        const EVENT_TOO_MANY_REQUESTS = 'on_too_many_requests';

        const REQUEST_GET = 'GET';
        const REQUEST_POST = 'POST';

        /**
         * Kiek turi įvykti request'ų, kad būtų padaromas timeout'as.
         * Tikrasis request'ų kiekis bus išrandomize'intas pagal šiuos parametrus.
         */
        public $next_timeout_min = 2000;
        public $next_timeout_max = 5000;

        /**
         * Kiek laiko timeout'inti.
         * Tikrasis timeout'as bus išrandomize'intas pagal šiuos parametrus.
         */
        public $sleep_time_min = 2 * 60;
        public $sleep_time_max = 5 * 60;

        /**
         * Kiek turi įvykti request'ų, kad būtų pakeistas proxy.
         * Tikrasis request'ų kiekis bus išrandomize'intas pagal šiuos parametrus.
         */
        public $switch_transport_min = 400;
        public $switch_transport_max = 800;

        public $connect_timeout = 1;

        public $sleep_after_download = 1;

        /**
         * Naudoti Proxy request'ams.
         * @var bool
         */
        public $use_proxy = true;

        /**
         * Saugo kiek įvyko request'ų nuo paskutiniojo proxio pakeitimo.
         * @var int
         */
        private $current_download = 0;

        /**
         * Saugo kiek turi įvykti request'ų su parinktuoju proxiu, kad jis būtų pakeistas į naują.
         * @var int|null
         */
        private $switch_transport_on = null;

        /**
         * Saugo kiek jau yra įvykę request'ų nuo paskutinio timeout'o.
         * @var int
         */
        private $current_download_timeout = 0;

        /**
         * Saugo kiek reikia įvykdyti request'ų, kad įvyktų timeout'as.
         * @var int
         */
        private $next_timeout = 0;

        protected $requests_from_last_429 = 0;

        /**
         * GuzzleHttp klientas.
         * @var Client $client
         */
        protected $client;

        /**
         * Sesijos ID gautas po {@see Transport::reauth()}
         * @var string $cookie
         */
        public $cookie = '';

        /**
         * Statinis instance'as.
         * @see Transport::getInstance()
         * @var Transport
         */
        protected static $instance;

        /**
         * @var AbstractProxyList
         */
        public $proxy_list;

        public $debug = false;

        // public $container;

        protected $logger;

        /**
         * Transport constructor.
         * @throws \Exception
         */
        public function __construct( $config = [] ) {

            /* $this->container = [];
            $history = Middleware::history( $this->container );
            $handler_stack = HandlerStack::create();
            $handler_stack->push( $history ); */

            $this->client = new \GuzzleHttp\Client(); // [ 'handler' => $handler_stack ] );

            $this->proxy_list = $this->createObject( $config['proxy_list'] );
            unset( $config['proxy_list'] );

            self::initObject( $this, $config );

            // $this->proxy_list = new ArrayProxyList( require __DIR__ . '/../../config/proxy-list.php' );
            // $this->proxy_list = new DbProxyList();
            // $this->proxy_list = new SingleProxyList( 'no88.nordvpn.com:80' );

            $this->switch_transport_on = rand( $this->switch_transport_min, $this->switch_transport_max );
            $this->next_timeout = rand( $this->next_timeout_min, $this->next_timeout_max );
        }

        /**
         * Priskiria pateiktąjį objektą, kaip ProxyList'ą.
         * @param AbstractProxyList $proxy_list
         */
        public function setProxyList( AbstractProxyList $proxy_list ) {

            $this->proxy_list = $proxy_list;
        }

        /**
         * Gražina ProxyList objektą.
         * @return AbstractProxyList
         */
        public function getProxyList() {

            return $this->proxy_list;
        }

        /**
         * Pakeičia naudojamą Proxy į sekantį.
         * @throws \Exception
         */
        public function switchTransport() {

            $this->proxy_list->switchTransport();

            $this->current_download = 0;
            $this->switch_transport_on = rand( $this->switch_transport_min, $this->switch_transport_max );
        }

        /**
         * Gražina Cookie string'ą (paimtas iš Google Earth užklausų).
         * Prasmė nelabai aiški, bet be jo neveikia.
         * Tuo pačiu paduoda ir Session ID, gautą auth metu.
         * @return string
         */
        protected function getCookie() {

            return $this->cookie;
        }

        /**
         * GET užklausa.
         *
         * Jeigu reikia (t.y. su šiuo proxiu įvyko daugiau užklausų nei Transport::$switch_transport_on), pakeičia transport'ą.
         * Jeigu reikia (t.y. įvyko daugiau užklausų nei Transport::$next_timeout), suspend'ina script'ą iki 10 min.
         *
         * @param string $url
         * @param array $options - žr {@see \GuzzleHttp\Client::get()}
         * @return \Psr\Http\Message\ResponseInterface
         * @throws \Exception
         */
        protected function get( $url, $options = [] ) {

            return $this->client->get(
                $url,
                $options
            );
        }

        /**
         * POST užklausa.
         *
         * @param string $url
         * @param array $options - žr {@see \GuzzleHttp\Client::post()}
         * @return \Psr\Http\Message\ResponseInterface
         */
        protected function post( $url, $options = [] ) {

            return $this->client->post(
                $url,
                $options
            );
        }

        protected function doRequest( $url, $method = self::REQUEST_GET, $options = [] ) {

            $opt = $options;

            $this->requests_from_last_429++;
            $this->current_download++;
            $this->current_download_timeout++;

            if ( $this->current_download >= $this->switch_transport_on ) {

                $this->switchTransport();
            }

            if ( $this->current_download_timeout >= $this->next_timeout ) {

                $time = rand( $this->sleep_time_min, $this->sleep_time_max );
                echo 'Timing out for: ' . $time . ' seconds... ';
                sleep( $time );
                echo 'Done.' . "\r\n";
                $this->current_download_timeout = 0;
                $this->next_timeout = rand( $this->next_timeout_min, $this->next_timeout_max );
            }

            if ( isset( $opt['_connect_timeout'] ) ) {

                $opt['connect_timeout'] = $opt['_connect_timeout'];
                unset( $opt['_connect_timeout'] );
            } elseif ( !isset( $opt['connect_timeout'] ) ) {

                $opt[ 'connect_timeout' ] = $this->connect_timeout;
            }

            $opt['headers']['Cookie'] = $this->getCookie();

            if ( $this->use_proxy ) {

                $opt[ 'proxy' ] = $this->proxy_list->getCurrentAddress();
            }

            if ( $method === self::REQUEST_GET ) {

                return $this->get( $url, $opt );
            } else {

                return $this->post( $url, $opt );
            }
        }

        public function request( $url, $method = self::REQUEST_GET, $options = [], $max_proxies_in_a_row = null ) {

            $count = 0;

            do {

                $exception = null;
                $event = new AfterResponseEvent();

                try {

                    $event->response = $this->doRequest( $url, $method, $options );
                    $this->trigger( self::EVENT_AFTER_RESPONSE, $event );
                } catch ( ConnectException $exception ) {

                    if ( !$this->use_proxy || ( ( $max_proxies_in_a_row !== null ) && ( ++$count > $max_proxies_in_a_row ) ) ) {

                        throw $exception;
                    }

                    if ( strpos( $exception->getMessage(), 'with 0 out of 0 bytes received' ) !== false && !isset( $options['_connect_timeout'] ) ) {

                        // pastebėjau, kad šitą klaidą meta, kai neužtenka timeout'o gauti response'ui, tad pailginam jį ir nefail'inam transport'o
                        $options['_connect_timeout'] = $this->connect_timeout + 5;
                        // $this->log( 'Retrying with longer timeout...' . "\r\n" );
                    } else {

                        unset( $options['_connect_timeout'] );
                        $this->log( $exception->getCode() . ': ' . $exception->getMessage() . "\r\n" );
                        $this->proxy_list->markFailed( $exception );
                    }
                } catch ( ClientException | TooManyRequestsException $exception ) {

                    unset( $options['_connect_timeout'] );
                    if ( $exception->getCode() == 429 ) {

                        $evt = new TooManyRequestsEvent();
                        $evt->transport = $this;
                        $this->trigger( self::EVENT_TOO_MANY_REQUESTS, $evt );

                        $this->log( '429 Too Many Requests (' . $this->requests_from_last_429 . ' since last 429)' . "\r\n" );
                        $this->requests_from_last_429 = 0;

                        if ( !$evt->getHandled() ) {

                            $this->proxy_list->markTooManyRequests();
                        }
                    } else {

                        throw $exception;
                    }
                } catch ( RequestException $exception ) {

                    unset( $options['_connect_timeout'] );
                    $this->log( $exception->getCode() . ': ' . $exception->getMessage() . "\r\n" );

                    if ( strpos( $exception->getMessage(), 'Received HTTP code 407 from proxy after CONNECT' ) !== false
                        && $this->use_proxy
                        && ( ( $max_proxies_in_a_row === null ) || ( ++$count <= $max_proxies_in_a_row ) )
                    ) {

                        $this->proxy_list->markFailed( $exception );
                    } elseif ( strpos( $exception->getMessage(), 'Could not resolve proxy' ) !== false ) {

                        $this->proxy_list->markFailed( $exception );
                    } else {

                        throw $exception;
                    }
                }

            } while ( $event->response === null );

            $this->proxy_list->markSuccess();
            if ( $this->sleep_after_download ) {

                sleep( $this->sleep_after_download );
            }

            return $event->response;
        }

        /**
         * Returns static instance of the Transport.
         * @param array $config - Public attribute of the Transport class values.
         * @return static
         * @throws \Exception
         */
        public static function getInstance( $config = [] ) {

            if ( self::$instance === null ) {

                self::$instance = new static( $config );
            }

            return self::$instance;
        }
    }