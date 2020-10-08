<?php
    namespace unique\proxyswitcher\events;

    trait EventTrait {

        protected $events = [];

        public function on( $event, $callback ) {

            $this->events[ $event ][] = $callback;
        }

        public function trigger( $event_name, EventObjectInterface $event ) {

            foreach ( $this->events[ $event_name ] ?? [] as $callback ) {

                call_user_func( $callback, $event );
                if ( $event->getHandled() ) {

                    return;
                }
            }
        }

        public function off( $event, $callback = null ) {

            if ( $callback === null ) {

                unset( $this->events[ $event ] );
            } else {

                foreach ( $this->events[ $event ] ?? [] as $i => $handler ) {

                    if ( $handler === $callback ) {

                        unset( $this->events[ $event ][ $i] );
                    }
                }
            }
        }
    }