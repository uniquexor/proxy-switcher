<?php
    namespace unique\proxyswitcherunit\tests\traits;

    use PHPUnit\Framework\TestCase;
    use unique\proxyswitcherunit\data\traits\LoggerTrait;

    class LoggerTraitTest extends TestCase {

        /**
         * @covers \unique\proxyswitcherunit\data\traits\LoggerTrait
         */
        public function testLogging() {

            $obj = new LoggerTrait();

            // No errors if logger has not been provided.
            $obj->log( 'test' );

            $log = [];
            $obj->setLogger( function ( string $text, bool $is_error ) use ( &$log ) {

                $log[] = [ 'text' => $text, 'is_error' => $is_error ];
            } );

            $this->assertSame( true, $obj->is_verbose );

            $obj->log( 'No is_error provided' );
            $obj->log( 'is_error is true', true );

            $obj->is_verbose = false;
            $obj->log( 'Not logged' );

            $obj->is_verbose = true;
            $obj->setLogger( null );
            $obj->log( 'Not logged' );

            $expected = [
                [ 'text' => 'No is_error provided', 'is_error' => false ],
                [ 'text' => 'is_error is true', 'is_error' => true ],
            ];
            $this->assertSame( $expected, $log );
        }
    }