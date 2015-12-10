<?php

namespace Application\memreas;

// https://github.com/ramsey/uuid
use Rhumsaa\Uuid\Uuid;
use Rhumsaa\Uuid\Exception\UnsatisfiedDependencyException;
use Application\Model\MemreasConstants;

class MUUID {
	public static function fetchUUID() {
		return ( string ) Uuid::uuid4 ();
	}
}
?>
