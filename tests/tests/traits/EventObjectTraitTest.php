<?php
    namespace unique\proxyswitcherunit\tests\traits;

    use PHPUnit\Framework\TestCase;
    use unique\proxyswitcherunit\data\events\TestEvent;

    class EventObjectTraitTest extends TestCase {

        /**
         * @covers \unique\proxyswitcher\traits\EventObjectTrait
         */
        public function testHandled() {

            $event = new TestEvent();
            $this->assertSame( false, $event->getHandled() );

            $event->setHandled( true );
            $this->assertSame( true, $event->getHandled() );

            $event->setHandled( false );
            $this->assertSame( false, $event->getHandled() );
        }
    }