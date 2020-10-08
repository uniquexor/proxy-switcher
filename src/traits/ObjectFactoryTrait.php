<?php
    namespace unique\proxyswitcher\events;

    trait ObjectFactoryTrait {

        public function createObject( $config = [] ) {

            if ( !isset( $config['class'] ) ) {

                throw new \Exception( 'Unable to create object, no class specified.' );
            }

            $class = $config['class'];
            unset( $config['class'] );

            return new $class( $config );
        }

        protected static function initObject( $object, $config = [] ) {

            foreach ( $config as $key => $value ) {

                $object->$key = $value;
            }

            return $object;
        }
    }