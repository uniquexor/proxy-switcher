<?php
    namespace unique\proxyswitcherunit\tests\traits;

    use PHPUnit\Framework\TestCase;
    use unique\proxyswitcherunit\data\traits\ObjectFactoryTrait;

    class ObjectFactoryTraitTest extends TestCase {

        /**
         * @covers \unique\proxyswitcher\traits\ObjectFactoryTrait
         */
        public function testCreateObject() {

            $obj = new ObjectFactoryTrait();
            $this->assertSame( $obj, $obj->createObject( $obj ) );

            $new_obj = $obj->createObject( [ 'class' => get_class( $obj ) ] );
            $this->assertSame( [], $new_obj->config );

            $new_obj = $obj->createObject( [ 'class' => get_class( $obj ), 'a' => 10 ] );
            $this->assertSame( [ 'a' => 10 ], $new_obj->config );

            $new_obj = $obj->createObject( [ 'class' => get_class( $obj ), 10, 'a' => 10, 20, 'b' => 20 ] );
            $this->assertSame( [ 10, 'a' => 10, 20, 'b' => 20 ], $new_obj->config );

            $this->expectException( \Exception::class );
            $this->expectExceptionMessage( 'Unable to create object, no class specified.' );
            $obj->createObject( [] );
        }

        /**
         * @covers \unique\proxyswitcher\traits\ObjectFactoryTrait
         */
        public function testInitProperties() {

            $obj = new ObjectFactoryTrait();
            $this->assertSame( 1, $obj->a );
            $this->assertSame( 2, $obj->b );
            $this->assertSame( 3, $obj->c );

            $obj->initProperties( [
                'a' => 10,
                'c' => 30
            ] );

            $this->assertSame( 10, $obj->a );
            $this->assertSame( 2, $obj->b );
            $this->assertSame( 30, $obj->c );

            $exception = null;
            try {

                $obj->initProperties( [ 'd' => 40 ] );
            } catch ( \Exception $exception ) {}

            $this->assertInstanceOf( \Exception::class, $exception );
            $this->assertSame( 'Unable to instantiate a non public property `d` on `' . get_class( $obj ) . '`', $exception->getMessage() );

            $exception = null;
            try {

                $obj->initProperties( [ 'e' => 40 ] );
            } catch ( \Exception $exception ) {}

            $this->assertInstanceOf( \Exception::class, $exception );
            $this->assertSame( 'Unable to instantiate a non public property `e` on `' . get_class( $obj ) . '`', $exception->getMessage() );
        }
    }