<img src="/docs/apie-logo.svg" width="100px" align="left" />
<h1>Apie type converter</h1>




 [![Latest Stable Version](http://poser.pugx.org/apie/type-converter/v)](https://packagist.org/packages/apie/type-converter) [![Total Downloads](http://poser.pugx.org/apie/type-converter/downloads)](https://packagist.org/packages/apie/type-converter) [![Latest Unstable Version](http://poser.pugx.org/apie/type-converter/v/unstable)](https://packagist.org/packages/apie/type-converter) [![License](http://poser.pugx.org/apie/type-converter/license)](https://packagist.org/packages/apie/type-converter) [![PHP Version Require](http://poser.pugx.org/apie/type-converter/require/php)](https://packagist.org/packages/apie/type-converter) [![Code coverage](./coverage_badge.svg)](https://github.com/apie-lib/type-converter/actions/workflows/php.yml) [![Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/donate/?hosted_button_id=J4CAFUAW7VTAY) 

Apie is a suite of composer packages to work with domain objects. It tries to aim to follow a Domain-objects-first approach and not a database first approach that you find in many PHP frameworks nowadays.

This type converter package is written outside the monorepo and provides a simple tooling to convert objects
into other objects.

# Usage
Easiest usage:
```php
<?php
use Apie\TypeConverter\DefaultConvertersFactory;
$converter = DefaultConvertersFactory::create();
var_dump($converter->convertTo(12, 'string')); // '12'
```

This is not a very useful example. Normally you try to convert a DTO to a domain object or the other way
around.

More serious example:
```php
<?php
use Apie\TypeConverter\DefaultConvertersFactory;

class Dto {
    public string $description;

    public string $name;
}

class DomainObject {
    public function __construct(
        private string $description,
        private string $name
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
use Apie\TypeConverter\DefaultConvertersFactory;
$converter = DefaultConvertersFactory::create();
$dto = ($converter->convertTo(new DomainObject('description', 'name'), Dto::class));
var_dump($dto->name); // 'name'
$converter->convertTo($dto, DomainObject::class);
```

# Creating your own converters
It's very easy to create your own converters. All you need to do is make a class that implements
ConverterInterface.
In case you use phpstan for static code analysis a small phpdoc can be added
to make phpstan understand the type conversion.

```php
<?php
use Apie\Core\ValueObjects\Interfaces\StringValueObjectInterface;
use Apie\TypeConverter\ConverterInterface;

/**
 * @implements ConverterInterface<StringValueObjectInterface, string>
 */
class StringValueObjectToStringConverter implements ConverterInterface
{
    public function convert(StringValueObjectInterface $valueObject): string
    {
        return $valueObject->toNative();
    }
}

use Apie\TypeConverter\DefaultConvertersFactory;
$converter = DefaultConvertersFactory::create(new StringValueObjectToStringConverter());
$converter->convertTo(new Email('test@example.com'), 'string');
```

# Customizing the TypeConverter
DefaultConvertsFactory creates an instance of TypeConverter with sensible defaults.
You can configure everything by just calling the constructor. Remember that all default converters
require to be added manually too.

```php
<?php
use Apie\TypeConverter\Converters\FloatToStringConverter;
use Apie\TypeConverter\Converters\IntToStringConverter;
use Apie\TypeConverter\Converters\ObjectToObjectConverter;
use Apie\TypeConverter\TypeConverter;
$converter = new TypeConverter(
    new ObjectToObjectConverter(),
    new IntToStringConverter(),
    new FloatToStringConverter()
);
```

# Complex converters
In the example above we can convert a string value object to string, but how do we make it the other way around?

We could make a converter for every value object, but that would mean many similar classes.
```php
<?php
use Apie\Core\ValueObjects\Interfaces\StringValueObjectInterface;
use Apie\TypeConverter\ConverterInterface;

/**
 * @implements ConverterInterface<string, Email>
 */
class StringToEmailConverter implements ConverterInterface
{
    public function convert(string $value): Email
    {
        return Email::fromNative($value);
    }
}
```

Luckily we have a better solution by providing the wanted type as second argument.
```php
<?php
use Apie\Core\ValueObjects\Interfaces\StringValueObjectInterface;
use Apie\TypeConverter\ConverterInterface;

/**
 * @implements ConverterInterface<string, StringValueObjectInterface>
 */
class StringToStringValueObjectConverter implements ConverterInterface
{
    public function convert(string $value, \ReflectionNamedType $wantedType): StringValueObjectInterface
    {
        $className = $wantedType->getName();
        return $className::fromNative($value);
    }
}
```

It is also possible to get the TypeConverter instance to make recursive conversions.

```php
<?php
use Apie\Core\ValueObjects\Interfaces\StringValueObjectInterface;
use Apie\TypeConverter\ConverterInterface;
use Apie\TypeConverter\TypeConverter;

/**
 * @implements ConverterInterface<int, StringValueObjectInterface>
 */
class IntToStringValueObjectConverter implements ConverterInterface
{
    public function convert(int $value, \ReflectionNamedType $wantedType, TypeConverter $typeConverter): StringValueObjectInterface
    {
        $value = $typeConverter->convertTo($value, 'string');
        $className = $wantedType->getName();
        return $className::fromNative($value);
    }
}
```

# Converter preference
If multiple converters could perform the conversion, then the most accurate one is the one that gets the priority.

```php
<?php
use Apie\TypeConverter\Converters\FloatToStringConverter;
use Apie\TypeConverter\Converters\IntToStringConverter;
use Apie\TypeConverter\Converters\ObjectToObjectConverter;
use Apie\TypeConverter\TypeConverter;
$converter = new TypeConverter(
    new ObjectToObjectConverter(),
    new IntToStringConverter(),
    new FloatToStringConverter()
);
```

If I would try to convert '1' IntToStringConverter and FloatToStringConverter would apply, but since int is more accurate it uses IntToStringConverter.