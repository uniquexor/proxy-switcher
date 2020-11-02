<?php
    namespace unique\proxyswitcherunit\tests;

    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use unique\proxyswitcher\ArrayProxyList;

    /**
     * Class ArrayProxyListTest
     * @package unique\proxyswitcherunit\tests
     * @covers \unique\proxyswitcher\ArrayProxyList
     */
    class ArrayProxyListTest extends TestCase {

        public function testMarkFailed() {

            $list = new ArrayProxyList( [ 'transports' => [ 'my.proxy.com:80', 'my2.proxy.com:80', 'my3.proxy.com:80' ] ] );
            $this->assertSame( 3, $list->max_transport_fails );
            $list->max_transport_fails = 2;

            $this->assertSame( 0, $list->getCurrentId() );

            $list->markFailed();
            $this->assertSame( 1, $list->getCurrentId() );

            $list->switchTransport();
            $this->assertSame( 2, $list->getCurrentId() );

            $list->switchTransport();
            $this->assertSame( 0, $list->getCurrentId() );

            $list->markFailed();
            $this->assertSame( 1, $list->getCurrentId() );

            $list->switchTransport();
            $this->assertSame( 2, $list->getCurrentId() );

            $list->switchTransport();
            $this->assertSame( 1, $list->getCurrentId() );
        }

        public function testGetCurrentAddress() {

            $list = new ArrayProxyList( [ 'username' => 'user@user.com', 'password' => 'pass', 'transports' => [ 'my.proxy.com:80', 'my2.proxy.com:80' ] ] );

            $this->assertSame( 'my.proxy.com:80', $list->getCurrentAddress( true ) );
            $this->assertSame( 'http://user%40user.com:pass@my.proxy.com:80', $list->getCurrentAddress() );

            $list->switchTransport();

            $this->assertSame( 'my2.proxy.com:80', $list->getCurrentAddress( true ) );
            $this->assertSame( 'http://user%40user.com:pass@my2.proxy.com:80', $list->getCurrentAddress() );
        }

        public function testMarkTooManyRequests() {

            $list = $this->getMockBuilder( ArrayProxyList::class )
                ->setMethods( [ 'markInvalid', 'switchTransport' ] )
                ->getMock();
            $list
                ->expects( $this->once() )
                ->method( 'markInvalid' );
            $list
                ->expects( $this->once() )
                ->method( 'switchTransport' );

            /**
             * @var ArrayProxyList|MockObject $list
             */

            $list->markTooManyRequests();
        }
    }