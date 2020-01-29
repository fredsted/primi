<?php

namespace Smuuf\Primi\Structures;

use \Smuuf\Primi\Helpers\Common;
use \Smuuf\Primi\ISupportsComparison;
use \Smuuf\Primi\ISupportsAddition;
use \Smuuf\Primi\ISupportsSubtraction;
use \Smuuf\Primi\ISupportsMultiplication;
use \Smuuf\Primi\ISupportsIteration;
use \Smuuf\Primi\ISupportsArrayAccess;

class StringValue extends Value implements
	ISupportsAddition,
	ISupportsSubtraction,
	ISupportsMultiplication,
	ISupportsIteration,
	ISupportsComparison,
	ISupportsArrayAccess
{

	const TYPE = "string";

	const NEWLINE = '__NEWLINE__';

	public function __construct(string $value) {
		$this->value = $value;
	}

	public function getStringValue(): string {

		// We are about to put double-quotes around the return value,
		// so let's "escape" double-quotes present in the string value.
		$escaped = \str_replace('"', '\"', $this->value);

		$escaped = self::expandSequences($escaped);

		return "\"$escaped\"";

	}

	public function getInternalValue()
	{
		return self::expandSequences($this->value);
	}

	public function doAddition(Value $rightOperand) {

		Common::allowTypes($rightOperand, self::class);
		return new self($this->value . $rightOperand->value);

	}

	public function doSubtraction(Value $rightOperand) {

		// Allow only string at this point (if the operand was a regex, we've
		// already returned value).
		Common::allowTypes($rightOperand, self::class, RegexValue::class);

		if ($rightOperand instanceof RegexValue) {
			$match = \preg_replace($rightOperand->getInternalValue(), \null, $this->getInternalValue());
			return new self($match);
		}

		$new = \str_replace($rightOperand->getInternalValue(), \null, $this->getInternalValue());
		return new self($new);

	}

	public function doMultiplication(Value $rightOperand) {

		// Allow only number as right operands.
		Common::allowTypes($rightOperand, NumberValue::class);

		$multiplier = $rightOperand->value;
		if (\is_int($multiplier) && $multiplier >= 0) {
			return new self(\str_repeat($this->value, $multiplier));
		}

		throw new \TypeError;

	}

	public function doComparison(string $op, Value $rightOperand): BoolValue {

		Common::allowTypes(
			$rightOperand,
			self::class,
			RegexValue::class,
			NumberValue::class
		);

		// Numbers and strings can be only compared for equality.
		// And are never equal.
		if ($rightOperand instanceof NumberValue) {
			if ($op !== "==" && $op !== "!=") {
				throw new \TypeError;
			}
		}

		switch ($op) {
			case "==":

				if ($rightOperand instanceof RegexValue) {
					$result = \preg_match($rightOperand->value, $this->value);
				} else {
					$result = $this->value === $rightOperand->value;
				}

			break;
			case "!=":

				if ($rightOperand instanceof RegexValue) {
					$result = !\preg_match($rightOperand->value, $this->value);
				} else {
					$result = $this->value !== $rightOperand->value;
				}

			break;
			default:
				throw new \TypeError;
		}

		return new BoolValue($result);

	}

	public function arrayGet(string $index): Value {

		$index = (int) $index;

		if (!isset($this->value[$index])) {
			throw new \Smuuf\Primi\InternalUndefinedIndexException($index);
		}

		return new self($this->value[$index]);

	}

	public function arraySet(?string $index, Value $value) {

		// Allow only strings to be inserted.
		Common::allowTypes($value, self::class, NumberValue::class);

		if ($index === \null) {

			// An empty index will cause the value to be appended to the end.
			$this->value .= $value->value;

		} else {

			// If index is specified, PHP own rules for inserting into strings apply.
			$this->value[(int) $index] = $value->value;

		}

	}

	public function getArrayInsertionProxy(?string $index): ArrayInsertionProxy {
		return new ArrayInsertionProxy($this, $index);
	}

	public function getIterator(): \Iterator {
		return self::utfSplit($this->value);
	}

	// Helpers

	protected static function expandSequences(string $string) {

		// Primi strings support some escape sequences.

		$string = preg_replace(['#(?<!\\\)\\\n#', '/\\\\\\\n/'], ["\n", '\n'], $string);

		return $string;

	}

	/**
	 * Return a generator yielding each of this string's characters as
	 * new one-character StringValue objects.
	 */
	private static function utfSplit(string $string): \Generator {

		$strlen = \mb_strlen($string);
		while ($strlen) {
			yield new self(\mb_substr($string, 0, 1, "UTF-8"));
			$string = \mb_substr($string, 1, $strlen, "UTF-8");
			$strlen = \mb_strlen($string);
		}

	}

}
