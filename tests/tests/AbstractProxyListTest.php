<?php
    namespace unique\proxyswitcherunit\tests;

    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use unique\proxyswitcherunit\data\BaseProxyList;

    class AbstractProxyListTest extends TestCase {

        /**
         * @covers \unique\proxyswitcher\AbstractProxyList
         */
        public function testConstructor() {

            $config = [ 'username' => 'username', 'password' => 'password', 'transports' => [ 1, 2 ] ];

            $list = new BaseProxyList( $config );
            $this->assertSame( 'username', $list->username );
            $this->assertSame( 'password', $list->password );
            $this->assertSame( [ 1, 2 ], $list->getTransports() );
        }

        /**
         * @covers \unique\proxyswitcher\AbstractProxyList
         */
        public function testSwitchingTransport() {

            $list = new BaseProxyList( [ 'transports' => [ 'a', 'b', 'c' ] ] );

            $this->assertSame( 0, $list->getCurrentId() );

            $list->switchTransport();
            $this->assertSame( 1, $list->getCurrentId() );

            $list->switchTransport();
            $this->assertSame( 2, $list->getCurrentId() );

            $list->switchTransport();
            $this->assertSame( 0, $list->getCurrentId() );

            // Let's see if transport gets marked as invalid:
            $list->switchTransport();
            $this->assertSame( 1, $list->getCurrentId() );
            $this->assertFalse( $list->isInvalid( 1 ) );
            $list->markInvalid();
            $this->assertTrue( $list->isInvalid( 1 ) );

            // An invalid proxy must not be used:
            $list->switchTransport();
            $this->assertSame( 2, $list->getCurrentId() );
            $list->switchTransport();
            $this->assertSame( 0, $list->getCurrentId() );
            $list->switchTransport();
            $this->assertSame( 2, $list->getCurrentId() );

            // Exception must be thrown when no more valid proxies are left:
            $list->markInvalid();
            $list->switchTransport();
            $this->assertSame( 0, $list->getCurrentId() );
            $this->expectException( \Exception::class );
            $this->expectExceptionMessage( 'No more transports available.');
            $list->switchTransport();
        }

        /**
         * @covers \unique\proxyswitcher\AbstractProxyList::log
         */
        public function testSwitchTransportLog() {

            $base = $this->getMockBuilder( BaseProxyList::class )
                ->setMethods( [ 'log', 'getCurrentAddress' ] )
                ->setConstructorArgs( [ [ 'transports' => [ 'a', 'b', 'c' ] ] ] )
                ->getMock();
            $base
                ->expects( $this->once() )
                ->method( 'log' )
                ->with( 'Switching transport to: address' . ".\r\n", false );
            $base
                ->expects( $this->once() )
                ->method( 'getCurrentAddress' )
                ->with( true )
                ->willReturn( 'address' );

            /**
             * @var BaseProxyList|MockObject $base
             */

            $base->switchTransport();
        }
    }