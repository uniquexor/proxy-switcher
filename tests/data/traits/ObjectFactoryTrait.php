<?php
    namespace unique\proxyswitcherunit\data\traits;

    class ObjectFactoryTrait {

        use \unique\proxyswitcher\traits\ObjectFactoryTrait;

        public $a = 1;

        public $b = 2;

        public $c = 3;

        protected $d;

        private $e;

        public $config;

        public function __construct( $config = [] ) {

            $this->config = $config;
        }

        public function getD() {

            return $this->d;
        }

        public function getE() {

            return $this->e;
        }
    }