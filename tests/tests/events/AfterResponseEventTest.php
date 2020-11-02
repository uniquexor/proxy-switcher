<?php
    namespace unique\proxyswitcherunit\tests\events;

    use PHPUnit\Framework\TestCase;
    use unique\events\interfaces\EventObjectInterface;
    use unique\proxyswitcher\events\AfterResponseEvent;

    class AfterResponseEventTest extends TestCase {

        /**
         * @covers \unique\proxyswitcher\events\AfterResponseEvent
         */
        public function testProperties() {

            $this->assertClassHasAttribute( 'response', AfterResponseEvent::class );

            $obj = new AfterResponseEvent();
            $this->assertInstanceOf( EventObjectInterface::class, $obj );
        }
    }