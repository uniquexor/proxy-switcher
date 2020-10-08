<?php
    namespace unique\proxyswitcher;

    class ArrayProxyList extends AbstractProxyList {

        /**
         * Skaičiuoja kiek kartų proxy'is fail'ino. Jeigu fail'ina 3 kartus, jis žymimas kaip invalid ir daugiau nebenaudojamas.
         * Indeksuojamas pagal {@see Transport::$transports} masyvą.
         * @var array
         */
        protected $transport_failure_count = [];

        public function __construct( $list ) {

            $this->transports = $list;
            $this->current_transport = 0;
        }

        /**
         * @inheritdoc
         */
        public function markFailed( \Throwable $error = null ) {

            if ( !isset( $this->transport_failure_count[ $this->current_transport ] ) ) {

                $this->transport_failure_count[ $this->current_transport ] = 0;
            }

            $this->transport_failure_count[ $this->current_transport ]++;

            if ( $this->transport_failure_count[ $this->current_transport ] >= 3 ) {

                $this->markInvalid();
            }

            $this->switchTransport();
        }

        /**
         * @inheritdoc
         */
        public function getCurrentAddress(): string {

            return 'http://' . ( $this->username && $this->password ? urlencode( $this->username ) . ':' . urlencode( $this->password ) . '@' : '' ) .
                $this->transports[ $this->current_transport ];
        }

        /**
         * @inheritdoc
         */
        public function markSuccess() {

            return;
        }

        public function markTooManyRequests() {

            $this->markInvalid();
            $this->switchTransport();
        }
    }