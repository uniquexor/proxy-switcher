<?php
    namespace unique\proxyswitcher\traits;

    use unique\proxyswitcher\interfaces\EventObjectInterface;

    /**
     * Provides a simple event system. Allows setting and removing of event handlers as well as triggering specified events.
     * @package unique\proxyswitcher\traits
     */
    trait EventTrait {

        /**
         * Contains all the assigned event handlers.
         * [
         *      (string) event name => [
         *          (array|\Closure) Callback function,
         *          ...
         *      ],
         *      ...
         * ]
         * @var array
         */
        protected $events = [];

        /**
         * Assigns an event handler.
         * @param string $event - Event name
         * @param array|\Closure $callback - Handler
         */
        public function on( string $event, $callback ) {

            $this->events[ $event ][] = $callback;
        }

        /**
         * Triggers the specified event.
         * The first assigned handler will be called first. If it does not set {@see EventObjectInterface::setHandled()} the second handler will be called
         * and so on, until all the handlers have been called or `setHandled( true )` has been set.
         *
         * @param string $event_name - Event name
         * @param EventObjectInterface $event - Event data
         */
        public function trigger( string $event_name, EventObjectInterface $event ) {

            $event->setHandled( false );

            foreach ( $this->events[ $event_name ] ?? [] as $callback ) {

                call_user_func( $callback, $event );
                if ( $event->getHandled() ) {

                    return;
                }
            }
        }

        /**
         * Removes an event handler from the object.
         * If no handler is provided all handlers will be removed.
         *
         * @param string $event - Event name
         * @param array|\Closure|null $callback - Event handler.
         */
        public function off( string $event, $callback = null ) {

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