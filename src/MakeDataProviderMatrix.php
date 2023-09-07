<?php
namespace Apie\PhpunitMatrixDataProvider;

use Generator;
use ReflectionMethod;

trait MakeDataProviderMatrix
{
    private function createDataProviderFrom(ReflectionMethod $method, object $objectFactory): Generator
    {
        $builder = new MatrixBuilder($objectFactory);
        yield from $builder->createMatrix($method);
    }
}