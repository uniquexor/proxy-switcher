<?php
    namespace unique\proxyswitcher\events;

    interface EventHandlingInterface {

        public function on( $event, $callback );

        public function trigger( $event_name, EventObjectInterface $event );

        public function off( $event, $callback = null );
    }