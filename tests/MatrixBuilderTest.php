<?php
namespace Apie\Tests\PhpunitMatrixDataProvider;

use Apie\PhpunitMatrixDataProvider\MakeDataProviderMatrix;
use Apie\PhpunitMatrixDataProvider\MatrixBuilder;
use Generator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class MatrixBuilderTest extends TestCase
{
    use MakeDataProviderMatrix;

    public static function provideIntegers(): Generator
    {
        yield from self::createDataProviderFrom(
            new ReflectionMethod(__CLASS__, 'it_works_with_phpunit'),
            new class {
                public function getOne(): int
                {
                    return 1;
                }
                public function getTwo(): int
                {
                    return 2;
                }
                public function getThree(): int
                {
                    return 3;
                }
                public function getFour(): int
                {
                    return 4;
                }
                public function getFive(): int
                {
                    return 5;
                }
                public function toString(int $input): string
                {
                    return (string) $input;
                }
            }
        );
    }

    /**
     * @test
     * @dataProvider provideIntegers
     */
    public function it_works_with_phpunit(int $number, string $numberString = '6')
    {
        $this->assertContains($number, [1, 2, 3, 4, 5]);
        $this->assertContains($numberString, ['1', '2', '3', '4', '5', '6']);
    }

    /**
     * @test
     */
    public function it_does_not_crash_with_recursive_objects()
    {
        $class = new class {
            public function test(int $a): int
            {
                return $a;
            }
        };
        $matrixBuilder = new MatrixBuilder($class);
        $matrix = $matrixBuilder->createMatrix(new ReflectionMethod($class, 'test'));
        $this->assertEmpty($matrix);
    }

    /**
     * @test
     */
    public function it_throws_errors_on_missing_typehints()
    {
        $class = new class {
            public function test($a): int
            {
                return $a;
            }
        };
        $matrixBuilder = new MatrixBuilder($class);
        $this->expectException(RuntimeException::class);
        $matrixBuilder->createMatrix(new ReflectionMethod($class, 'test'));
    }

    /**
     * @test
     */
    public function it_can_reference_the_object_factory(?DummyObject $dummyObject = null)
    {
        $matrixBuilder = new MatrixBuilder(new DummyObject);
        $matrix = $matrixBuilder->createMatrix(new ReflectionMethod($this, __FUNCTION__));
        $this->assertCount(2, $matrix);
    }

    /**
     * @test
     */
    public function it_skips_returned_null_values()
    {
        $objectFactory = new class {
            public function getOne(): ?int
            {
                return 1;
            }
            public function getTwo(): ?int
            {
                return null;
            }
            public function toString(int $input): string
            {
                return (string) $input;
            }
            public function anotherTest(string $hello): float
            {
                return 1.0;
            }
        };
        $matrixBuilder = new MatrixBuilder($objectFactory);
        $matrix = $matrixBuilder->createMatrix(new ReflectionMethod($objectFactory, 'anotherTest'));
        $this->assertEquals(['anotherTest(toString(getOne()))' => ["1"]], $matrix);
    }
}