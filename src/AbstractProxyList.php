<?php
    namespace unique\proxyswitcher;

    use unique\proxyswitcher\events\LoggerTrait;
    use unique\proxyswitcher\events\ObjectFactoryTrait;

    abstract class AbstractProxyList {

        use LoggerTrait, ObjectFactoryTrait;

        /**
         * A username for a proxy server.
         * @var string
         */
        public string $username;

        /**
         * A password for a proxy server.
         * @var string
         */
        public string $password;

        /**
         * Proxių sąrašas.
         * Pilna implementacija priklauso nuo plečiančios klasės.
         * @var array
         */
        public $transports = [];

        /**
         * Nebevalidūs proxy'iai. Šie prox'iai nebebus daugiau bandomi.
         * Saugo index'ą į {@see Transport::$transports} masyvą.
         * Struktūra:
         * [
         *      (int) Index'as į $transports masyvą => (boolean) true,
         *      ...
         * ]
         * @var bool[]
         */
        protected $invalid_transports = [];

        /**
         * Index'ą į {@see Transport::$transports} masyvą, nurodo kurį proxy naudoti.
         * @var int
         */
        protected $current_transport;

        public function __construct( $config = [] ) {

        }

        /**
         * Pažymi transportą kaip nebevalidų
         */
        protected function markInvalid() {

            $this->invalid_transports[ $this->current_transport ] = true;
        }

        /**
         * Patikrina ar nurodytas proxy yra įtrauktas į neveikiančių sąrašą.
         * @param int $id - {@see AbstractProxyList::$transports} raktas
         * @return bool
         */
        public function isInvalid( int $id ) {

            return $this->invalid_transports[ $id ] ?? false;
        }

        /**
         * Gražina {@see AbstractProxyList::$transports} raktą, kuris dabar naudojamas.
         * @return int
         */
        public function getCurrentId(): int {

            return $this->current_transport;
        }

        /**
         * Parenka nauja Proxy.
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

            $this->log( 'Switching transport to: ' . $this->getCurrentAddress() . ".\r\n" );
        }

        abstract public function markTooManyRequests();

        /**
         * Pažymi dabartinį proxy kaip neveikiantį ir parenka naują.
         * @param \Throwable $error
         */
        abstract public function markFailed( \Throwable $error = null );

        /**
         * Pažymi dabartinį proxy kaip suveikusį.
         */
        abstract public function markSuccess();

        /**
         * Suformuoja dabartinio proxy adresą.
         * Formatas: [SCHEMA]://[USERNAME]:[PASSWORD]@[ADDRESS]:[PORT].
         * Jeigu nenurodytas $username arba $password:
         * [SCHEMA]://[ADDRESS]:[PORT].
         *
         * @param string|null $username
         * @param string|null $password
         * @return string
         */
        abstract public function getCurrentAddress(): string;
    }