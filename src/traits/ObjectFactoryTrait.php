<?php
    namespace unique\proxyswitcher\traits;

    /**
     * Trait ObjectFactoryTrait.
     * Allows to create a new object by class and initialize it's public attributes.
     *
     * @package unique\proxyswitcher\traits
     */
    trait ObjectFactoryTrait {

        /**
         * Creates and returns a new object or simply returns $config if it is an object.
         * If $config is an array it must contain a `class` index, to be used for a new object.
         * The class attribute will be stripped from the array and it will be passed on to the constructor.
         * Usage:
         * ```php```
         * $this->createObject( [
         *    'class' => '\Some\Class',
         *    'class attribute 1' => '...',
         *    'class attribute 2' => '...',
         *    ...
         * ] );
         * ```php```
         * @param array $config
         * @return array|mixed
         * @throws \Exception
         */
        public function createObject( $config = [] ) {

            if ( is_object( $config ) ) {
                
                return $config;
            }

            if ( !isset( $config['class'] ) ) {

                throw new \Exception( 'Unable to create object, no class specified.' );
            }

            $class = $config['class'];
            unset( $config['class'] );

            return new $class( $config );
        }

        /**
         * Initializes $this object with the properties provided.
         * The properties must be public, otherwise an Exception will be thrown.
         *
         * @param array $config
         * @return $this
         * @throws \ReflectionException
         * @throws \Exception
         */
        public function initProperties( $config = [] ) {

            $reflection = new \ReflectionObject( $this );
            foreach ( $config as $key => $value ) {

                $prop = $reflection->getProperty( $key );
                if ( $prop && $prop->isPublic() ) {

                    $this->$key = $value;
                } else {

                    throw new \Exception( 'Unable to instantiate a non public property `' . $key . '` on `' . get_class( $this ) . '`' );
                }
            }

            return $this;
        }
    }