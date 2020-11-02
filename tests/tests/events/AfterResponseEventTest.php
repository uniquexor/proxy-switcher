<?php
    namespace unique\proxyswitcherunit\tests\events;

    use PHPUnit\Framework\TestCase;
    use unique\proxyswitcher\events\AfterResponseEvent;
    use unique\proxyswitcher\interfaces\EventObjectInterface;

    class AfterResponseEventTest extends TestCase {

        /**
         * @covers \unique\proxyswitcher\events\AfterResponseEvent
         */
        public function testProperties() {

            $this->assertClassHasAttribute( 'response', AfterResponseEvent::class );
            $this->assertClassHasAttribute( 'error', AfterResponseEvent::class );

            $obj = new AfterResponseEvent();
            $this->assertInstanceOf( EventObjectInterface::class, $obj );
        }
    }