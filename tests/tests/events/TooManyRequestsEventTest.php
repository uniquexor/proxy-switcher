<?php
    namespace unique\proxyswitcherunit\tests\events;

    use PHPUnit\Framework\TestCase;
    use unique\events\interfaces\EventObjectInterface;
    use unique\proxyswitcher\events\TooManyRequestsEvent;

    class TooManyRequestsEventTest extends TestCase {

        /**
         * @covers \unique\proxyswitcher\events\TooManyRequestsEvent
         */
        public function testProperties() {

            $this->assertClassHasAttribute( 'transport', TooManyRequestsEvent::class );

            $obj = new TooManyRequestsEvent();
            $this->assertInstanceOf( EventObjectInterface::class, $obj );
        }
    }