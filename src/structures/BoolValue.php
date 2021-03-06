<?php

namespace Smuuf\Primi\Structures;

use \Smuuf\Primi\Helpers\Common;
use \Smuuf\Primi\ISupportsComparison;
use \Smuuf\Primi\Structures\NullValue;
use \Smuuf\Primi\Structures\NumberValue;

class BoolValue extends Value implements ISupportsComparison {

	const TYPE = "bool";

	public function __construct(bool $value) {
		$this->value = $value;
	}

	public function getStringValue(): string {
		return $this->value ? 'true' : 'false';
	}

	public function doComparison(string $op, Value $right): BoolValue {

		Common::allowTypes(
			$right,
			self::class,
			NumberValue::class,
			NullValue::class
		);

		$l = $this->value;
		$r = Common::isTruthy($right);

		switch ($op) {
			case "==":
				return new BoolValue($l === $r);
			case "!=":
				return new BoolValue($l !== $r);
			default:
				throw new \TypeError;
		}

	}

}
