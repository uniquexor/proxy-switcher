<?php
    namespace unique\proxyswitcherunit\tests;

    use PHPUnit\Extension\FunctionMocker;
    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use unique\proxyswitcher\SingleProxyList;

    /**
     * Class SingleProxyListTest
     * @package unique\proxyswitcherunit\tests
     * @covers \unique\proxyswitcher\SingleProxyList
     */
    class SingleProxyListTest extends TestCase {

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject
         */
        private $php;

        public function setUp(): void {

            parent::setUp();

            $this->php = FunctionMocker::start( $this, 'unique\proxyswitcher' )
                ->mockFunction( 'sleep' )
                ->getMock();
        }

        public function testMarkTooManyRequests() {

            $list = new SingleProxyList();

            $this->expectException( \Exception::class );
            $this->expectExceptionMessage( 'Got 429 Too Many Requests.' );

            $list->markTooManyRequests();
        }

        public function testMarkFailed() {

            $list = new SingleProxyList();

            $exception = new \Exception( 'test' );
            $exc = null;
            try {

                $list->markFailed( $exception );
            } catch ( \Exception $exc ) {}

            $this->assertSame( $exception, $exc );

            $this->expectException( \Exception::class );
            $this->expectExceptionMessage( 'A proxy has failed.' );
            $list->markFailed();
        }

        public function testGetCurrentAddress() {

            $list = new SingleProxyList( [
                'username' => 'user@user.com',
                'password' => 'pass',
                'address' => 'my.proxy.com:80'
            ] );

            $this->assertSame( 'my.proxy.com:80', $list->getCurrentAddress( true ) );
            $this->assertSame( 'http://user%40user.com:pass@my.proxy.com:80', $list->getCurrentAddress() );
        }

        /**
         * @runInSeparateProcess
         */
        public function testSwitchTransport() {

            $this->php
                ->expects( $this->once() )
                ->method( 'sleep' )
                ->with( 60 );

            $list = $this->getMockBuilder( SingleProxyList::class )
                ->setMethods( [ 'log' ] )
                ->getMock();
            $list
                ->expects( $this->atLeast( 2 ) )
                ->method( 'log' )
                ->withConsecutive(
                    [ 'Can\'t switch transport, sleeping for 60s...' . "\r\n", false ],
                    [ 'Continuing...' . "\r\n", false ],
                );

            /**
             * @var SingleProxyList|MockObject $list
             */

            $list->switchTransport();
        }
    }