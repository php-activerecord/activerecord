<?php

class BookNumericality extends ActiveRecord\Model
{
    public static string $table_name = 'books';

    public static array $validates_numericality_of = [];
}

class ValidatesNumericalityOfTest extends DatabaseTestCase
{
    public static $NULL = [null];
    public static $BLANK = ['', ' ', " \t \r \n"];
    public static $FLOAT_STRINGS = ['0.0', '+0.0', '-0.0', '10.0', '10.5', '-10.5', '-0.0001', '-090.1'];
    public static $INTEGER_STRINGS = ['0', '+0', '-0', '10', '+10', '-10', '0090', '-090'];
    public static $FLOATS = [0.0, 10.0, 10.5, -10.5, -0.0001];
    public static $INTEGERS = [0, 10, -10];
    public static $JUNK = ['not a number', '42 not a number', '00-1', '--3', '+-3', '+3-1', '-+019.0', '12.12.13.12', "123\nnot a number"];

    public function setUp($connection_name=null): void
    {
        parent::setUp($connection_name);
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => []
        ];
    }

    private function assert_validity($value, $boolean, $msg=null)
    {
        $book = new BookNumericality();
        $book->numeric_test = $value;

        if ('valid' == $boolean) {
            $this->assertTrue($book->save());
            $this->assertFalse($book->errors->is_invalid('numeric_test'));
        } else {
            $this->assertFalse($book->save());
            $this->assertTrue($book->errors->is_invalid('numeric_test'));

            if (!is_null($msg)) {
                $this->assertSame([$msg], $book->errors->on('numeric_test'));
            }
        }
    }

    private function assert_invalid($values, $msg=null)
    {
        foreach ($values as $value) {
            $this->assert_validity($value, 'invalid', $msg);
        }
    }

    private function assert_valid($values, $msg=null)
    {
        foreach ($values as $value) {
            $this->assert_validity($value, 'valid', $msg);
        }
    }

    public function testNumericality()
    {
        // $this->assert_invalid(array("0xdeadbeef"));

        $this->assert_valid(array_merge(self::$FLOATS, self::$INTEGERS));
        $this->assert_invalid(array_merge(self::$NULL, self::$BLANK, self::$JUNK));
    }

    public function testNotAnumber()
    {
        $this->assert_invalid(['blah'], 'is not a number');
    }

    public function testInvalidNull()
    {
        $this->assert_invalid([null]);
    }

    public function testInvalidBlank()
    {
        $this->assert_invalid([' ', '  '], 'is not a number');
    }

    public function testInvalidWhitespace()
    {
        $this->assert_invalid(['']);
    }

    public function testValidNull()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => [
                'allow_null' => true
            ]
        ];
        $this->assert_valid([null]);
    }

    public function testOnlyInteger()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => [
                'only_integer' => true
            ]
        ];

        $this->assert_valid([1, '1']);
        $this->assert_invalid([1.5, '1.5']);
    }

    public function testGreaterThan()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => ['greater_than' => 5]
        ];

        $this->assert_valid([6, '7']);
        $this->assert_invalid([5, '5'], 'must be greater than 5');
    }

    public function testEqualTo()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => ['equal_to' => 5]
        ];

        $this->assert_valid([5, '5']);
        $this->assert_invalid([6, '6'], 'must be equal to 5');
    }

    public function testGreaterThanOrEqualTo()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => ['greater_than_or_equal_to' => 5]
        ];

        $this->assert_valid([5, 5.1, '5.1']);
        $this->assert_invalid([-50, 4.9, '4.9', '-5.1']);
    }

    public function testLessThan()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => ['less_than' => 5]
        ];

        $this->assert_valid([4.9, -1, 0, '-5']);
        $this->assert_invalid([5, '5'], 'must be less than 5');
    }

    public function testLessThanOrEqualTo()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => ['less_than_or_equal_to' => 5]
        ];

        $this->assert_valid([5, -1, 0, 4.9, '-5']);
        $this->assert_invalid(['8', 5.1], 'must be less than or equal to 5');
    }

    public function testGreaterThanLessThanAndEven()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => [
                'greater_than' => 1,
                'less_than' => 4,
                'even' => true
            ]
        ];

        $this->assert_valid([2]);
        $this->assert_invalid([1, 3, 4]);
    }

    public function testCustomMessage()
    {
        BookNumericality::$validates_numericality_of = [
            'numeric_test' => ['message' => 'Hello']
        ];
        $book = new BookNumericality(['numeric_test' => 'NaN']);
        $book->is_valid();
        $this->assertEquals(['Numeric test Hello'], $book->errors->full_messages());
    }
}

array_merge(ValidatesNumericalityOfTest::$INTEGERS, ValidatesNumericalityOfTest::$INTEGER_STRINGS);
array_merge(ValidatesNumericalityOfTest::$FLOATS, ValidatesNumericalityOfTest::$FLOAT_STRINGS);
