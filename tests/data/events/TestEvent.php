<?php
    namespace unique\proxyswitcherunit\data\events;

    use unique\proxyswitcher\interfaces\EventObjectInterface;
    use unique\proxyswitcher\traits\EventObjectTrait;

    class TestEvent implements EventObjectInterface {

        use EventObjectTrait;

        public $result;
    }