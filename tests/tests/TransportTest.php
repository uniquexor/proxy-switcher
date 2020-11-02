<?php
    namespace unique\proxyswitcherunit\tests;

    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\ClientException;
    use GuzzleHttp\Exception\ConnectException;
    use GuzzleHttp\Exception\RequestException;
    use GuzzleHttp\Psr7\Request;
    use GuzzleHttp\Psr7\Response;
    use PHPUnit\Extension\FunctionMocker;
    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use unique\proxyswitcher\ArrayProxyList;
    use unique\proxyswitcher\events\AfterResponseEvent;
    use unique\proxyswitcher\events\TooManyRequestsEvent;
    use unique\proxyswitcher\SingleProxyList;
    use unique\proxyswitcherunit\data\BaseProxyList;
    use unique\proxyswitcherunit\data\Transport;

    /**
     * Class TransportTest
     * @package unique\proxyswitcherunit
     *
     * @covers \unique\proxyswitcher\Transport
     */
    class TransportTest extends TestCase {

        /**
         * @var MockObject
         */
        private $php;

        public function setUp(): void {

            parent::setUp();

            $this->php = FunctionMocker::start( $this, 'unique\proxyswitcher' )
                ->mockFunction( 'rand' )
                ->mockFunction( 'sleep' )
                ->getMock();
        }

        public function testConstructor() {

            $mock = $this->getMockBuilder( Transport::class )
                ->setMethods( [ 'getNewSwitchTransportOn', 'getNewNextTimeoutOn' ] )
                ->getMock();
            $mock
                ->expects( $this->once() )
                ->method( 'getNewSwitchTransportOn' );
            $mock
                ->expects( $this->once() )
                ->method( 'getNewNextTimeoutOn' );

            /**
             * @var Transport|MockObject $mock
             */

            $proxy_list = new SingleProxyList( [
                'address' => 'a',
            ] );

            $config = [
                'next_timeout_min' => 1,
                'next_timeout_max' => 2,
                'sleep_time_min' => 3,
                'sleep_time_max' => 4,
                'switch_transport_min' => 5,
                'switch_transport_max' => 6,
                'max_proxies_in_a_row' => 7,
                'connect_timeout' => 8,
                'timeout_after_request' => 9,
                'cookie' => 10
            ];

            $mock->__construct( array_merge( [ 'proxy_list' => $proxy_list ], $config ) );
            foreach ( $config as $key => $value ) {

                $this->assertSame( $value, $mock->$key );
            }

            $this->assertSame( $proxy_list, $mock->getProxyList() );
            $this->assertTrue( $mock->getUseProxy() );

            $mock = new Transport();
            $this->assertFalse( $mock->getUseProxy() );
        }

        /**
         * @runInSeparateProcess
         */
        public function testGetNewSwitchTransportOn() {

            $this->php
                ->expects( $this->once() )
                ->method( 'rand' )
                ->with( 10, 20 );

            $this->getMockBuilder( Transport::class )
                ->setMethods( [ 'getNewNextTimeoutOn' ] )       // we need to disable the second randomizer method
                ->setConstructorArgs( [ [ 'switch_transport_min' => 10, 'switch_transport_max' => 20 ] ] )
                ->getMock();
        }

        /**
         * @runInSeparateProcess
         */
        public function testGetNewNextTimeoutOn() {

            $this->php
                ->expects( $this->once() )
                ->method( 'rand' )
                ->with( 30, 40 );

            $mock = $this->getMockBuilder( Transport::class )
                ->setMethods( [ 'getNewSwitchTransportOn' ] )       // we need to disable the second randomizer method
                ->setConstructorArgs( [ [ 'next_timeout_min' => 30, 'next_timeout_max' => 40 ] ] )
                ->getMock();
        }

        public function testGetSetProxyList() {

            $mock = new Transport();
            $this->assertFalse( $mock->getUseProxy() );
            $this->assertNull( $mock->getProxyList() );

            $proxy_list = new SingleProxyList();
            $mock->setProxyList( $proxy_list );
            $this->assertTrue( $mock->getUseProxy() );
            $this->assertSame( $proxy_list, $mock->getProxyList() );
        }

        public function testGetInstance() {

            $transport = Transport::getInstance();
            $this->assertSame( $transport, Transport::getInstance() );
        }

        public function testSetLogger() {

            $log = [];
            $logger = function ( $text, $is_error ) use ( &$log ) {

                $log[] = [ 'text' => $text, 'is_error' => $is_error ];
            };

            $list = new BaseProxyList();
            $transport = new Transport( [ 'proxy_list' => $list ] );
            $transport->setLogger( $logger );

            $transport->testLog( 'Text 1', true );
            $list->testLog( 'Text 2', false );

            $this->assertSame( [
                [ 'text' => 'Text 1', 'is_error' => true ],
                [ 'text' => 'Text 2', 'is_error' => false ],
            ], $log );
        }

        public function testUnimplementedMethods() {

            $methods = [
                'send' => [ new Request( 'get', 'url' ), [] ],
                'sendAsync' => [ new Request( 'get', 'url' ), [] ],
                'requestAsync' => [ 'get', 'url' ],
                'getConfig' => [ null ],
            ];

            $transport = new Transport();

            foreach ( $methods as $method => $params ) {

                $exception = null;
                try {

                    call_user_func_array( [ $transport, $method ], $params );
                } catch ( \Exception $exception ) {}

                $this->assertInstanceOf( \Exception::class, $exception );
                $this->assertSame( 'Not Implemented.', $exception->getMessage() );
            }
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         * @runInSeparateProcess
         */
        public function testRequestSuccess() {

            $this->php
                ->expects( $this->exactly( 2 ) )
                ->method( 'sleep' )
                ->with( 20 );

            $transport = $this->getMockBuilder( Transport::class )
                ->setMethods( [ 'doRequest', 'trigger' ] )
                ->setConstructorArgs( [ [ 'timeout_after_request' => 20 ] ] )
                ->getMock();

            $response = $this->createMock( Response::class );
            $opts = [ 'test' => 1 ];
            $opts2 = [ 'test' => 2 ];

            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'doRequest' )
                ->withConsecutive(
                    [ 'url', Transport::REQUEST_GET, $opts ],
                    [ 'url2', Transport::REQUEST_POST, $opts2 ],
                )
                ->willReturn( $response );

            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'trigger' )
                ->with( Transport::EVENT_AFTER_RESPONSE, $this->callback( function ( $event ) use ( $response ) {

                    $this->assertInstanceOf( AfterResponseEvent::class, $event );
                    $this->assertSame( $response, $event->response );

                    return true;
                } ) );

            /**
             * @var Transport|MockObject $transport
             */
            $res = $transport->request( Transport::REQUEST_GET, 'url', $opts );
            $this->assertSame( $response, $res );

            $proxy_list = $this->createPartialMock( SingleProxyList::class, [ 'markSuccess' ] );
            $proxy_list
                ->expects( $this->once() )
                ->method( 'markSuccess' );

            $transport->setProxyList( $proxy_list );
            $res = $transport->request( Transport::REQUEST_POST, 'url2', $opts2 );
            $this->assertSame( $response, $res );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestConnectExceptionNoProxyList() {

            // No proxy list must forward the exception:
            $transport = $this->getMockBuilder( Transport::class )
                ->setMethods( [ 'doRequest' ] )
                ->getMock();

            $expected_exception = new ConnectException( 'test', $this->createMock( Request::class ) );
            $transport
                ->expects( $this->once() )
                ->method( 'doRequest' )
                ->willThrowException( $expected_exception );

            $exception = null;

            /**
             * @var Transport|MockObject $transport
             */

            try {

                $transport->request( Transport::REQUEST_GET, 'url' );
            } catch ( \Exception $exception ) {}

            $this->assertSame( $expected_exception, $exception );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestConnectExceptionMaxProxiesInARowZero() {

            // No proxy list must forward the exception:
            $transport = $this->getMockBuilder( Transport::class )
                ->setMethods( [ 'doRequest' ] )
                ->getMock();

            $expected_exception = new ConnectException( 'test', $this->createMock( Request::class ) );
            $transport
                ->expects( $this->once() )
                ->method( 'doRequest' )
                ->willThrowException( $expected_exception );

            $exception = null;

            /**
             * @var Transport|MockObject $transport
             */

            $transport->max_proxies_in_a_row = 0;
            $transport->setProxyList( $this->createMock( ArrayProxyList::class ) );

            try {

                $transport->request( Transport::REQUEST_GET, 'url' );
            } catch ( \Exception $exception ) {}

            $this->assertSame( $expected_exception, $exception );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestConnectExceptionMarkFailedAndIncreaseTimeoutAndMaxProxiesInARowMoreThanZero() {

            // No proxy list must forward the exception:
            $transport = $this->getMockBuilder( Transport::class )
                ->setMethods( [ 'doRequest', 'log' ] )
                ->getMock();

            $expected_exception = new ConnectException( 'test', $this->createMock( Request::class ) );

            $exceptions = [
                $expected_exception,
                new ConnectException( 'Operation timed out after 1000 milliseconds with 0 out of 0 bytes received', $this->createMock( Request::class ) ),
                $expected_exception,
                $expected_exception,
            ];

            $transport
                ->expects( $this->exactly( 4 ) )
                ->method( 'doRequest' )
                ->withConsecutive(
                    [ 'url', Transport::REQUEST_GET, [] ],
                    [ 'url', Transport::REQUEST_GET, [] ],
                    [ 'url', Transport::REQUEST_GET, [ '_connect_timeout' => 7 ] ],
                    [ 'url', Transport::REQUEST_GET, [] ],
                )
                ->willReturnCallback( function () use ( &$exceptions ) {

                    throw array_shift( $exceptions );
                } );
            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'log' )
                ->with( '0: test' . "\r\n", true );

            $exception = null;

            /**
             * @var Transport|MockObject $transport
             */

            $transport->max_proxies_in_a_row = 2;
            $transport->connect_timeout = 2;

            $proxy_list = $this->createPartialMock( ArrayProxyList::class, [ 'markFailed' ] );
            $proxy_list
                ->expects( $this->exactly( 2 ) )
                ->method( 'markFailed' )
                ->with( $expected_exception );

            $transport->setProxyList( $proxy_list );

            try {

                $transport->request( Transport::REQUEST_GET, 'url' );
            } catch ( \Exception $exception ) {}

            $this->assertSame( $expected_exception, $exception );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestClientException() {

            $request = $this->createMock( Request::class );
            $response_429 = $this->createConfiguredMock( Response::class, [ 'getStatusCode' => 429 ] );
            $response_404 = $this->createConfiguredMock( Response::class, [ 'getStatusCode' => 404 ] );
            $exception_404 = new ClientException( 'test', $request, $response_404 );

            $data = [
                [
                    'request' => [ 'url', Transport::REQUEST_GET, [] ],
                    'response' => new ClientException( 'test', $request, $response_429 ),
                ],
                [
                    'request' => [ 'url', Transport::REQUEST_GET, [] ],
                    'response' => $this->createMock( Response::class ),
                ],
                [
                    'request' => [ 'url', Transport::REQUEST_GET, [] ],
                    'response' => $this->createMock( Response::class ),
                ],
                [
                    'request' => [ 'url', Transport::REQUEST_GET, [ '_connect_timeout' => 6 ] ],
                    'response' => new ClientException( 'test', $request, $response_429 ),
                ],
                [
                    'request' => [ 'url', Transport::REQUEST_GET, [] ],
                    'response' => $this->createMock( Response::class ),
                ],
                [
                    'request' => [ 'url', Transport::REQUEST_GET, [] ],
                    'response' => $exception_404,
                ],
            ];

            $transport = $this->createPartialMock( Transport::class, [ 'doRequest', 'log' ] );
            $transport
                ->method( 'doRequest' )
                ->withConsecutive(
                    $data[0]['request'],
                    $data[1]['request'],
                    $data[2]['request'],
                    $data[3]['request'],
                    $data[4]['request'],
                    $data[5]['request'],
                )
                ->willReturnCallback( function () use ( &$data ) {

                    $response = array_shift( $data );
                    $response = $response['response'];

                    if ( $response instanceof \Exception ) {

                        throw $response;
                    } else {

                        return $response;
                    }
                } );

            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'log' )
                ->withConsecutive(
                    [ '429 Too Many Requests (1 since last 429)' . "\r\n", true ],
                    [ '429 Too Many Requests (2 since last 429)' . "\r\n", true ]
                );

            $proxy_list = $this->createPartialMock( ArrayProxyList::class, [ 'markTooManyRequests' ] );
            $proxy_list
                ->expects( $this->once() )
                ->method( 'markTooManyRequests' );

            $transport->setProxyList( $proxy_list );

            // 429, success:
            $transport->request( Transport::REQUEST_GET ,'url' );
            // Success
            $transport->request( Transport::REQUEST_GET ,'url' );

            $transport->on( Transport::EVENT_TOO_MANY_REQUESTS, function ( TooManyRequestsEvent $event ) {

                $event->setHandled( true );
            } );

            // 429 handled (also resets _connect_timeout), Success
            $transport->request( Transport::REQUEST_GET ,'url', [ '_connect_timeout' => 6 ] );

            // Other HTTP error:
            $exception = null;
            try {

                $transport->request( Transport::REQUEST_GET, 'url' );
            } catch ( \Exception $exception ) {}

            $this->assertSame( $exception_404, $exception );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestRequestExceptionNoProxy() {

            $request = $this->createMock( Request::class );
            $exception_407 = new RequestException( 'Received HTTP code 407 from proxy after CONNECT', $request );

            // No proxy - immediate termination:
            $transport = $this->createPartialMock( Transport::class, [ 'log', 'doRequest' ] );
            $transport
                ->expects( $this->once() )
                ->method( 'doRequest' )
                ->willThrowException( $exception_407 );
            $transport
                ->expects( $this->once() )
                ->method( 'log' )
                ->with( '0: Received HTTP code 407 from proxy after CONNECT' . "\r\n", true );

            $exception = null;
            try {

                $transport->request( Transport::REQUEST_GET, 'url' );
            } catch ( \Exception $exception ) {}

            $this->assertSame( $exception_407, $exception );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestRequestException407Handling() {

            $request = $this->createMock( Request::class );
            $response = $this->createMock( Response::class );
            $exception_407 = new RequestException( 'Received HTTP code 407 from proxy after CONNECT', $request );

            $proxy_list = $this->createPartialMock( ArrayProxyList::class, [ 'markFailed' ] );
            $proxy_list
                ->expects( $this->once() )
                ->method( 'markFailed' )
                ->with( $exception_407 );

            $data = [
                $exception_407,
                $response,
            ];

            $transport = $this->createPartialMock( Transport::class, [ 'doRequest' ] );
            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'doRequest' )
                ->willReturnCallback( function () use ( &$data ) {

                    $item = array_shift( $data );
                    if ( $item instanceof \Exception ) {

                        throw $item;
                    } else {

                        return $item;
                    }
                } );

            $transport->setProxyList( $proxy_list );

            $res = $transport->request( Transport::REQUEST_GET, 'url' );
            $this->assertSame( $response, $res );
        }


        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestRequestExceptionMaxProxiesInARow() {

            $request = $this->createMock( Request::class );
            $exception_407 = new RequestException( 'Received HTTP code 407 from proxy after CONNECT', $request );

            $proxy_list = $this->createPartialMock( ArrayProxyList::class, [ 'markFailed' ] );
            $proxy_list
                ->expects( $this->once() )
                ->method( 'markFailed' )
                ->with( $exception_407 );

            $transport = $this->createPartialMock( Transport::class, [ 'doRequest' ] );
            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'doRequest' )
                ->willThrowException( $exception_407 );

            $transport->setProxyList( $proxy_list );
            $transport->max_proxies_in_a_row = 1;
            $exception = null;
            try {

                $transport->request( Transport::REQUEST_GET, 'url' );
            } catch ( \Exception $exception ) {}

            $this->assertSame( $exception_407, $exception );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestRequestExceptionCouldNotResolveProxy() {

            $request = $this->createMock( Request::class );
            $response = $this->createMock( Response::class );
            $exception_407 = new RequestException( 'Could not resolve proxy', $request );

            $proxy_list = $this->createPartialMock( ArrayProxyList::class, [ 'markFailed' ] );
            $proxy_list
                ->expects( $this->once() )
                ->method( 'markFailed' )
                ->with( $exception_407 );

            $data = [
                $exception_407,
                $response,
            ];

            $transport = $this->createPartialMock( Transport::class, [ 'doRequest' ] );
            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'doRequest' )
                ->willReturnCallback( function () use ( &$data ) {

                    $item = array_shift( $data );
                    if ( $item instanceof \Exception ) {

                        throw $item;
                    } else {

                        return $item;
                    }
                } );

            $transport->setProxyList( $proxy_list );
            $res = $transport->request( Transport::REQUEST_GET, 'url' );

            $this->assertSame( $response, $res );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::request
         */
        public function testRequestRequestExceptionOtherErrors() {

            $request = $this->createMock( Request::class );
            $exception_other = new RequestException( 'Other error', $request );

            $proxy_list = $this->createPartialMock( ArrayProxyList::class, [ 'markFailed' ] );
            $proxy_list
                ->expects( $this->never() )
                ->method( 'markFailed' );


            $transport = $this->createPartialMock( Transport::class, [ 'doRequest' ] );
            $transport
                ->expects( $this->once() )
                ->method( 'doRequest' )
                ->willThrowException( $exception_other );

            $transport->setProxyList( $proxy_list );

            $exception = null;
            try {

                $transport->request( Transport::REQUEST_GET, 'url' );
            } catch ( \Exception $exception ) {}

            $this->assertSame( $exception_other, $exception );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::doRequest
         */
        public function testRequestSwitchTransportOn() {

            $client = $this->createConfiguredMock( Client::class, [ 'get' => $this->createMock( Response::class ) ] );

            $transport = $this->createPartialMock( Transport::class, [ 'getNewSwitchTransportOn' ] );
            $transport
                ->expects( $this->once() )
                ->method( 'getNewSwitchTransportOn' )
                ->willReturn( 2 );

            $transport->__construct();
            $transport->setClient( $client );

            // No error must happen when proxy list is not specified and we make more than getNewSwitchTransportOn() requests:
            $transport->request( Transport::REQUEST_GET, 'url' );
            $transport->request( Transport::REQUEST_GET, 'url' );
            $transport->request( Transport::REQUEST_GET, 'url' );

            // With proxy, a switch must happen:

            $transport = $this->createPartialMock( Transport::class, [ 'getNewSwitchTransportOn' ] );
            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'getNewSwitchTransportOn' )
                ->willReturn( 2 );

            $proxy_list = $this->createMock( ArrayProxyList::class );
            $proxy_list
                ->expects( $this->once() )
                ->method( 'switchTransport' )
                ->willReturnCallback( function () use ( &$switch_count ) {

                    $switch_count++;
                } );

            $switch_count = 0;

            $transport->__construct();
            $transport->setProxyList( $proxy_list );
            $transport->setClient( $client );

            $transport->request( Transport::REQUEST_GET, 'url' );
            $this->assertSame( 0, $switch_count );

            $transport->request( Transport::REQUEST_GET, 'url' );
            $this->assertSame( 1, $switch_count );

            $transport->request( Transport::REQUEST_GET, 'url' );
            $this->assertSame( 1, $switch_count );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::doRequest
         * @runInSeparateProcess
         */
        public function testRequestTimeout() {

            $client = $this->createConfiguredMock( Client::class, [ 'get' => $this->createMock( Response::class ) ] );

            $transport = $this->createPartialMock( Transport::class, [ 'getNewSwitchTransportOn', 'getNewNextTimeoutOn', 'log' ] );
            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'getNewNextTimeoutOn' )
                ->willReturn( 2 );
            $transport
                ->expects( $this->exactly( 2 ) )
                ->method( 'log' )
                ->withConsecutive(
                    [ 'Timing out for: 150 seconds... ', false ],
                    [ 'Done.' . "\r\n", false ],
                );

            $this->php
                ->expects( $this->once() )
                ->method( 'rand' )
                ->with( 100, 200 )
                ->willReturn( 150 );
            $this->php
                ->expects( $this->once() )
                ->method( 'sleep' )
                ->with( 150 );

            $transport->__construct();
            $transport->timeout_after_request = 0;
            $transport->sleep_time_min = 100;
            $transport->sleep_time_max = 200;

            $transport->setClient( $client );

            $transport->request( Transport::REQUEST_GET, 'url' );
            $transport->request( Transport::REQUEST_GET, 'url' );
            $transport->request( Transport::REQUEST_GET, 'url' );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::doRequest
         */
        public function testRequestConnectOptions() {

            $client = $this->createMock( Client::class );
            $client
                ->expects( $this->exactly( 3 ) )
                ->method( 'get' )
                ->withConsecutive(
                    [ 'url', [ 'connect_timeout' => 1, 'headers' => [ 'Cookie' => '' ] ] ],
                    [ 'url', [ 'connect_timeout' => 5,  'headers' => [ 'Cookie' => '123' ] ] ],
                    [ 'url', [ 'connect_timeout' => 10, 'headers' => [ 'Cookie' => '123' ], 'proxy' => 'test' ] ],
                )
                ->willReturn( $this->createMock( Response::class ) );

            $transport = new Transport();
            $transport->setClient( $client );
            $transport->connect_timeout = 1;

            $transport->request( Transport::REQUEST_GET, 'url' );

            $transport->cookie = '123';
            $transport->request( Transport::REQUEST_GET, 'url', [ '_connect_timeout' => 5 ] );

            $proxy_list = $this->createMock( ArrayProxyList::class );
            $proxy_list
                ->expects( $this->once() )
                ->method( 'getCurrentAddress' )
                ->willReturn( 'test' );
            $transport->setProxyList( $proxy_list );
            $transport->request( Transport::REQUEST_GET, 'url', [ 'connect_timeout' => 10 ] );
        }

        /**
         * @covers \unique\proxyswitcher\Transport::doRequest
         */
        public function testRequestDifferentMethods() {

            $client = $this->createMock( Client::class );
            $get_response = $this->createMock( Response::class );
            $post_response = $this->createMock( Response::class );

            $client
                ->expects( $this->once() )
                ->method( 'get' )
                ->with( 'url1', [ 'connect_timeout' => 1, 'headers' => [ 'Cookie' => '' ] ] )
                ->willReturn( $get_response );

            $client
                ->expects( $this->once() )
                ->method( 'post' )
                ->with( 'url2', [ 'connect_timeout' => 1, 'headers' => [ 'Cookie' => '' ] ] )
                ->willReturn( $post_response );

            $transport = new Transport();
            $transport->setClient( $client );
            $transport->connect_timeout = 1;

            $res = $transport->request( Transport::REQUEST_GET, 'url1' );
            $this->assertSame( $get_response, $res );

            $res = $transport->request( Transport::REQUEST_POST, 'url2' );
            $this->assertSame( $post_response, $res );
        }
    }