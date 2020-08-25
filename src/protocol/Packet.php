<?php

/*
 * RakLib network library
 *
 *
 * This project is not affiliated with Jenkins Software LLC nor RakNet.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace raklib\protocol;

use pocketmine\utils\BinaryDataException;

#include <rules/RakLibPacket.h>

abstract class Packet extends PacketSerializer{
	/** @var int */
	public static $ID = -1;

	public function encode() : void{
		$this->reset();
		$this->encodeHeader();
		$this->encodePayload();
	}

	protected function encodeHeader() : void{
		$this->putByte(static::$ID);
	}

	abstract protected function encodePayload() : void;

	/**
	 * @throws BinaryDataException
	 */
	public function decode() : void{
		$this->rewind();
		$this->decodeHeader();
		$this->decodePayload();
	}

	/**
	 * @throws BinaryDataException
	 */
	protected function decodeHeader() : void{
		$this->getByte(); //PID
	}

	/**
	 * @throws BinaryDataException
	 */
	abstract protected function decodePayload() : void;
}
